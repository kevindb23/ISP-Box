<?php

namespace App\Modules\Users\Repositories;

use Framework\DatabaseConnection;
use PDO;

class UsersRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $database)
    {
        $this->db = $database->get();
    }

    public function all(): array
    {
        $sql = "
            SELECT
                id,
                username,
                full_name,
                email,
                role,
                status,
                last_login,
                created_at,
                updated_at
            FROM users
            WHERE role <> 'SUBSCRIBER'
            ORDER BY id DESC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                username,
                full_name,
                email,
                role,
                status,
                last_login,
                created_at,
                updated_at
            FROM users
            WHERE id = :id
              AND role <> 'SUBSCRIBER'
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql = "SELECT id FROM users WHERE username = :username";
        $params = ['username' => $username];

        if ($exceptId !== null) {
            $sql .= " AND id <> :id";
            $params['id'] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users
                (username, full_name, email, password, role, status)
            VALUES
                (:username, :full_name, :email, :password, :role, :status)
        ");

        $stmt->execute([
            'username'  => $data['username'],
            'full_name' => $data['full_name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'role'      => $data['role'],
            'status'    => $data['status'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET
                username = :username,
                full_name = :full_name,
                email = :email,
                role = :role,
                status = :status
            WHERE id = :id
              AND role <> 'SUBSCRIBER'
        ");

        $stmt->execute([
            'id'        => $id,
            'username'  => $data['username'],
            'full_name' => $data['full_name'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'status'    => $data['status'],
        ]);
    }

    public function disable(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET status = 'DISABLED'
            WHERE id = :id
              AND role <> 'SUBSCRIBER'
        ");

        $stmt->execute(['id' => $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET password = :password
            WHERE id = :id
              AND role <> 'SUBSCRIBER'
        ");

        $stmt->execute([
            'id' => $id,
            'password' => $passwordHash,
        ]);
    }
}