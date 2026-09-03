<?php

namespace App\Modules\BngManagement\Repositories;

use App\Infrastructure\Security\SecretCipher;
use App\Modules\BngManagement\Entities\AccelPppProfile;
use PDO;

final class AccelPppConfigRepository
{
    public function __construct(private PDO $db, private SecretCipher $secrets)
    {
    }

    public function get(bool $includeSecrets = false): ?array
    {
        $row = $this->db->query('SELECT * FROM bng_accel_profiles ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $config = json_decode((string)$row['config_json'], true) ?: [];
        $config['radius_secret_configured'] = !empty($row['radius_secret']);
        $config['dae_secret_configured'] = !empty($row['dae_secret']);
        if ($includeSecrets) {
            $config['radius_secret'] = $this->secrets->decrypt($row['radius_secret'] ?? null) ?? '';
            $config['dae_secret'] = $this->secrets->decrypt($row['dae_secret'] ?? null) ?? '';
        }
        unset($row['radius_secret'], $row['dae_secret'], $row['config_json']);
        return (new AccelPppProfile($row + ['config' => $config]))->toArray();
    }

    public function save(array $config, string $redactedRendered, string $configHash, int $userId): array
    {
        $existing = $this->get(true);
        $radiusSecret = trim((string)($config['radius_secret'] ?? '')) ?: (string)($existing['config']['radius_secret'] ?? '');
        $daeSecret = trim((string)($config['dae_secret'] ?? '')) ?: (string)($existing['config']['dae_secret'] ?? '');
        unset($config['radius_secret'], $config['dae_secret'], $config['radius_secret_configured'], $config['dae_secret_configured']);
        $params = [':json'=>json_encode($config, JSON_UNESCAPED_SLASHES), ':radius'=>$this->secrets->encrypt($radiusSecret), ':dae'=>$this->secrets->encrypt($daeSecret), ':rendered'=>$redactedRendered, ':hash'=>$configHash];
        if ($existing) {
            $params[':id']=(int)$existing['id'];
            $params[':user']=$userId;
            $stmt=$this->db->prepare("UPDATE bng_accel_profiles SET config_json=:json,radius_secret=:radius,dae_secret=:dae,rendered_config=:rendered,config_hash=:hash,status='DRAFT',restart_required=1,last_error=NULL,updated_by=:user WHERE id=:id");
        } else {
            $params[':user']=$userId;
            $stmt=$this->db->prepare("INSERT INTO bng_accel_profiles(config_json,radius_secret,dae_secret,rendered_config,config_hash,status,restart_required,created_by,updated_by) VALUES(:json,:radius,:dae,:rendered,:hash,'DRAFT',1,:user,:user)");
        }
        $stmt->execute($params);
        return $this->get() ?? [];
    }

    public function markStaged(int $id, int $userId): void
    {
        $stmt=$this->db->prepare("UPDATE bng_accel_profiles SET status='STAGED',staged_at=NOW(),staged_by=:user,last_error=NULL WHERE id=:id");
        $stmt->execute([':id'=>$id,':user'=>$userId]);
    }
    public function markValidated(int $id): void { $stmt=$this->db->prepare('UPDATE bng_accel_profiles SET last_validated_at=NOW() WHERE id=:id');$stmt->execute([':id'=>$id]); }
    public function markActive(int $id,int $userId): void { $stmt=$this->db->prepare("UPDATE bng_accel_profiles SET status='ACTIVE',activated_at=NOW(),activated_by=:user,restart_required=0,last_error=NULL WHERE id=:id");$stmt->execute([':id'=>$id,':user'=>$userId]); }

    public function markFailed(int $id, string $error): void
    {
        $stmt=$this->db->prepare("UPDATE bng_accel_profiles SET status='FAILED',last_error=:error WHERE id=:id");
        $stmt->execute([':id'=>$id,':error'=>$error]);
    }
    public function begin(): void { if(!$this->db->inTransaction())$this->db->beginTransaction(); }
    public function commit(): void { if($this->db->inTransaction())$this->db->commit(); }
    public function rollback(): void { if($this->db->inTransaction())$this->db->rollBack(); }

    public function createDeployment(int $profileId, string $type, string $status, string $hash, string $message, int $userId): int
    {
        $stmt=$this->db->prepare('INSERT INTO bng_accel_deployments(profile_id,deployment_type,status,config_hash,message,performed_by,completed_at) VALUES(:profile,:type,:status,:hash,:message,:user,NOW())');
        $stmt->execute([':profile'=>$profileId,':type'=>$type,':status'=>$status,':hash'=>$hash,':message'=>$message,':user'=>$userId]);
        return (int)$this->db->lastInsertId();
    }

    public function deployments(): array
    {
        return $this->db->query('SELECT d.*,u.full_name AS performed_by_name FROM bng_accel_deployments d LEFT JOIN users u ON u.id=d.performed_by ORDER BY d.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
