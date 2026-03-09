<?php
declare(strict_types=1);

namespace LicenseGenerator;

use PDO;

/**
 * License generation, validation and management.
 *
 * License key format : TACHO-XXXX-XXXX-XXXX-XXXX
 *                      (4 groups of 4 uppercase alphanumeric characters)
 *
 *   Group 1 – base-36 encoding of the module bitmask (4 chars, left-padded with '0').
 *             Allows decoding which modules are licensed directly from the key.
 *   Groups 2-4 – first 12 hex chars of HMAC-SHA256(secret, company_id|valid_to|group1|nonce),
 *             binding the key to the given secret so only the secret holder
 *             can generate valid keys.
 *
 * Signature hash      : HMAC-SHA256(
 *                           company_id|license_key|modules|max_operators|max_drivers|valid_to|hardware_id,
 *                           LICENSE_SECRET
 *                       )
 */
class LicenseManager
{
    /** Available module identifiers */
    public const MODULES = [
        'all'         => 'Wszystkie moduły',
        'analysis'    => 'Analiza czasu pracy',
        'reports'     => 'Raporty',
        'violations'  => 'Naruszenia przepisów',
        'delegation'  => 'Delegacje',
        'vacation'    => 'Urlopy',
    ];

    /** Bitmask values for individual module identifiers. */
    private const MODULE_BITS = [
        'analysis'   => 1,
        'reports'    => 2,
        'violations' => 4,
        'delegation' => 8,
        'vacation'   => 16,
    ];

    public function __construct(private Database $db) {}

    // -----------------------------------------------------------------------
    // Generation
    // -----------------------------------------------------------------------

    /**
     * Generate a new license key, persist it, and return the full record.
     *
     * @param array{
     *     company_id:     string,
     *     company_name:   string,
     *     modules:        string[],
     *     max_operators:  int,
     *     max_drivers:    int,
     *     valid_from:     string,
     *     valid_to:       string,
     *     hardware_id:    string,
     *     notes:          string,
     * } $data
     * @param string $secret  The HMAC secret to use. Defaults to LICENSE_SECRET constant.
     * @throws \RuntimeException when no usable secret is available.
     */
    public function generate(array $data, ?int $userId = null, string $secret = ''): array
    {
        $secret = ($secret === '') ? LICENSE_SECRET : $secret;
        $this->assertSecret($secret);

        $modules    = $this->normaliseModules($data['modules'] ?? ['all']);
        // Derive the key from the secret so only the secret holder can generate valid keys.
        $licenseKey = $this->deriveKey(
            $data['company_id'],
            $modules,
            $data['valid_to'],
            $secret
        );
        $hash = $this->computeHash(
            $data['company_id'],
            $licenseKey,
            implode(',', $modules),
            (int)$data['max_operators'],
            (int)$data['max_drivers'],
            $data['valid_to'],
            $data['hardware_id'] ?? '',
            $secret
        );

        $stmt = $this->db->prepare(
            'INSERT INTO licenses
                (company_id, company_name, license_key, sha256_hash, modules,
                 max_operators, max_drivers, valid_from, valid_to,
                 hardware_id, is_active, notes, created_by, used_secret)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
        );
        $stmt->execute([
            $data['company_id'],
            $data['company_name'],
            $licenseKey,
            $hash,
            json_encode($modules),
            (int)$data['max_operators'],
            (int)$data['max_drivers'],
            $data['valid_from'],
            $data['valid_to'],
            $data['hardware_id'] ?? '',
            $data['notes'] ?? '',
            $userId,
            $secret,
        ]);

        return array_merge($data, [
            'id'          => (int)$this->db->lastInsertId(),
            'license_key' => $licenseKey,
            'sha256_hash' => $hash,
            'modules'     => $modules,
            'is_active'   => 1,
            'used_secret' => $secret,
        ]);
    }

    // -----------------------------------------------------------------------
    // Verification
    // -----------------------------------------------------------------------

