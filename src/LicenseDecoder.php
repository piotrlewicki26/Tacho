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
 * Requires either ext-gmp (preferred, faster) or ext-bcmath (fallback).
 * Both ship as standard extensions with virtually all PHP distributions.
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
        $b36 = self::normaliseKey($licenseKey);
        if ($b36 === null) {
            return false;
        }

        if (extension_loaded('gmp')) {
            return self::decodeGmp($b36, $secret);
        } elseif (extension_loaded('bcmath')) {
            return self::decodeBc($b36, $secret);
        }

        throw new \RuntimeException(
            'A PHP big-integer extension is required for license key decoding. '
            . 'Install either ext-gmp (apt install php-gmp) or ext-bcmath (apt install php-bcmath).'
        );
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
     * @param  string   $secret        The per-license HMAC secret.
     * @throws \RangeException   when a value cannot be represented in the key.
     * @throws \RuntimeException when neither ext-gmp nor ext-bcmath is available.
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

        if (extension_loaded('gmp')) {
            return self::encodeGmp($moduleMask, $daysFrom, $daysTo, $maxOperators, $maxDrivers, $secret);
        } elseif (extension_loaded('bcmath')) {
            return self::encodeBc($moduleMask, $daysFrom, $daysTo, $maxOperators, $maxDrivers, $secret);
        }

        throw new \RuntimeException(
            'A PHP big-integer extension is required for license key encoding. '
            . 'Install either ext-gmp (apt install php-gmp) or ext-bcmath (apt install php-bcmath).'
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

    // -----------------------------------------------------------------------
    // GMP implementations (preferred – faster)
    // -----------------------------------------------------------------------

    private static function encodeGmp(
        int    $moduleMask,
        int    $daysFrom,
        int    $daysTo,
        int    $maxOperators,
        int    $maxDrivers,
        string $secret
    ): string {
        $n = gmp_init($moduleMask);
        $n = gmp_add($n, gmp_mul(gmp_init($daysFrom),     gmp_pow(2,  5)));
        $n = gmp_add($n, gmp_mul(gmp_init($daysTo),       gmp_pow(2, 19)));
        $n = gmp_add($n, gmp_mul(gmp_init($maxOperators), gmp_pow(2, 33)));
        $n = gmp_add($n, gmp_mul(gmp_init($maxDrivers),   gmp_pow(2, 47)));

        $payload8 = str_pad(gmp_export($n), 8, "\0", STR_PAD_LEFT);
        $hmac     = hash_hmac('sha256', $payload8, $secret, true);
        $hmac18   = (ord($hmac[0]) << 10) | (ord($hmac[1]) << 2) | (ord($hmac[2]) >> 6);

        $combined = gmp_add($n, gmp_mul(gmp_init($hmac18), gmp_pow(2, 64)));
        $b36      = strtoupper(str_pad(gmp_strval($combined, 36), 16, '0', STR_PAD_LEFT));

        return sprintf('TACHO-%s-%s-%s-%s',
            substr($b36, 0, 4), substr($b36, 4, 4),
            substr($b36, 8, 4), substr($b36, 12, 4));
    }

    private static function decodeGmp(string $b36, string $secret): array|false
    {
        $n       = gmp_init(strtolower($b36), 36);
        $mask64  = gmp_sub(gmp_pow(2, 64), 1);
        $payload = gmp_and($n, $mask64);
        $hmac18  = gmp_intval(gmp_div_q($n, gmp_pow(2, 64)));

        $payload8   = str_pad(gmp_export($payload), 8, "\0", STR_PAD_LEFT);
        $computed   = hash_hmac('sha256', $payload8, $secret, true);
        $computed18 = (ord($computed[0]) << 10) | (ord($computed[1]) << 2) | (ord($computed[2]) >> 6);

        if ($hmac18 !== $computed18) {
            return false;
        }

        $moduleMask   = gmp_intval(gmp_and($payload, gmp_init(0x1F)));
        $daysFrom     = gmp_intval(gmp_and(gmp_div_q($payload, gmp_pow(2,  5)), gmp_init(0x3FFF)));
        $daysTo       = gmp_intval(gmp_and(gmp_div_q($payload, gmp_pow(2, 19)), gmp_init(0x3FFF)));
        $maxOperators = gmp_intval(gmp_and(gmp_div_q($payload, gmp_pow(2, 33)), gmp_init(0x3FFF)));
        $maxDrivers   = gmp_intval(gmp_and(gmp_div_q($payload, gmp_pow(2, 47)), gmp_init(0x1FFFF)));

        return self::buildResult($moduleMask, $daysFrom, $daysTo, $maxOperators, $maxDrivers);
    }

    // -----------------------------------------------------------------------
    // BCMath implementations (fallback when ext-gmp is absent)
    // -----------------------------------------------------------------------

    private static function encodeBc(
        int    $moduleMask,
        int    $daysFrom,
        int    $daysTo,
        int    $maxOperators,
        int    $maxDrivers,
        string $secret
    ): string {
        $n = (string)$moduleMask;
        $n = bcadd($n, bcmul((string)$daysFrom,     bcpow('2',  '5', 0), 0), 0);
        $n = bcadd($n, bcmul((string)$daysTo,        bcpow('2', '19', 0), 0), 0);
        $n = bcadd($n, bcmul((string)$maxOperators,  bcpow('2', '33', 0), 0), 0);
        $n = bcadd($n, bcmul((string)$maxDrivers,    bcpow('2', '47', 0), 0), 0);

        $payload8 = self::bcToBytes8($n);
        $hmac     = hash_hmac('sha256', $payload8, $secret, true);
        $hmac18   = (ord($hmac[0]) << 10) | (ord($hmac[1]) << 2) | (ord($hmac[2]) >> 6);

        $combined = bcadd($n, bcmul((string)$hmac18, bcpow('2', '64', 0), 0), 0);
        $b36      = str_pad(self::bcToB36($combined), 16, '0', STR_PAD_LEFT);

        return sprintf('TACHO-%s-%s-%s-%s',
            substr($b36, 0, 4), substr($b36, 4, 4),
            substr($b36, 8, 4), substr($b36, 12, 4));
    }

    private static function decodeBc(string $b36, string $secret): array|false
    {
        $n       = self::bcFromB36(strtolower($b36));
        $pow64   = bcpow('2', '64', 0);
        $hmac18  = (int)bcdiv($n, $pow64, 0);
        $payload = bcmod($n, $pow64);

        $payload8   = self::bcToBytes8($payload);
        $computed   = hash_hmac('sha256', $payload8, $secret, true);
        $computed18 = (ord($computed[0]) << 10) | (ord($computed[1]) << 2) | (ord($computed[2]) >> 6);

        if ($hmac18 !== $computed18) {
            return false;
        }

        $moduleMask   = (int)bcmod($payload, '32');
        $daysFrom     = self::bcGetBits($payload,  5, 14);
        $daysTo       = self::bcGetBits($payload, 19, 14);
        $maxOperators = self::bcGetBits($payload, 33, 14);
        $maxDrivers   = self::bcGetBits($payload, 47, 17);

        return self::buildResult($moduleMask, $daysFrom, $daysTo, $maxOperators, $maxDrivers);
    }

    /** Shared result builder used by both decodeGmp and decodeBc. */
    private static function buildResult(
        int $moduleMask,
        int $daysFrom,
        int $daysTo,
        int $maxOperators,
        int $maxDrivers
    ): array {
        $epoch = new \DateTimeImmutable(self::DATE_EPOCH);
        return [
            'modules'       => self::maskToModules($moduleMask),
            'valid_from'    => $epoch->modify("+{$daysFrom} days")->format('Y-m-d'),
            'valid_to'      => $epoch->modify("+{$daysTo} days")->format('Y-m-d'),
            'max_operators' => $maxOperators,
            'max_drivers'   => $maxDrivers,
        ];
    }

    // -----------------------------------------------------------------------
    // BCMath arithmetic helpers
    // -----------------------------------------------------------------------

    /** Base-36 lowercase string → BCMath decimal string. */
    private static function bcFromB36(string $b36): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';
        $dec = '0';
        for ($i = 0, $len = strlen($b36); $i < $len; $i++) {
            $digit = strpos($alphabet, $b36[$i]);
            $dec   = bcadd(bcmul($dec, '36', 0), (string)$digit, 0);
        }
        return $dec;
    }

    /** BCMath decimal string → uppercase base-36 string. */
    private static function bcToB36(string $dec): string
    {
        if (bccomp($dec, '0', 0) === 0) {
            return '0';
        }
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result   = '';
        while (bccomp($dec, '0', 0) > 0) {
            $rem    = (int)bcmod($dec, '36');
            $result = $alphabet[$rem] . $result;
            $dec    = bcdiv($dec, '36', 0);
        }
        return $result;
    }

    /** BCMath decimal string → 8-byte big-endian binary string. */
    private static function bcToBytes8(string $dec): string
    {
        $hex = '';
        while (bccomp($dec, '0', 0) > 0) {
            $byte = (int)bcmod($dec, '256');
            $hex  = sprintf('%02x', $byte) . $hex;
            $dec  = bcdiv($dec, '256', 0);
        }
        return str_pad(hex2bin($hex ?: '00'), 8, "\0", STR_PAD_LEFT);
    }

    /** Extract $bits bits from BCMath decimal $n at bit position $from. */
    private static function bcGetBits(string $n, int $from, int $bits): int
    {
        $shifted = bcdiv($n, bcpow('2', (string)$from, 0), 0);
        return (int)bcmod($shifted, bcpow('2', (string)$bits, 0));
    }
}
