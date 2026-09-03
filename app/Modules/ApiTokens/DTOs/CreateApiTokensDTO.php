<?php

namespace App\Modules\ApiTokens\DTOs;

final class CreateApiTokensDTO
{
    public function __construct(
        public int $userId,
        public ?string $expiresAt = null,
        public string $name = '',
        public string $description = '',
        public string $purpose = 'CUSTOM',
        public array $scopes = [],
        public string $transportPolicy = 'HTTPS'
    ) {
        $this->expiresAt = trim((string)$this->expiresAt) ?: null;
        $this->name = trim($this->name);
        $this->description = trim($this->description);
        $this->purpose = strtoupper(trim($this->purpose));
        $this->transportPolicy = strtoupper(trim($this->transportPolicy));
        $this->scopes = array_values(array_unique(array_filter(array_map('strval', $this->scopes))));
    }
}
