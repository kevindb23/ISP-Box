<?php

namespace App\Modules\Audit\Entities;

class AuditLog
{
    public int $id = 0;
    public int $userId = 0;
    public string $username = 'SYSTEM';
    public ?string $actorRole = null;
    public string $module = '';
    public string $action = '';
    public string $description = '';
    public ?string $ipAddress = null;
    public ?string $objectType = null;
    public ?int $objectId = null;
    public string $result = 'SUCCESS';
    public string $source = 'APPLICATION';
    public ?string $httpMethod = null;
    public ?string $route = null;
    public ?string $requestId = null;
    public ?string $metadataJson = null;
    public ?string $oldValuesJson = null;
    public ?string $newValuesJson = null;
    public string $createdAt = '';

    public function __construct(array $data = [])
    {
        $this->id = (int)($data['id'] ?? 0);
        $this->userId = (int)($data['user_id'] ?? 0);
        $this->username = (string)($data['username'] ?? 'SYSTEM');
        $this->actorRole = $data['actor_role'] ?? null;
        $this->module = (string)($data['module'] ?? '');
        $this->action = (string)($data['action'] ?? '');
        $this->description = (string)($data['description'] ?? '');
        $this->ipAddress = $data['ip_address'] ?? null;
        $this->objectType = $data['object_type'] ?? null;
        $this->objectId = isset($data['object_id']) ? (int)$data['object_id'] : null;
        $this->result = (string)($data['result'] ?? 'SUCCESS');
        $this->source = (string)($data['source'] ?? 'APPLICATION');
        $this->httpMethod = $data['http_method'] ?? null;
        $this->route = $data['route'] ?? null;
        $this->requestId = $data['request_id'] ?? null;
        $this->metadataJson = $data['metadata_json'] ?? null;
        $this->oldValuesJson = $data['old_values_json'] ?? null;
        $this->newValuesJson = $data['new_values_json'] ?? null;
        $this->createdAt = (string)($data['created_at'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'username' => $this->username,
            'actor_role' => $this->actorRole,
            'module' => $this->module,
            'action' => $this->action,
            'description' => $this->description,
            'ip_address' => $this->ipAddress,
            'object_type' => $this->objectType,
            'object_id' => $this->objectId,
            'result' => $this->result,
            'source' => $this->source,
            'http_method' => $this->httpMethod,
            'route' => $this->route,
            'request_id' => $this->requestId,
            'metadata' => $this->decode($this->metadataJson),
            'old_values' => $this->decode($this->oldValuesJson),
            'new_values' => $this->decode($this->newValuesJson),
            'created_at' => $this->createdAt,
        ];
    }

    private function decode(?string $json): array
    {
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
