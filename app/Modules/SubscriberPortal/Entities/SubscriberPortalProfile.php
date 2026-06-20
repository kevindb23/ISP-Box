<?php

namespace App\Modules\SubscriberPortal\Entities;

class SubscriberPortalProfile
{
    public int $id;
    public ?int $user_id;
    public ?int $account_number;
    public string $full_name;
    public ?string $email;
    public ?string $contact_number;
    public ?string $address;
    public string $status;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct(array $data = [])
    {
        $this->id = (int)($data['id'] ?? $data['subscriber_id'] ?? 0);
        $this->user_id = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $this->account_number = isset($data['account_number']) ? (int)$data['account_number'] : null;
        $this->full_name = (string)($data['full_name'] ?? $data['subscriber_name'] ?? '');
        $this->email = $data['email'] ?? null;
        $this->contact_number = $data['contact_number'] ?? null;
        $this->address = $data['address'] ?? null;
        $this->status = strtoupper((string)($data['status'] ?? 'ACTIVE'));
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subscriber_id' => $this->id,
            'user_id' => $this->user_id,
            'account_number' => $this->account_number,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
