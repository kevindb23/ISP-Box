<?php

namespace App\Modules\Subscribers\DTOs;

class UpdateSubscriberDTO
{
    public string $full_name;
    public ?string $address;
    public ?string $contact_number;
    public ?string $email;
    public int $plan_id;

    public function __construct(array $data)
    {
        $this->full_name      = trim((string)($data['full_name'] ?? ''));
        $this->address        = trim((string)($data['address'] ?? '')) ?: null;
        $this->contact_number = trim((string)($data['contact_number'] ?? '')) ?: null;
        $this->email          = trim((string)($data['email'] ?? '')) ?: null;
        $this->plan_id        = (int)($data['plan_id'] ?? 0);
    }

    public function toArray(): array
    {
        return [
            'full_name'      => $this->full_name,
            'address'        => $this->address,
            'contact_number' => $this->contact_number,
            'email'          => $this->email,
            'plan_id'        => $this->plan_id,
        ];
    }
}
