<?php
declare(strict_types=1);
namespace Models;

use Core\Database;

class Vehicle
{
    public function allForCompany(int $companyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM vehicles WHERE company_id = :cid ORDER BY registration',
            ['cid' => $companyId]
        );
    }

    public function find(int $id, int $companyId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM vehicles WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
    }

    public function create(int $companyId, array $data): int
    {
        $row = $this->sanitize($data);
        $row['company_id'] = $companyId;
        return Database::insert('vehicles', $row);
    }

    public function update(int $id, int $companyId, array $data): void
    {
        $row = $this->sanitize($data);
        if ($row) {
            Database::update('vehicles', $row, 'id = :id AND company_id = :cid', ['id' => $id, 'cid' => $companyId]);
        }
    }

    public function delete(int $id, int $companyId): void
    {
        Database::update('vehicles', ['is_active' => 0], 'id = :id AND company_id = :cid', ['id' => $id, 'cid' => $companyId]);
    }

    private function sanitize(array $d): array
    {
        $allowed = ['registration','brand','model','year','vin','tachograph_serial','tachograph_type','notes','is_active'];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $d)) $out[$k] = $d[$k] ?: null;
        }
        return $out;
    }
}
