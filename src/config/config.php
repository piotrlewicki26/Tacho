<?php
declare(strict_types=1);

// ── PHP 7.4 polyfills ──────────────────────────────────────────────────────
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

// ── .env loader ────────────────────────────────────────────────────────────
$_envFile = dirname(__DIR__, 2) . '/.env';
if (is_file($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        if (strpos($_line, '=') === false) continue;
        [$_key, $_val] = explode('=', $_line, 2);
        $_key = trim($_key);
        $_val = trim($_val);
        // Strip a single pair of matching surrounding quotes
        if (strlen($_val) >= 2
            && (($_val[0] === '"' && $_val[-1] === '"')
                || ($_val[0] === "'" && $_val[-1] === "'"))
        ) {
            $_val = substr($_val, 1, -1);
        }
        if ($_key !== '' && getenv($_key) === false) {
            putenv("{$_key}={$_val}");
            $_ENV[$_key]    = $_val;
            $_SERVER[$_key] = $_val;
        }
    }
}
unset($_envFile, $_line, $_key, $_val);

// ── Application ────────────────────────────────────────────────────────────
define('APP_NAME',     'TachoSystem');
define('APP_VERSION',  '1.0.0');
define('APP_URL',      getenv('APP_URL')   ?: 'http://localhost');
define('APP_DEBUG',    (bool)(getenv('APP_DEBUG') ?: false));

// ── Database ───────────────────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'tacho_system');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── License ────────────────────────────────────────────────────────────────
// Per-company secrets are stored in companies.license_secret (generated on
// company creation).  This global fallback is only used when a company row
// pre-dates the per-company secret feature.  Override in .env.
define('LICENSE_SECRET_KEY', getenv('LICENSE_SECRET') ?: 'CHANGE_THIS_SECRET_KEY_MIN_32_CHARS!!');

// Remote license-authority URL.  When set, TachoSystem calls this endpoint
// once per day to validate each company's active license.
// Leave empty to rely solely on local (SHA-256 hash) verification.
define('LICENSE_VERIFY_URL', getenv('LICENSE_VERIFY_URL') ?: '');

// ── Upload ─────────────────────────────────────────────────────────────────
define('UPLOAD_PATH',    dirname(__DIR__, 2) . '/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50 MB

// ── Session ────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600 * 8); // 8 h

// ── Timezone ───────────────────────────────────────────────────────────────
define('APP_TIMEZONE', 'Europe/Warsaw');
date_default_timezone_set(APP_TIMEZONE);
