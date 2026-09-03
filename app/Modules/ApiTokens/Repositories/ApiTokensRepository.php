<?php

namespace App\Modules\ApiTokens\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

final class ApiTokensRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, name, description, purpose, scopes, transport_policy, created_at, expires_at, '
            . 'last_used_at, last_used_ip, revoked_at FROM api_tokens WHERE user_id = :user_id ORDER BY id DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(int $userId, string $tokenHash, ?string $expiresAt, string $name,
        string $description, string $purpose, array $scopes, string $transportPolicy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO api_tokens (user_id, name, description, purpose, scopes, transport_policy, created_by, token, created_at, expires_at) '
            . 'VALUES (:user_id, :name, :description, :purpose, :scopes, :transport_policy, :created_by, :token, NOW(), :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token' => $tokenHash,
            'expires_at' => $expiresAt,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'purpose' => $purpose,
            'scopes' => json_encode(array_values($scopes), JSON_UNESCAPED_SLASHES),
            'transport_policy' => $transportPolicy,
            'created_by' => $userId,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function revokeForUser(int $tokenId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE api_tokens SET revoked_at = NOW() '
            . 'WHERE id = :id AND user_id = :user_id '
            . 'AND revoked_at IS NULL'
        );
        $stmt->execute(['id' => $tokenId, 'user_id' => $userId]);

        return $stmt->rowCount() === 1;
    }

    public function monitoringIdentity(): array
    {
        $keys=['instance_id','monitoring_client_id','monitoring_client_name','monitoring_instance_name','monitoring_location','monitoring_environment'];
        $stmt=$this->db->prepare('SELECT config_key,config_value FROM system_config WHERE config_key IN (?,?,?,?,?,?)');
        $stmt->execute($keys); $values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$values[(string)$row['config_key']]=(string)$row['config_value'];
        return $values;
    }

    public function saveMonitoringIdentity(array $values): void
    {
        $stmt=$this->db->prepare('INSERT INTO system_config(config_key,config_value,description) VALUES(:key,:value,:description) ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)');
        foreach($values as $key=>$value)$stmt->execute(['key'=>$key,'value'=>$value,'description'=>'HQ infrastructure monitoring identity.']);
    }

    public function monitoringSettings(): array
    {
        $keys = ['monitoring_hq_enabled','monitoring_hq_url','monitoring_allow_insecure_http','monitoring_verify_tls','monitoring_retention_days','monitoring_disk_warning_percent','monitoring_disk_critical_percent','monitoring_memory_warning_percent','monitoring_heartbeat_seconds'];
        $quoted = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare("SELECT config_key,config_value FROM system_config WHERE config_key IN ({$quoted})");
        $stmt->execute($keys); $values = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $values[(string) $row['config_key']] = (string) $row['config_value'];
        return $values;
    }

    public function saveMonitoringSettings(array $values): void
    {
        $stmt = $this->db->prepare('INSERT INTO system_config(config_key,config_value,description) VALUES(:key,:value,:description) ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)');
        foreach ($values as $key => $value) $stmt->execute(['key' => $key, 'value' => (string) $value, 'description' => 'Infrastructure monitoring operational setting.']);
    }
}
