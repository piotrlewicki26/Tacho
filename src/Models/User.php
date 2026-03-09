<?php
declare(strict_types=1);
namespace Models;

use Core\Database;

class User
{
    public function findByEmail(string $email): ?array
    {
        return Database::fetchOne('SELECT * FROM users WHERE email = :e LIMIT 1', ['e' => $email]);
    }

    public function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    public function allForCompany(?int $companyId): array
    {
        if ($companyId === null) {
            return Database::fetchAll(
                'SELECT u.*, c.name AS company_name FROM users u
                 LEFT JOIN companies c ON c.id = u.company_id ORDER BY u.name'
            );
        }
        return Database::fetchAll(
            'SELECT * FROM users WHERE company_id = :cid ORDER BY name',
            ['cid' => $companyId]
        );
    }

    public function create(array $data): int
    {
        $row = $this->sanitize($data);
        if (!empty($data['password'])) {
            $row['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        return Database::insert('users', $row);
    }

    public function update(int $id, array $data): void
    {
        $row = $this->sanitize($data);
        if (!empty($data['password'])) {
            $row['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        if ($row) {
            Database::update('users', $row, 'id = :id', ['id' => $id]);
        }
    }

    public function toggleActive(int $id): void
    {
        Database::query('UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id = :id', ['id' => $id]);
    }

    public function delete(int $id): void
    {
        Database::delete('users', 'id = :id', ['id' => $id]);
    }

    private function sanitize(array $d): array
    {
        $allowed = ['company_id','name','email','role','is_active'];
        return array_filter(array_intersect_key($d, array_flip($allowed)), fn($v) => $v !== null && $v !== '');
    }
}
