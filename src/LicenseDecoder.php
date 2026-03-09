<?php
declare(strict_types=1);

namespace LicenseGenerator;

/**
 * Portable, database-free license key encoder / decoder for TachoSystem.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * DROP-IN INTEGRATION FOR THE SETTLEMENT SYSTEM
 * ───────────────────────────────────────────────────────────────────────────
 * Copy this single file into any PHP 8.0+ application.  No other files from
 * the license-generator project are required.  Remove the namespace
 * declaration (or adjust it) to match the target application's autoloading.
 *
 * Prerequisite: the PHP GMP extension (ext-gmp), which ships enabled by
 * default on virtually all modern Linux PHP packages.
 *
 * Example (settlement system):
 *
 *   $data = LicenseDecoder::decode($licenseKey, $configuredSecret);
 *   if ($data === false) {
 *       throw new RuntimeException('Invalid or tampered license key.');
 *   }
 *   if (!LicenseDecoder::isCurrentlyValid($licenseKey, $configuredSecret)) {
 *       throw new RuntimeException('License has expired.');
 *   }
 *   if (!LicenseDecoder::hasModule($licenseKey, $configuredSecret, 'analysis')) {
 *       throw new RuntimeException('Analysis module is not licensed.');
 *   }
 *   echo "Max drivers: " . $data['max_drivers'];
 *
 * ───────────────────────────────────────────────────────────────────────────
 * KEY FORMAT  :  TACHO-XXXX-XXXX-XXXX-XXXX
 *   16 uppercase alphanumeric chars (base-36) = ~82.7 bits of capacity.
 *
 * BIT LAYOUT (bit 0 = least significant):
 *   bits  0-4  (5 bits)  : module bitmask  (0-31)
 *   bits  5-18 (14 bits) : valid_from days since DATE_EPOCH  (0-16383)
 *   bits 19-32 (14 bits) : valid_to   days since DATE_EPOCH  (0-16383)
 *   bits 33-46 (14 bits) : max_operators  (1-9999)
 *   bits 47-63 (17 bits) : max_drivers    (1-99999)
 *   bits 64-81 (18 bits) : first 18 bits of HMAC-SHA256(secret, payload_bytes)
 *
 * DATE_EPOCH : 2020-01-01
 *   14-bit days → max 16383 days → covers dates up to 2064-11-08.
 * ───────────────────────────────────────────────────────────────────────────
 */
class LicenseDecoder
{
    /** Day-zero for date encoding; dates before this cannot be encoded. */
    public const DATE_EPOCH = '2020-01-01';

    /** Maximum representable days offset (14-bit = 16383 days ≈ 44.8 years). */
    public const MAX_DAYS = 16383;

    /**
     * Bit values for individual module identifiers.
     * 'all' is a virtual key meaning every module is active (full bitmask = 31).
     */
    public const MODULE_BITS = [
        'analysis'   => 1,
        'reports'    => 2,
        'violations' => 4,
        'delegation' => 8,
        'vacation'   => 16,
    ];

