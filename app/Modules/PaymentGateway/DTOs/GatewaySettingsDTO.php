<?php

namespace App\Modules\PaymentGateway\DTOs;

final class GatewaySettingsDTO
{
    public function __construct(
        public ?bool $enabled = null,
        public ?string $mode = null,
        public ?string $publicKey = null,
        public ?string $secretKey = null,
        public ?string $webhookSecret = null
    ) {
        $this->mode = $this->mode !== null ? strtolower(trim($this->mode)) : null;
        $this->publicKey = $this->normalize($this->publicKey);
        $this->secretKey = $this->normalize($this->secretKey);
        $this->webhookSecret = $this->normalize($this->webhookSecret);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            enabled: array_key_exists('paymongo_enabled', $data)
                ? filter_var($data['paymongo_enabled'], FILTER_VALIDATE_BOOLEAN)
                : null,
            mode: array_key_exists('paymongo_mode', $data) ? (string)$data['paymongo_mode'] : null,
            publicKey: array_key_exists('paymongo_public_key', $data) ? (string)$data['paymongo_public_key'] : null,
            secretKey: array_key_exists('paymongo_secret_key', $data) ? (string)$data['paymongo_secret_key'] : null,
            webhookSecret: array_key_exists('paymongo_webhook_secret', $data) ? (string)$data['paymongo_webhook_secret'] : null
        );
    }

    public function toPersistenceArray(): array
    {
        $data = [];
        if ($this->enabled !== null) $data['paymongo_enabled'] = $this->enabled ? '1' : '0';
        if ($this->mode !== null) $data['paymongo_mode'] = $this->mode;
        if ($this->publicKey !== null) $data['paymongo_public_key'] = $this->publicKey;
        if ($this->secretKey !== null && $this->secretKey !== '') $data['paymongo_secret_key'] = $this->secretKey;
        if ($this->webhookSecret !== null && $this->webhookSecret !== '') $data['paymongo_webhook_secret'] = $this->webhookSecret;
        return $data;
    }

    public function toAuditArray(): array
    {
        $data = $this->toPersistenceArray();
        if (isset($data['paymongo_secret_key'])) $data['paymongo_secret_key'] = '[REDACTED]';
        if (isset($data['paymongo_webhook_secret'])) $data['paymongo_webhook_secret'] = '[REDACTED]';
        return $data;
    }

    private function normalize(?string $value): ?string
    {
        return $value !== null ? trim($value) : null;
    }
}
