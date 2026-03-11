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

    /**
     * Delete a file record, its activities, violations, and the physical file on disk.
     * Only deletes if the file belongs to the given company (ownership check).
     */
    public function delete(int $id, int $companyId): bool
    {
        $row = Database::fetchOne(
            'SELECT stored_name FROM tacho_files WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$row) return false;

        // Remove physical file
        $path = UPLOAD_PATH . $row['stored_name'];
        if (is_file($path) && !unlink($path)) {
            error_log('TachoFile::delete – could not delete file: ' . $path);
        }

        // Remove DB records (activities cascade via FK or explicit delete)
        Database::delete('activities',  'tacho_file_id = :fid', ['fid' => $id]);
        Database::delete('violations',  'tacho_file_id = :fid', ['fid' => $id]);
        Database::delete('tacho_files', 'id = :id',             ['id'  => $id]);

        return true;
    }
}
