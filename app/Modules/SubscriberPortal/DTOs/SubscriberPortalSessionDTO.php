<?php

namespace App\Modules\SubscriberPortal\DTOs;

class SubscriberPortalSessionDTO
{
    public int $user_id;
    public string $username;
    public string $role;
    public ?int $subscriber_id;

    public function __construct(array $data = [])
    {
        $this->user_id = isset($data['user_id'])
            ? (int)$data['user_id']
            : (int)($data['id'] ?? 0);

        $this->username = trim((string)($data['username'] ?? ''));

        $this->role = strtoupper(trim((string)($data['role'] ?? '')));

        $this->subscriber_id = isset($data['subscriber_id']) && (int)$data['subscriber_id'] > 0
            ? (int)$data['subscriber_id']
            : null;
    }

    public function isSubscriber(): bool
    {
        return $this->role === 'SUBSCRIBER';
    }

    public function isValid(): bool
    {
        return $this->user_id > 0
            && $this->username !== ''
            && $this->role !== '';
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'username' => $this->username,
            'role' => $this->role,
            'subscriber_id' => $this->subscriber_id,
        ];
    }
}
