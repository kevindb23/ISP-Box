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

        // Subscriber portal accounts historically could retain a stale
        // users.username after the subscriber email changed. Resolve the
        // linked subscriber email as a compatibility path while the account
        // is repaired by the next portal-password reset/update.
        if (!$row) {
            $stmt = $this->db->prepare(
                "SELECT u.*
                 FROM users u
                 INNER JOIN subscribers s
                    ON s.user_id = u.id
                   AND s.deleted_at IS NULL
                 WHERE u.role = 'SUBSCRIBER'
                   AND s.email = :subscriber_email
                 LIMIT 1"
            );

            $stmt->execute([
                'subscriber_email' => $username,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

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
