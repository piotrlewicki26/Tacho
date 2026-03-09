<?php
declare(strict_types=1);
namespace Models;

use Core\Database;

class Driver
{
    public function allForCompany(int $companyId): array
    {
        return Database::fetchAll(
            'SELECT d.*,
                    (SELECT COUNT(*) FROM tacho_files tf WHERE tf.driver_id = d.id) AS file_count,
                    (SELECT COUNT(*) FROM violations v WHERE v.driver_id = d.id) AS violation_count
             FROM drivers d
             WHERE d.company_id = :cid
             ORDER BY d.last_name, d.first_name',
            ['cid' => $companyId]
        );
    }

    public function find(int $id, int $companyId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM drivers WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
    }

    public function create(int $companyId, array $data): int
    {
        $row = $this->sanitize($data);
        $row['company_id'] = $companyId;
        return Database::insert('drivers', $row);
    }

    public function update(int $id, int $companyId, array $data): void
    {
        $row = $this->sanitize($data);
        if ($row) {
            Database::update('drivers', $row, 'id = :id AND company_id = :cid', ['id' => $id, 'cid' => $companyId]);
        }
    }

    public function delete(int $id, int $companyId): void
    {
        Database::update('drivers', ['is_active' => 0], 'id = :id AND company_id = :cid', ['id' => $id, 'cid' => $companyId]);
    }

    private function sanitize(array $d): array
    {
        $allowed = ['first_name','last_name','birth_date','license_number','card_number','card_expiry','nationality','phone','email','notes','is_active'];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $d)) $out[$k] = $d[$k] ?: null;
        }
        return $out;
    }
}