    /** Bitmask value that represents every individual module being active. */
    public const ALL_MODULES_MASK = 31;  // 1|2|4|8|16

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Decode a TACHO-XXXX-XXXX-XXXX-XXXX license key.
     *
     * Returns the decoded license data on success, or false when:
     *   - the key has an unexpected format, or
     *   - the embedded HMAC checksum does not match the provided secret.
     *
     * The return value does NOT check whether the license is currently active
     * (i.e. today is within [valid_from, valid_to]).  Use isCurrentlyValid()
     * for that combined check.
     *
     * @param  string $licenseKey  The full license key, e.g. "TACHO-A1B2-C3D4-E5F6-G7H8".
     * @param  string $secret      The HMAC secret that was used when the key was generated.
     * @return array{
     *     modules:       string[],
     *     valid_from:    string,
     *     valid_to:      string,
     *     max_operators: int,
     *     max_drivers:   int,
     * }|false
     */
    public static function decode(string $licenseKey, string $secret): array|false
    {
        self::requireGmp();

        $b36 = self::normaliseKey($licenseKey);
        if ($b36 === null) {
            return false;
        }

        $n = gmp_init(strtolower($b36), 36);

        // Split 82-bit number: top 18 bits = HMAC, bottom 64 bits = payload.
        $mask64  = gmp_sub(gmp_pow(2, 64), 1);
        $payload = gmp_and($n, $mask64);
        $hmac18  = gmp_intval(gmp_div_q($n, gmp_pow(2, 64)));

        // Recompute HMAC from the 8-byte big-endian payload and verify.
        $payload8       = str_pad(gmp_export($payload), 8, "\0", STR_PAD_LEFT);
        $computed       = hash_hmac('sha256', $payload8, $secret, true);
        $computed18     = (ord($computed[0]) << 10) | (ord($computed[1]) << 2) | (ord($computed[2]) >> 6);

        if ($hmac18 !== $computed18) {
            return false;
        }

        // Extract individual fields from the payload.
        $moduleMask   = gmp_intval(gmp_and($payload, gmp_init(0x1F)));
        $daysFrom     = gmp_intval(gmp_and(gmp_div_q($payload, gmp_pow(2,  5)), gmp_init(0x3FFF)));
        $daysTo       = gmp_intval(gmp_and(gmp_div_q($payload, gmp_pow(2, 19)), gmp_init(0x3FFF)));
        $maxOperators = gmp_intval(gmp_and(gmp_div_q($payload, gmp_pow(2, 33)), gmp_init(0x3FFF)));
        $maxDrivers   = gmp_intval(gmp_and(gmp_div_q($payload, gmp_pow(2, 47)), gmp_init(0x1FFFF)));

        $epoch     = new \DateTimeImmutable(self::DATE_EPOCH);
        $validFrom = $epoch->modify("+{$daysFrom} days")->format('Y-m-d');
        $validTo   = $epoch->modify("+{$daysTo} days")->format('Y-m-d');

        return [
            'modules'       => self::maskToModules($moduleMask),
            'valid_from'    => $validFrom,
            'valid_to'      => $validTo,
            'max_operators' => $maxOperators,
            'max_drivers'   => $maxDrivers,
        ];
    }

    /**
     * Return true when the license key decodes successfully AND today's date
     * falls within the encoded [valid_from, valid_to] range.
     */
    public static function isCurrentlyValid(string $licenseKey, string $secret): bool
    {
        $data = self::decode($licenseKey, $secret);
        if ($data === false) {
            return false;
        }
        $today = date('Y-m-d');
        return $data['valid_from'] <= $today && $today <= $data['valid_to'];
    }

    /**
     * Return true when the license key is currently valid AND the named module
     * is included in the encoded module list (or 'all' is encoded).
     *
     * @param string $module  One of the keys in MODULE_BITS, e.g. 'analysis'.
     */
    public static function hasModule(string $licenseKey, string $secret, string $module): bool
    {
        $data = self::decode($licenseKey, $secret);
        if ($data === false) {
            return false;
        }
        $today = date('Y-m-d');
        if ($data['valid_from'] > $today || $today > $data['valid_to']) {
            return false;
        }
        return in_array('all', $data['modules'], true)
            || in_array($module, $data['modules'], true);
    }

    // -----------------------------------------------------------------------
    // Internal encoding — used by LicenseManager::generate()
    // -----------------------------------------------------------------------

