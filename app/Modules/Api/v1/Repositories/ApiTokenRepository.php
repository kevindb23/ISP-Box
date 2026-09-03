<?php

namespace App\Modules\Api\v1\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use Throwable;

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

    public function findIdentity(string $token, string $transport = 'HTTP'): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                api_tokens.id AS token_id,
                api_tokens.name AS token_name,
                api_tokens.purpose,
                api_tokens.scopes,
                api_tokens.transport_policy,
                users.id AS user_id,
                users.role,
                users.status
            FROM api_tokens
            INNER JOIN users ON users.id = api_tokens.user_id
            WHERE api_tokens.token = :token
            AND (api_tokens.transport_policy = 'BOTH' OR api_tokens.transport_policy = :transport)
            AND (api_tokens.expires_at IS NULL OR api_tokens.expires_at > NOW())
            AND api_tokens.revoked_at IS NULL
            AND UPPER(COALESCE(users.status, 'ACTIVE')) = 'ACTIVE'
            LIMIT 1
        ");

        $stmt->execute([
            'token' => hash('sha256', $token),
            'transport' => strtoupper($transport) === 'HTTPS' ? 'HTTPS' : 'HTTP',
        ]);

        $identity = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$identity) return null;

        $decodedScopes = json_decode((string)($identity['scopes'] ?? '[]'), true);
        $identity['scopes'] = is_array($decodedScopes) ? array_values($decodedScopes) : [];
        $identity['source'] = 'BEARER_TOKEN';

        try {
            $touch = $this->db->prepare(
                'UPDATE api_tokens SET last_used_at = NOW(), last_used_ip = :ip WHERE id = :id'
            );
            $touch->execute([
                ':ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: null,
                ':id' => (int)$identity['token_id'],
            ]);
        } catch (Throwable $ignored) {
            // Authentication must remain available if usage telemetry cannot be updated.
        }

        return $identity;
    }

}
