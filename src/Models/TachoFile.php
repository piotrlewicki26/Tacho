<?php
declare(strict_types=1);
namespace Models;

use Core\Database;

class TachoFile
{
    public function allForCompany(int $companyId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT tf.*, CONCAT(d.first_name," ",d.last_name) AS driver_name, v.registration
             FROM tacho_files tf
             LEFT JOIN drivers d ON d.id = tf.driver_id
             LEFT JOIN vehicles v ON v.id = tf.vehicle_id
             WHERE tf.company_id = :cid
             ORDER BY tf.created_at DESC LIMIT ' . (int)$limit,
            ['cid' => $companyId]
        );
    }

    public function find(int $id, int $companyId): ?array
    {
        return Database::fetchOne(
            'SELECT tf.*, CONCAT(d.first_name," ",d.last_name) AS driver_name, v.registration
             FROM tacho_files tf
             LEFT JOIN drivers d ON d.id = tf.driver_id
             LEFT JOIN vehicles v ON v.id = tf.vehicle_id
             WHERE tf.id = :id AND tf.company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
    }

    public function create(array $data): int
    {
        return Database::insert('tacho_files', $data);
    }

    public function updateStatus(int $id, string $status, ?string $error = null): void
    {
        Database::update('tacho_files', [
            'parse_status' => $status,
            'parse_error'  => $error,
            'parsed_at'    => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }
}
