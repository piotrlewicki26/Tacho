<?php
declare(strict_types=1);

namespace LicenseGenerator;

use PDO;

/**
 * License generation, validation and management.
 *
 * License key format : TACHO-XXXX-XXXX-XXXX-XXXX
 *                      (16 uppercase alphanumeric base-36 characters ≈ 82.7 bits)
 *
 * All license metadata is encoded directly inside the key body by
 * LicenseDecoder::encode():
 *   bits  0-4  : module bitmask
 *   bits  5-18 : valid_from (days since 2020-01-01)
 *   bits 19-32 : valid_to   (days since 2020-01-01)
 *   bits 33-46 : max_operators
 *   bits 47-63 : max_drivers
 *   bits 64-81 : 18-bit HMAC-SHA256 checksum (bound to LICENSE_SECRET)
 *
 * This means the key is self-contained: any system that holds the matching
 * LICENSE_SECRET can validate the license and read its parameters offline,
 * without a database.  See LicenseDecoder for the portable decoder.
 *
 * The database additionally stores a full HMAC-SHA256 signature over:
 *   company_id|license_key|modules|max_operators|max_drivers|valid_from|valid_to|hardware_id
 * This provides a second integrity layer for the generator's own records.
 */
class LicenseManager
{
    /** Available module identifiers — human-readable labels. */
    public const MODULES = [
        'all'         => 'Wszystkie moduły',
        'analysis'    => 'Analiza czasu pracy',
        'reports'     => 'Raporty',
        'violations'  => 'Naruszenia przepisów',
        'delegation'  => 'Delegacje',
        'vacation'    => 'Urlopy',
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
        // Encode all metadata + HMAC checksum into the key body.
        $licenseKey = LicenseDecoder::encode(
            $modules,
            $data['valid_from'],
            $data['valid_to'],
            (int)$data['max_operators'],
            (int)$data['max_drivers'],
            $secret
        );
        $hash = $this->computeHash(
            $data['company_id'],
            $licenseKey,
            implode(',', $modules),
            (int)$data['max_operators'],
            (int)$data['max_drivers'],
            $data['valid_from'],
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
     * Verify an existing license key using the database record as the source of truth.
     *
     * Three-layer check:
     *   1. DB record exists and is active / not expired.
     *   2. The key's embedded HMAC (offline layer) matches the stored secret.
     *   3. The DB-stored HMAC-SHA256 signature over the full record fields matches.
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

        // Use the secret stored with this license; fall back to the configured
        // LICENSE_SECRET for licenses created before per-license secrets existed.
        $secret = ($license['used_secret'] ?? '') ?: LICENSE_SECRET;

        if ($secret === '') {
            return ['valid' => false, 'message' => 'Brak skonfigurowanego sekretu (LICENSE_SECRET) – nie można zweryfikować podpisu.', 'license' => $license];
        }

        // ── Layer 2: offline key HMAC ───────────────────────────────────────
        // Decode the key body and cross-check its embedded metadata against the
        // DB record.  This catches any tampering in either the key or the DB row.
        $keyData = LicenseDecoder::decode($licenseKey, $secret);
        if ($keyData !== false) {
            $rawModules = json_decode($license['modules'], true);
            if (!is_array($rawModules)) {
                return [
                    'valid'   => false,
                    'message' => 'Nieprawidłowe dane JSON w polu modules rekordu bazy danych.',
                    'license' => $license,
                ];
            }
            $dbModules = $this->normaliseModules($rawModules);
            if (
                $keyData['valid_from']    !== $license['valid_from']          ||
                $keyData['valid_to']      !== $license['valid_to']            ||
                $keyData['max_operators'] !== (int)$license['max_operators']  ||
                $keyData['max_drivers']   !== (int)$license['max_drivers']    ||
                $keyData['modules']       !== $dbModules
            ) {
                return [
                    'valid'   => false,
                    'message' => 'Dane zakodowane w kluczu nie zgadzają się z rekordem bazy danych (możliwa manipulacja).',
                    'license' => $license,
                ];
            }
        }

        // ── Layer 3: DB-stored HMAC-SHA256 ─────────────────────────────────
        $rawModules = json_decode($license['modules'], true);
        if (!is_array($rawModules)) {
            return [
                'valid'   => false,
                'message' => 'Nieprawidłowe dane JSON w polu modules rekordu bazy danych.',
                'license' => $license,
            ];
        }
        $modulesStr = implode(',', $this->normaliseModules($rawModules));

        $expected = $this->computeHash(
            $license['company_id'],
            $licenseKey,
            $modulesStr,
            (int)$license['max_operators'],
            (int)$license['max_drivers'],
            $license['valid_from'],
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
     * Compute the HMAC-SHA256 signature stored in the database record.
     *
     * Message: company_id|license_key|modules|max_operators|max_drivers|valid_from|valid_to|hardware_id
     *
     * @param string $secret  HMAC key. Defaults to the LICENSE_SECRET constant.
     */
    public function computeHash(
        string $companyId,
        string $licenseKey,
        string $modulesStr,
        int    $maxOperators,
        int    $maxDrivers,
        string $validFrom,
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
            $validFrom,
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

    /**
     * Offline-decode a license key without a database lookup.
     *
     * Returns the decoded metadata array (same shape as LicenseDecoder::decode()),
     * or false when the key format is invalid or the HMAC does not match the secret.
     *
     * @param string $secret  Defaults to the configured LICENSE_SECRET.
     * @return array{
     *     modules:       string[],
     *     valid_from:    string,
     *     valid_to:      string,
     *     max_operators: int,
     *     max_drivers:   int,
     * }|false
     */
    public function decodeKey(string $licenseKey, string $secret = ''): array|false
    {
        $secret = ($secret === '') ? LICENSE_SECRET : $secret;
        return LicenseDecoder::decode($licenseKey, $secret);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

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
