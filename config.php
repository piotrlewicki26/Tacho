<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Load .env file
// ---------------------------------------------------------------------------
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, '"\'');
        if (!isset($_ENV[$name])) {
            putenv("{$name}={$value}");
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define('APP_PATH',      __DIR__);
define('SRC_PATH',      APP_PATH . '/src');
define('VIEWS_PATH',    APP_PATH . '/views');
define('DATABASE_PATH', (getenv('DATABASE_PATH') ?: APP_PATH . '/database/licenses.db'));
define('LICENSE_SECRET', (string)(getenv('LICENSE_SECRET') ?: ''));
define('API_KEY',        (string)(getenv('API_KEY')        ?: ''));
define('APP_TITLE',     'TachoSystem – Generator Licencji');
define('APP_DEBUG',     filter_var((getenv('APP_DEBUG') ?: 'false'), FILTER_VALIDATE_BOOLEAN));

// ---------------------------------------------------------------------------
// PHP runtime settings
// ---------------------------------------------------------------------------
error_reporting(APP_DEBUG ? E_ALL : (E_ALL & ~E_NOTICE & ~E_DEPRECATED));
ini_set('display_errors', APP_DEBUG ? '1' : '0');
date_default_timezone_set('Europe/Warsaw');
mb_internal_encoding('UTF-8');
