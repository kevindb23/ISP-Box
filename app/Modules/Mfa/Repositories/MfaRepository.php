<?php

namespace App\Modules\Mfa\Repositories;

use Framework\DatabaseConnection;
use PDO;

final class MfaRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function usersWithMfa(): array
    {
        return $this->db->query(<<<'SQL'
            SELECT u.id, u.username, u.full_name, u.email, u.role, u.status,
                   COALESCE(m.enabled, 0) AS mfa_enabled,
                   COALESCE(m.method, 'AUTHENTICATOR') AS mfa_method,
                   m.verified_at
            FROM users u
            LEFT JOIN user_mfa m ON m.user_id = u.id
            ORDER BY u.id DESC
        SQL)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function user(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, full_name, email, role, status FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function settings(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_mfa WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function savePendingEnrollment(int $userId, string $method, string $encryptedSecret): void
    {
        $stmt = $this->db->prepare(<<<'SQL'
            INSERT INTO user_mfa (
                user_id, enabled, method, secret_encrypted, verified_at,
                pending_method, pending_secret_encrypted, pending_started_at
            )
            VALUES (
                :user_id, 0, 'AUTHENTICATOR', NULL, NULL,
                :method, :secret, NOW()
            )
            ON DUPLICATE KEY UPDATE
                pending_method = VALUES(pending_method),
                pending_secret_encrypted = VALUES(pending_secret_encrypted),
                pending_started_at = VALUES(pending_started_at)
        SQL);
        $stmt->execute(['user_id' => $userId, 'method' => $method, 'secret' => $encryptedSecret]);
    }

    public function enable(int $userId): void
    {
        $stmt = $this->db->prepare(<<<'SQL'
            UPDATE user_mfa
            SET method = pending_method,
                secret_encrypted = pending_secret_encrypted,
                enabled = 1,
                verified_at = NOW(),
                pending_method = NULL,
                pending_secret_encrypted = NULL,
                pending_started_at = NULL
            WHERE user_id = :user_id
        SQL);
        $stmt->execute(['user_id' => $userId]);
    }

    public function disable(int $userId): void
    {
        $stmt = $this->db->prepare(<<<'SQL'
            UPDATE user_mfa
            SET enabled = 0,
                verified_at = NULL,
                pending_method = NULL,
                pending_secret_encrypted = NULL,
                pending_started_at = NULL
            WHERE user_id = :user_id
        SQL);
        $stmt->execute(['user_id' => $userId]);
    }

    public function reset(int $userId): void
    {
        $stmt = $this->db->prepare(<<<'SQL'
            UPDATE user_mfa
            SET enabled = 0,
                method = 'AUTHENTICATOR',
                secret_encrypted = NULL,
                verified_at = NULL,
                pending_method = NULL,
                pending_secret_encrypted = NULL,
                pending_started_at = NULL
            WHERE user_id = :user_id
        SQL);
        $stmt->execute(['user_id' => $userId]);

        $stmt = $this->db->prepare('DELETE FROM mfa_challenges WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $stmt->execute(['password' => $passwordHash, 'id' => $userId]);
    }

    public function createChallenge(array $data): int
    {
        $stmt = $this->db->prepare(<<<'SQL'
            INSERT INTO mfa_challenges
                (user_id, purpose, method, challenge_token_hash, code_hash, expires_at, max_attempts, ip_address)
            VALUES
                (:user_id, :purpose, :method, :token_hash, :code_hash, :expires_at, 5, :ip_address)
        SQL);
        $stmt->execute([
            'user_id' => $data['user_id'], 'purpose' => $data['purpose'], 'method' => $data['method'],
            'token_hash' => $data['token_hash'], 'code_hash' => $data['code_hash'] ?? null,
            'expires_at' => $data['expires_at'], 'ip_address' => $data['ip_address'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function challengeByToken(string $tokenHash, string $purpose): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM mfa_challenges WHERE challenge_token_hash = :token_hash AND purpose = :purpose LIMIT 1');
        $stmt->execute(['token_hash' => $tokenHash, 'purpose' => $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function registerFailedAttempt(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE mfa_challenges SET attempts = attempts + 1 WHERE id = :id AND consumed_at IS NULL');
        $stmt->execute(['id' => $id]);
    }

    public function verifyChallenge(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE mfa_challenges SET verified_at = NOW() WHERE id = :id AND consumed_at IS NULL');
        $stmt->execute(['id' => $id]);
    }

    public function consumeChallenge(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE mfa_challenges SET consumed_at = NOW() WHERE id = :id AND consumed_at IS NULL');
        $stmt->execute(['id' => $id]);
    }
}
