<?php

namespace App\Modules\Auth\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use App\Modules\Auth\Entities\AuthenticatedUser;
use PDO;

class AdminRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function findByUsername(string $username): ?AuthenticatedUser
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE username = :username LIMIT 1"
        );

        $stmt->execute([
            'username' => $username
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new AuthenticatedUser($row) : null;
    }

    public function findById(int $id): ?AuthenticatedUser
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new AuthenticatedUser($row) : null;
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $stmt->execute(['id' => $id, 'password' => $passwordHash]);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET last_login = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);
    }
}
