<?php
declare(strict_types=1);

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
// Override this in .env or environment – NEVER commit the real value.
define('LICENSE_SECRET_KEY', getenv('LICENSE_SECRET') ?: 'CHANGE_THIS_SECRET_KEY_MIN_32_CHARS!!');

// ── Upload ─────────────────────────────────────────────────────────────────
define('UPLOAD_PATH',    dirname(__DIR__, 2) . '/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50 MB

// ── Session ────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600 * 8); // 8 h

// ── Timezone ───────────────────────────────────────────────────────────────
define('APP_TIMEZONE', 'Europe/Warsaw');
date_default_timezone_set(APP_TIMEZONE);
