<?php
declare(strict_types=1);
namespace Core;

/**
 * SHA-256-secured, module-based license system.
 *
 * License key format: TACHO-XXXX-XXXX-XXXX-XXXX
 * The SHA-256 hash binds: company_id + key + modules + max_operators +
 *   max_drivers + valid_to + hardware_id + per-company license_secret
 */
class License
{
    private const PREFIX   = 'TACHO';
    private const PARTS    = 4;
    private const PARTLEN  = 4;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    // ── Secret management ──────────────────────────────────────────────────

    /** Generate a cryptographically random 48-character secret. */
    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(24)); // 48 hex chars
    }

    /** Return the per-company secret; falls back to global constant. */
    public static function companySecret(int $companyId): string
    {
        $row = Database::fetchOne(
            'SELECT license_secret FROM companies WHERE id = :id LIMIT 1',
            ['id' => $companyId]
        );
        return ($row && $row['license_secret'] !== null && $row['license_secret'] !== '')
            ? $row['license_secret']
            : LICENSE_SECRET_KEY;
    }

    // ── Generation ─────────────────────────────────────────────────────────

    public static function generate(
        int     $companyId,
        array   $modules,
        int     $maxOperators,
        int     $maxDrivers,
        string  $validFrom,
        string  $validTo,
        ?string $hardwareId = null
    ): array {
        $key    = self::randomKey();
        $secret = self::companySecret($companyId);
        $hash   = self::computeHash($companyId, $key, $modules, $maxOperators, $maxDrivers, $validTo, $hardwareId, $secret);

        return [
            'license_key'   => $key,
            'sha256_hash'   => $hash,
            'modules'       => json_encode($modules),
            'max_operators' => $maxOperators,
            'max_drivers'   => $maxDrivers,
            'valid_from'    => $validFrom,
            'valid_to'      => $validTo,
            'hardware_id'   => $hardwareId,
            'is_active'     => 1,
        ];
    }

    // ── Activation ─────────────────────────────────────────────────────────

    /**
     * Build and return the license record from externally-supplied parameters.
     * The hash is computed using the company's own secret, binding key + params.
     */
    public static function buildFromKey(
        int     $companyId,
        string  $licenseKey,
        array   $modules,
        int     $maxOperators,
        int     $maxDrivers,
        string  $validFrom,
        string  $validTo,
        ?string $hardwareId = null
    ): array {
        $secret = self::companySecret($companyId);
        $hash   = self::computeHash($companyId, $licenseKey, $modules, $maxOperators, $maxDrivers, $validTo, $hardwareId, $secret);

        return [
            'license_key'   => $licenseKey,
            'sha256_hash'   => $hash,
            'modules'       => json_encode($modules),
            'max_operators' => $maxOperators,
            'max_drivers'   => $maxDrivers,
            'valid_from'    => $validFrom,
            'valid_to'      => $validTo,
            'hardware_id'   => $hardwareId,
            'is_active'     => 1,
        ];
    }

    // ── Validation ─────────────────────────────────────────────────────────

    /** Returns true only if key exists, is active, not expired, and hash matches. */
    public static function validate(int $companyId, string $licenseKey): bool
    {
        $lic = Database::fetchOne(
            'SELECT * FROM licenses
             WHERE company_id = :cid AND license_key = :key
               AND is_active = 1 AND valid_from <= CURDATE() AND valid_to >= CURDATE()
             LIMIT 1',
            ['cid' => $companyId, 'key' => $licenseKey]
        );
        if (!$lic) return false;

        $modules = json_decode($lic['modules'] ?? '[]', true) ?: [];
        $secret  = self::companySecret($companyId);
        $expected = self::computeHash(
            $companyId, $licenseKey, $modules,
            (int) $lic['max_operators'], (int) $lic['max_drivers'],
            $lic['valid_to'], $lic['hardware_id'], $secret
        );
        return hash_equals($expected, $lic['sha256_hash']);
    }

    // ── Module / Limit Checks ──────────────────────────────────────────────

    public static function isModuleAllowed(int $companyId, string $module): bool
    {
        $lic = self::getActive($companyId);
        if (!$lic) return false;
        $modules = json_decode($lic['modules'] ?? '[]', true) ?: [];
        return in_array($module, $modules, true) || in_array('all', $modules, true);
    }

    public static function checkOperatorLimit(int $companyId): bool
    {
        $lic = self::getActive($companyId);
        if (!$lic) return false;
        $count = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM users WHERE company_id = :cid AND is_active = 1 AND role IN ('admin','operator')",
            ['cid' => $companyId]
        );
        return $count < (int) $lic['max_operators'];
    }

    public static function checkDriverLimit(int $companyId): bool
    {
        $lic = self::getActive($companyId);
        if (!$lic) return false;
        $count = (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM drivers WHERE company_id = :cid AND is_active = 1',
            ['cid' => $companyId]
        );
        return $count < (int) $lic['max_drivers'];
    }

    public static function getActive(int $companyId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM licenses
             WHERE company_id = :cid AND is_active = 1
               AND valid_from <= CURDATE() AND valid_to >= CURDATE()
             ORDER BY valid_to DESC LIMIT 1',
            ['cid' => $companyId]
        );
    }

    // ── Remote daily verification ──────────────────────────────────────────

    /**
     * Returns true when the license has not been verified by the remote
     * license authority today (last_verified_at is NULL or before today).
     */
    public static function needsDailyVerification(array $license): bool
    {
        if (empty($license['last_verified_at'])) {
            return true;
        }
        return date('Y-m-d') > date('Y-m-d', strtotime($license['last_verified_at']));
    }

    /**
     * Call the remote license-authority endpoint (LICENSE_VERIFY_URL).
     *
     * Sends a POST request with JSON body {"license_key":"…","company_id":N}.
     * Returns true when the remote server confirms the license is valid.
     * Returns true (fail-open) when the remote URL is not configured or when
     * the network request fails — prevents service disruption on connectivity
     * issues while ensuring verification is retried the next day.
     */
    public static function verifyRemote(int $companyId, array $license): bool
    {
        $url = defined('LICENSE_VERIFY_URL') ? (string) LICENSE_VERIFY_URL : '';
        if ($url === '') {
            return true; // remote verification not configured – local check only
        }

        $payload = (string) json_encode([
            'license_key' => $license['license_key'],
            'company_id'  => $companyId,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                'content'       => $payload,
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return true; // network error – fail open, retry tomorrow
        }

        $data = json_decode($response, true);
        return is_array($data) && isset($data['valid']) && $data['valid'] === true;
    }

    /**
     * Trigger the daily remote check for a company's active license.
     *
     * Queries the remote authority once per day and updates last_verified_at.
     * If the remote server explicitly marks the license as invalid the license
     * is deactivated; local data is otherwise untouched.
     * This method is a no-op when no remote URL is configured or when the
     * license was already verified today.
     */
    public static function checkActiveRemotely(int $companyId): void
    {
        $lic = self::getActive($companyId);
        if (!$lic) return;
        if (!self::needsDailyVerification($lic)) return;

        $valid  = self::verifyRemote($companyId, $lic);
        $update = ['last_verified_at' => date('Y-m-d H:i:s')];
        if (!$valid) {
            $update['is_active'] = 0;
        }
        try {
            Database::update('licenses', $update, 'id = :id', ['id' => (int) $lic['id']]);
        } catch (\PDOException $e) {
            // Ignore "unknown column" errors on installs where the migration
            // for last_verified_at has not yet run.  All other PDO errors
            // (connection lost, constraint violation, …) are also suppressed
            // here because a failing remote-check stamp must never break the
            // main request; the verification will be retried on the next boot.
        }
    }


    private static function computeHash(
        int $companyId, string $key, array $modules,
        int $maxOps, int $maxDrv, string $validTo, ?string $hwId,
        string $secret
    ): string {
        sort($modules);
        $payload = implode('|', [
            $companyId, $key, implode(',', $modules),
            $maxOps, $maxDrv, $validTo, $hwId ?? '',
            $secret,
        ]);
        return hash('sha256', $payload);
    }

    private static function randomKey(): string
    {
        $len   = strlen(self::ALPHABET) - 1;
        $parts = [self::PREFIX];
        for ($i = 0; $i < self::PARTS; $i++) {
            $p = '';
            for ($j = 0; $j < self::PARTLEN; $j++) {
                $p .= self::ALPHABET[random_int(0, $len)];
            }
            $parts[] = $p;
        }
        return implode('-', $parts);
    }
}