    /**
     * Encode license metadata into a TACHO-XXXX-XXXX-XXXX-XXXX key.
     *
     * The resulting key self-contains all the provided metadata and an 18-bit
     * HMAC-SHA256 integrity check bound to the given secret.  Any party that
     * holds the same secret can decode and verify the key offline.
     *
     * @param  string[] $modules       Normalised module list, e.g. ['all'] or ['analysis','reports'].
     * @param  string   $validFrom     ISO date, must be ≥ DATE_EPOCH (2020-01-01).
     * @param  string   $validTo       ISO date, must be ≥ DATE_EPOCH and ≥ $validFrom.
     * @param  int      $maxOperators  1–9999.
     * @param  int      $maxDrivers    1–99999.
     * @param  string   $secret        HMAC key (the configured LICENSE_SECRET).
     * @throws \RangeException   when a value cannot be represented in the key.
     * @throws \RuntimeException when GMP is not available.
     *
     * @internal Called by LicenseManager::generate().
     */
    public static function encode(
        array  $modules,
        string $validFrom,
        string $validTo,
        int    $maxOperators,
        int    $maxDrivers,
        string $secret
    ): string {
        self::requireGmp();

        $moduleMask = self::modulesToMask($modules);
        $epoch      = new \DateTimeImmutable(self::DATE_EPOCH);

        $daysFrom = self::dateToDays($validFrom, $epoch, 'valid_from');
        $daysTo   = self::dateToDays($validTo,   $epoch, 'valid_to');

        if ($maxOperators < 1 || $maxOperators > 9999) {
            throw new \RangeException("max_operators must be 1–9999 (got {$maxOperators}).");
        }
        if ($maxDrivers < 1 || $maxDrivers > 99999) {
            throw new \RangeException("max_drivers must be 1–99999 (got {$maxDrivers}).");
        }

        // Build 64-bit payload GMP integer.
        $n = gmp_init($moduleMask);
        $n = gmp_add($n, gmp_mul(gmp_init($daysFrom),     gmp_pow(2,  5)));
        $n = gmp_add($n, gmp_mul(gmp_init($daysTo),       gmp_pow(2, 19)));
        $n = gmp_add($n, gmp_mul(gmp_init($maxOperators), gmp_pow(2, 33)));
        $n = gmp_add($n, gmp_mul(gmp_init($maxDrivers),   gmp_pow(2, 47)));

        // Compute 18-bit HMAC over the 8-byte big-endian payload.
        $payload8 = str_pad(gmp_export($n), 8, "\0", STR_PAD_LEFT);
        $hmac     = hash_hmac('sha256', $payload8, $secret, true);
        $hmac18   = (ord($hmac[0]) << 10) | (ord($hmac[1]) << 2) | (ord($hmac[2]) >> 6);

        // Combine into 82-bit number: checksum in bits 64-81, payload in bits 0-63.
        $combined = gmp_add($n, gmp_mul(gmp_init($hmac18), gmp_pow(2, 64)));

        $b36 = strtoupper(str_pad(gmp_strval($combined, 36), 16, '0', STR_PAD_LEFT));

        return sprintf(
            'TACHO-%s-%s-%s-%s',
            substr($b36, 0, 4),
            substr($b36, 4, 4),
            substr($b36, 8, 4),
            substr($b36, 12, 4)
        );
    }

    // -----------------------------------------------------------------------
    // Module bitmask helpers  (also used by LicenseManager)
    // -----------------------------------------------------------------------

    /**
     * Convert a list of module keys to the corresponding bitmask integer.
     * 'all' maps to ALL_MODULES_MASK (every individual bit set).
     */
    public static function modulesToMask(array $modules): int
    {
        if (in_array('all', $modules, true)) {
            return self::ALL_MODULES_MASK;
        }
        $bits = 0;
        foreach ($modules as $mod) {
            $bits |= (self::MODULE_BITS[$mod] ?? 0);
        }
        return $bits;
    }

    /**
     * Convert a bitmask integer back to a list of module keys.
     * Returns ['all'] when every individual module bit is set.
     *
     * @return string[]
     */
    public static function maskToModules(int $mask): array
    {
        if ($mask === self::ALL_MODULES_MASK) {
            return ['all'];
        }
        $mods = [];
        foreach (self::MODULE_BITS as $mod => $bit) {
            if ($mask & $bit) {
                $mods[] = $mod;
            }
        }
        return $mods;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Strip prefix and dashes; validate the key format.
     * Returns the raw 16-char base-36 string, or null on format mismatch.
     */
    private static function normaliseKey(string $key): ?string
    {
        $upper = strtoupper(trim($key));
        if (!preg_match(
            '/^TACHO-([A-Z0-9]{4})-([A-Z0-9]{4})-([A-Z0-9]{4})-([A-Z0-9]{4})$/',
            $upper,
            $m
        )) {
            return null;
        }
        return $m[1] . $m[2] . $m[3] . $m[4];
    }

    /**
     * Convert an ISO date string to days since $epoch.
     *
     * @throws \RangeException when the date is before the epoch or exceeds MAX_DAYS.
     */
    private static function dateToDays(string $date, \DateTimeImmutable $epoch, string $label): int
    {
        $dt   = new \DateTimeImmutable($date);
        $diff = $epoch->diff($dt);
        $days = $diff->invert ? -(int)$diff->days : (int)$diff->days;

        if ($days < 0) {
            throw new \RangeException(
                "{$label} ({$date}) must be on or after " . self::DATE_EPOCH . '.'
            );
        }
        if ($days > self::MAX_DAYS) {
            throw new \RangeException(
                "{$label} ({$date}) exceeds the maximum encodable date "
                . "(+" . self::MAX_DAYS . " days from " . self::DATE_EPOCH . ")."
            );
        }
        return $days;
    }

    /** Throw a clear error when the GMP extension is not loaded. */
    private static function requireGmp(): void
    {
        if (!extension_loaded('gmp')) {
            throw new \RuntimeException(
                'The PHP GMP extension (ext-gmp) is required for license key '
                . 'encoding/decoding. Install it with: apt install php-gmp'
            );
        }
    }
}
