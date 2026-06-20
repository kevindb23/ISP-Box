<?php

namespace App\Modules\Api\v1\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class ApiTokenRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Validate API Token
    |--------------------------------------------------------------------------
    */

    public function validate(string $token): bool
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM api_tokens
            WHERE token = :token
            AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");

        $stmt->execute([
            "token" => hash('sha256', $token)
        ]);

        return (bool) $stmt->fetch();
    }

    /*
    |--------------------------------------------------------------------------
    | Create API Token
    |--------------------------------------------------------------------------
    */

    public function create(int $userId, ?string $expiresAt = null): string
    {
        $rawToken = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare("
            INSERT INTO api_tokens (user_id, token, created_at, expires_at)
            VALUES (:user_id, :token, NOW(), :expires_at)
        ");

        $stmt->execute([
            "user_id" => $userId,
            "token" => hash('sha256', $rawToken),
            "expires_at" => $expiresAt
        ]);

        return $rawToken;
    }

    /*
    |--------------------------------------------------------------------------
    | Revoke Token
    |--------------------------------------------------------------------------
    */

    public function revoke(string $token): void
    {
        $stmt = $this->db->prepare("
            UPDATE api_tokens
            SET expires_at = NOW()
            WHERE token = :token
        ");

        $stmt->execute([
            "token" => hash('sha256', $token)
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Tokens for User
    |--------------------------------------------------------------------------
    */

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, created_at, expires_at
            FROM api_tokens
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            "user_id" => $userId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
