<?php
declare(strict_types=1);

namespace LicenseGenerator;

use PDO;
use PDOException;

/**
 * SQLite database wrapper.
 *
 * Creates the database file and initialises the schema on first use.
 */
class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("Nie można utworzyć katalogu bazy danych: {$dir}");
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->initSchema();
    }

    // -----------------------------------------------------------------------
    // Public interface
    // -----------------------------------------------------------------------

    public function prepare(string $sql): \PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function exec(string $sql): int|false
    {
        return $this->pdo->exec($sql);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // -----------------------------------------------------------------------
    // Schema initialisation
    // -----------------------------------------------------------------------

    private function initSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT    UNIQUE NOT NULL,
                password_hash TEXT    NOT NULL,
                created_at    TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime')),
                last_login    TEXT
            );

            CREATE TABLE IF NOT EXISTS licenses (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id     TEXT    NOT NULL,
                company_name   TEXT    NOT NULL,
                license_key    TEXT    UNIQUE NOT NULL,
                sha256_hash    TEXT    NOT NULL,
                modules        TEXT    NOT NULL DEFAULT '[\"all\"]',
                max_operators  INTEGER NOT NULL DEFAULT 5,
                max_drivers    INTEGER NOT NULL DEFAULT 50,
                valid_from     TEXT    NOT NULL,
                valid_to       TEXT    NOT NULL,
                hardware_id    TEXT    NOT NULL DEFAULT '',
                is_active      INTEGER NOT NULL DEFAULT 1,
                notes          TEXT    NOT NULL DEFAULT '',
                created_by     INTEGER,
                created_at     TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime')),
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            );

            CREATE INDEX IF NOT EXISTS idx_licenses_company_id  ON licenses (company_id);
            CREATE INDEX IF NOT EXISTS idx_licenses_valid_to    ON licenses (valid_to);
            CREATE INDEX IF NOT EXISTS idx_licenses_is_active   ON licenses (is_active);
        ");

        // Migration: add used_secret column if it doesn't already exist.
        // Check via PRAGMA table_info to avoid relying on locale-specific error messages.
        $columns = $this->pdo->query("PRAGMA table_info(licenses)")->fetchAll(PDO::FETCH_ASSOC);
        $hasUsedSecret = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'used_secret') {
                $hasUsedSecret = true;
                break;
            }
        }
        if (!$hasUsedSecret) {
            $this->pdo->exec(
                "ALTER TABLE licenses ADD COLUMN used_secret TEXT NOT NULL DEFAULT ''"
            );
        }
    }
}
