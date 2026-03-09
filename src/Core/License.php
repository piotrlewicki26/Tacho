<?php
declare(strict_types=1);
namespace Core;

/**
 * SHA-256-secured, module-based license system.
 *
 * License key format: TACHO-XXXX-XXXX-XXXX-XXXX
 * The SHA-256 hash binds: company_id + key + modules + max_operators +
 *   max_drivers + valid_to + hardware_id + LICENSE_SECRET_KEY
 */
class License
{
    private const PREFIX   = 'TACHO';
    private const PARTS    = 4;
    private const PARTLEN  = 4;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

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
        $key  = self::randomKey();
        $hash = self::computeHash($companyId, $key, $modules, $maxOperators, $maxDrivers, $validTo, $hardwareId);

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
        $expected = self::computeHash(
            $companyId, $licenseKey, $modules,
            (int) $lic['max_operators'], (int) $lic['max_drivers'],
            $lic['valid_to'], $lic['hardware_id']
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

    // ── Internals ──────────────────────────────────────────────────────────

    private static function computeHash(
        int $companyId, string $key, array $modules,
        int $maxOps, int $maxDrv, string $validTo, ?string $hwId
    ): string {
        sort($modules);
        $payload = implode('|', [
            $companyId, $key, implode(',', $modules),
            $maxOps, $maxDrv, $validTo, $hwId ?? '',
            LICENSE_SECRET_KEY,
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