    /**
     * Verify an existing license key.
     *
     * Returns an array with at least the key 'valid' (bool) and 'message' (string).
     */
    public function verify(string $licenseKey, string $companyId): array
    {
        $licenseKey = trim($licenseKey);
        $companyId  = trim($companyId);

        if ($licenseKey === '' || $companyId === '') {
            return ['valid' => false, 'message' => 'Klucz licencji i ID firmy są wymagane.'];
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM licenses WHERE license_key = ? LIMIT 1'
        );
        $stmt->execute([$licenseKey]);
        $license = $stmt->fetch();

        if (!$license) {
            return ['valid' => false, 'message' => 'Klucz licencji nie istnieje w bazie.'];
        }

        if ($license['company_id'] !== $companyId) {
            return ['valid' => false, 'message' => 'ID firmy nie pasuje do licencji.', 'license' => $license];
        }

        if (!(bool)$license['is_active']) {
            return ['valid' => false, 'message' => 'Licencja jest nieaktywna.', 'license' => $license];
        }

        if ($license['valid_to'] < date('Y-m-d')) {
            return ['valid' => false, 'message' => 'Licencja wygasła (' . $license['valid_to'] . ').', 'license' => $license];
        }

        // Use the secret that was stored with this license; fall back to the
        // configured LICENSE_SECRET for licenses created before this feature.
        $secret = ($license['used_secret'] ?? '') ?: LICENSE_SECRET;

        if ($secret === '') {
            return ['valid' => false, 'message' => 'Brak skonfigurowanego sekretu (LICENSE_SECRET) – nie można zweryfikować podpisu.', 'license' => $license];
        }

        $modules    = json_decode($license['modules'], true);
        $modulesStr = implode(',', $this->normaliseModules($modules));

        $expected = $this->computeHash(
            $license['company_id'],
            $licenseKey,
            $modulesStr,
            (int)$license['max_operators'],
            (int)$license['max_drivers'],
            $license['valid_to'],
            $license['hardware_id'],
            $secret
        );

        if (!hash_equals($license['sha256_hash'], $expected)) {
            return ['valid' => false, 'message' => 'Nieprawidłowy skrót kryptograficzny licencji (dane mogły zostać zmodyfikowane).', 'license' => $license];
        }

        return ['valid' => true, 'message' => 'Licencja jest prawidłowa i aktywna.', 'license' => $license];
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    /** Return all licenses ordered by creation date descending. */
    public function getAll(): array
    {
        return $this->db->query(
            'SELECT l.*, u.username AS created_by_name
             FROM licenses l
             LEFT JOIN users u ON u.id = l.created_by
             ORDER BY l.created_at DESC'
        )->fetchAll();
    }

    /** Return a single license by its primary key. */
    public function getById(int $id): array|false
    {
        return $this->db->query(
            'SELECT l.*, u.username AS created_by_name
             FROM licenses l
             LEFT JOIN users u ON u.id = l.created_by
             WHERE l.id = ?',
            [$id]
        )->fetch();
    }

    /** Deactivate a license. */
    public function deactivate(int $id): void
    {
        $this->db->prepare('UPDATE licenses SET is_active = 0 WHERE id = ?')
                 ->execute([$id]);
    }

    /** Activate a license. */
    public function activate(int $id): void
    {
        $this->db->prepare('UPDATE licenses SET is_active = 1 WHERE id = ?')
                 ->execute([$id]);
    }

    /** Delete a license permanently. */
    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM licenses WHERE id = ?')->execute([$id]);
    }

    // -----------------------------------------------------------------------
    // Statistics
    // -----------------------------------------------------------------------

