<?php
declare(strict_types=1);
/**
 * TachoSystem – FTP Deploy Script
 *
 * Usage:
 *   1. Edit FTP credentials below (or set via environment variables)
 *   2. Run: php setup/ftp_deploy.php
 *
 * This script recursively uploads all project files to an FTP server,
 * useful for shared hosting providers like Cyberfolks.
 */

// ── Configuration ──────────────────────────────────────────────────────────
$ftpConfig = [
    'host'       => getenv('FTP_HOST')     ?: 'ftp.twojadomena.pl',
    'user'       => getenv('FTP_USER')     ?: 'ftp_user',
    'password'   => getenv('FTP_PASSWORD') ?: '',
    'remote_dir' => getenv('FTP_REMOTE')   ?: '/public_html/',
    'port'       => (int)(getenv('FTP_PORT') ?: 21),
    'passive'    => true,
];

// Local root of the project (parent of setup/)
$localRoot = dirname(__DIR__);

// Files/directories to skip
$skipPatterns = [
    '.git',
    '.installed',
    '.env',
    'vendor',
    'uploads',
    'setup/ftp_deploy.php',   // don't upload this script
];

// ── Check FTP extension ─────────────────────────────────────────────────────
if (!function_exists('ftp_connect')) {
    die("ERROR: PHP FTP extension is not available. Install it with: apt-get install php-ftp\n");
}

if (empty($ftpConfig['password'])) {
    die("ERROR: FTP_PASSWORD not set. Set the environment variable or edit this file.\n");
}

// ── Connect ─────────────────────────────────────────────────────────────────
echo "Łączę z {$ftpConfig['host']}:{$ftpConfig['port']}...\n";
$conn = ftp_connect($ftpConfig['host'], $ftpConfig['port'], 30);
if (!$conn) {
    die("ERROR: Cannot connect to FTP server.\n");
}

if (!ftp_login($conn, $ftpConfig['user'], $ftpConfig['password'])) {
    ftp_close($conn);
    die("ERROR: FTP login failed.\n");
}

if ($ftpConfig['passive']) {
    ftp_pasv($conn, true);
}

echo "Połączono pomyślnie.\n\n";

// ── Upload ──────────────────────────────────────────────────────────────────
$uploaded = 0;
$skipped  = 0;
$errors   = 0;

uploadDir($conn, $localRoot, $ftpConfig['remote_dir'], $localRoot, $skipPatterns, $uploaded, $skipped, $errors);

echo "\n──────────────────────────────────────\n";
echo "Wgrano:    $uploaded plików\n";
echo "Pominięto: $skipped elementów\n";
echo "Błędy:     $errors\n";

ftp_close($conn);

// ── Functions ───────────────────────────────────────────────────────────────

function uploadDir(
    $conn, string $localDir, string $remoteDir, string $localRoot,
    array $skipPatterns, int &$uploaded, int &$skipped, int &$errors
): void {
    // Ensure remote directory exists
    ftpMkdir($conn, $remoteDir);

    $items = scandir($localDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $localPath  = $localDir . DIRECTORY_SEPARATOR . $item;
        $remotePath = rtrim($remoteDir, '/') . '/' . $item;
        $relPath    = ltrim(str_replace($localRoot, '', $localPath), DIRECTORY_SEPARATOR);

        // Check skip patterns
        foreach ($skipPatterns as $pattern) {
            if (str_starts_with($relPath, $pattern) || $item === $pattern) {
                echo "  SKIP  $relPath\n";
                $skipped++;
                continue 2;
            }
        }

        if (is_dir($localPath)) {
            uploadDir($conn, $localPath, $remotePath, $localRoot, $skipPatterns, $uploaded, $skipped, $errors);
        } else {
            $result = ftp_put($conn, $remotePath, $localPath, FTP_BINARY);
            if ($result) {
                echo "  OK    $relPath\n";
                $uploaded++;
            } else {
                echo "  ERROR $relPath\n";
                $errors++;
            }
        }
    }
}

function ftpMkdir($conn, string $dir): void
{
    $parts   = explode('/', trim($dir, '/'));
    $current = '/';
    foreach ($parts as $part) {
        if (!$part) continue;
        $current .= $part . '/';
        @ftp_mkdir($conn, $current); // ignore "already exists" errors
    }
}
