<?php

namespace App\Modules\ApiTokens\Entities;

final class ApiTokens
{
    public function __construct(private array $attributes)
    {
        unset($this->attributes['token']);
        if (isset($this->attributes['id'])) $this->attributes['id'] = (int)$this->attributes['id'];
        if (isset($this->attributes['user_id'])) $this->attributes['user_id'] = (int)$this->attributes['user_id'];
        $scopes = json_decode((string)($this->attributes['scopes'] ?? '[]'), true);
        $this->attributes['scopes'] = is_array($scopes) ? array_values($scopes) : [];
        $this->attributes['status'] = !empty($this->attributes['revoked_at'])
            ? 'REVOKED'
            : ((!empty($this->attributes['expires_at']) && strtotime((string)$this->attributes['expires_at']) <= time()) ? 'EXPIRED' : 'ACTIVE');
    }

    public function toArray(): array { return $this->attributes; }
}