    /** Aggregate statistics for the dashboard. */
    public function getStats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*)                                         AS total,
                SUM(is_active = 1)                               AS active,
                SUM(is_active = 0)                               AS inactive,
                SUM(is_active = 1 AND valid_to < date('now'))    AS expired,
                SUM(is_active = 1 AND valid_to >= date('now'))   AS valid
             FROM licenses"
        )->fetch();

        return array_map('intval', $row);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Compute the HMAC-SHA256 license hash.
     * The message is: company_id|key|modules|max_ops|max_drivers|valid_to|hardware_id
     *
     * @param string $secret  HMAC key. Defaults to the LICENSE_SECRET constant.
     */
    public function computeHash(
        string $companyId,
        string $licenseKey,
        string $modulesStr,
        int    $maxOperators,
        int    $maxDrivers,
        string $validTo,
        string $hardwareId,
        string $secret = ''
    ): string {
        $secret  = ($secret === '') ? LICENSE_SECRET : $secret;
        $message = implode('|', [
            $companyId,
            $licenseKey,
            $modulesStr,
            $maxOperators,
            $maxDrivers,
            $validTo,
            $hardwareId,
        ]);
        return hash_hmac('sha256', $message, $secret);
    }

    /**
     * Sort and deduplicate modules; if 'all' is present keep only 'all'.
     *
     * @param  string[] $modules
     * @return string[]
     */
    public function normaliseModules(array $modules): array
    {
        $modules = array_values(array_unique(array_filter($modules)));
        if (in_array('all', $modules, true)) {
            return ['all'];
        }
        sort($modules);
        return $modules ?: ['all'];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Derive a TACHO-XXXX-XXXX-XXXX-XXXX license key that is bound to the
     * given secret, company, validity date, and active modules.
     *
     * Group 1 – base-36 encoding of the module bitmask (4 uppercase chars).
     * Groups 2-4 – first 12 hex chars of HMAC-SHA256(secret, data + random nonce),
     *              uppercased.  A fresh random nonce ensures each call produces a
     *              unique key even when all other inputs are identical.
     */
    private function deriveKey(
        string $companyId,
        array  $modules,
        string $validTo,
        string $secret
    ): string {
        // Encode module bitmask as 4-char base-36 uppercase group.
        $bitmask = $this->modulesBitmask($modules);
        $group1  = str_pad(
            strtoupper(base_convert((string)$bitmask, 10, 36)),
            4,
            '0',
            STR_PAD_LEFT
        );

        // Random nonce guarantees a unique key on each call.
        $nonce = bin2hex(random_bytes(4));
        $hmac  = hash_hmac('sha256', "{$companyId}|{$validTo}|{$group1}|{$nonce}", $secret);

        return sprintf(
            'TACHO-%s-%s-%s-%s',
            $group1,
            strtoupper(substr($hmac, 0, 4)),
            strtoupper(substr($hmac, 4, 4)),
            strtoupper(substr($hmac, 8, 4))
        );
    }

    /**
     * Compute a bitmask integer for a normalised list of module keys.
     * 'all' is treated as every individual module being active.
     */
    private function modulesBitmask(array $modules): int
    {
        if (in_array('all', $modules, true)) {
            return array_sum(self::MODULE_BITS);  // all bits set
        }
        $bits = 0;
        foreach ($modules as $mod) {
            $bits |= (self::MODULE_BITS[$mod] ?? 0);
        }
        return $bits;
    }

    /**
     * Decode a bitmask integer back to an array of module keys.
     * Returns ['all'] when every individual module bit is set.
     */
    public function bitmaskToModules(int $bitmask): array
    {
        $fullMask = array_sum(self::MODULE_BITS);
        if ($bitmask === $fullMask) {
            return ['all'];
        }
        $mods = [];
        foreach (self::MODULE_BITS as $mod => $bit) {
            if ($bitmask & $bit) {
                $mods[] = $mod;
            }
        }
        return $mods;
    }

    /**
     * Decode the module bitmask that is encoded in the first group of a
     * TACHO-XXXX-XXXX-XXXX-XXXX license key.
     *
     * Returns an empty array when the key has an unexpected format.
     *
     * @return string[]
     */
    public function decodeModulesFromKey(string $licenseKey): array
    {
        // Expected format: TACHO-XXXX-XXXX-XXXX-XXXX
        if (!preg_match('/^TACHO-([A-Z0-9]{4})-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', strtoupper($licenseKey), $m)) {
            return [];
        }
        $bitmask = (int)base_convert(strtolower($m[1]), 36, 10);
        return $this->bitmaskToModules($bitmask);
    }

    /** Throw when no secret is configured or provided. */
    private function assertSecret(string $secret): void
    {
        if ($secret === '') {
            throw new \RuntimeException(
                'LICENSE_SECRET nie jest skonfigurowany. Uruchom setup.php, ustaw zmienną środowiskową lub podaj sekret w formularzu.'
            );
        }
    }
}
