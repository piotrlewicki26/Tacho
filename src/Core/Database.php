<?php
declare(strict_types=1);
namespace Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO database wrapper – singleton per process.
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    throw $e;
                }
                throw new RuntimeException('Database connection failed.');
            }
        }
        return self::$instance;
    }

    /** Execute a query and return the PDOStatement. */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Return all rows. */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** Return a single row or null. */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Return a single column value or null. */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $val = self::query($sql, $params)->fetchColumn();
        return $val === false ? null : $val;
    }

    /** Insert a row and return the last insert ID. */
    public static function insert(string $table, array $data): int
    {
        $cols        = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => "`$c`", $cols)),
            implode(', ', $placeholders)
        );
        self::query($sql, $data);
        return (int) self::getInstance()->lastInsertId();
    }

    /** Update rows matching $where. Returns affected row count. */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = array_map(fn($c) => "`$c` = :set_$c", array_keys($data));
        $sql  = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);

        $params = [];
        foreach ($data as $k => $v) {
            $params['set_' . $k] = $v;
        }
        return self::query($sql, array_merge($params, $whereParams))->rowCount();
    }

    /** Delete rows matching $where. Returns affected row count. */
    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    public static function beginTransaction(): void  { self::getInstance()->beginTransaction(); }
    public static function commit(): void            { self::getInstance()->commit(); }
    public static function rollback(): void          { self::getInstance()->rollBack(); }
}
