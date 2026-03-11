<?php
declare(strict_types=1);
namespace Models;

use Core\Database;

class Company
{
    public function all(): array
    {
        return Database::fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM drivers d WHERE d.company_id = c.id AND d.is_active=1) AS driver_count,
                    (SELECT COUNT(*) FROM vehicles v WHERE v.company_id = c.id AND v.is_active=1) AS vehicle_count
             FROM companies c ORDER BY c.name'
        );
    }

    public function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM companies WHERE id = :id', ['id' => $id]);
    }

    public function create(array $data): int
    {
        return Database::insert('companies', $this->sanitize($data));
    }

    public function update(int $id, array $data): void
    {
        Database::update('companies', $this->sanitize($data), 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): void
    {
        Database::delete('companies', 'id = :id', ['id' => $id]);
    }

    private function sanitize(array $d): array
    {
        $allowed = ['name','nip','address','city','country','phone','email','logo','license_secret'];
        return array_intersect_key($d, array_flip($allowed));
    }
}
