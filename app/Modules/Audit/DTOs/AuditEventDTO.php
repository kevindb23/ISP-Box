<?php

namespace App\Modules\Audit\DTOs;

class AuditEventDTO
{
    public function __construct(
        public string $module,
        public string $action,
        public string $description = '',
        public int $userId = 0,
        public string $username = 'SYSTEM',
        public ?string $actorRole = null,
        public ?string $ipAddress = null,
        public ?string $objectType = null,
        public ?int $objectId = null,
        public string $result = 'SUCCESS',
        public string $source = 'APPLICATION',
        public ?string $httpMethod = null,
        public ?string $route = null,
        public ?string $requestId = null,
        public array $metadata = [],
        public array $oldValues = [],
        public array $newValues = []
    ) {
        $this->module = strtoupper(trim($this->module));
        $this->action = strtoupper(trim($this->action));
        $this->result = strtoupper(trim($this->result));
        $this->source = strtoupper(trim($this->source));
        $this->username = trim($this->username) ?: 'SYSTEM';
        $this->actorRole = $this->actorRole !== null
            ? strtoupper(trim($this->actorRole))
            : null;
        $this->httpMethod = $this->httpMethod !== null
            ? strtoupper(trim($this->httpMethod))
            : null;
    }

    public function toArray(): array
    {
        return [
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
            'metadata_json' => $this->encode($this->metadata),
            'old_values_json' => $this->encode($this->oldValues),
            'new_values_json' => $this->encode($this->newValues),
        ];
    }

    private function encode(array $value): ?string
    {
        if ($value === []) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }
}
