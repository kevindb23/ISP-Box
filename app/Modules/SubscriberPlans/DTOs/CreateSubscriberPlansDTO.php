<?php

namespace App\Modules\SubscriberPlans\DTOs;

class CreateSubscriberPlansDTO
{
    public string $plan_name;
    public float $price;
    public ?string $description;
    public string $plan_type;
    public int $validity_days;
    public int $speed_mbps;
    public int $is_active;

    public function __construct(array $data)
    {
        $this->plan_name     = trim((string)($data['plan_name'] ?? ''));
        $this->price         = (float)($data['price'] ?? 0);
        $this->description   = trim((string)($data['description'] ?? ''));

        $this->plan_type     = strtoupper(trim((string)($data['plan_type'] ?? 'POSTPAID')));
        $this->validity_days = (int)($data['validity_days'] ?? 30);
        $this->speed_mbps    = (int)($data['speed'] ?? $data['speed_mbps'] ?? 0);
        $this->is_active     = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if ($this->description === '') {
            $this->description = null;
        }

        if ($this->plan_type === 'POSTPAID') {
            $this->validity_days = 30;
        }
    }

    public function toArray(): array
    {
        return [
            'plan_name'     => $this->plan_name,
            'price'         => $this->price,
            'description'   => $this->description,
            'plan_type'     => $this->plan_type,
            'validity_days' => $this->validity_days,
            'speed_mbps'    => $this->speed_mbps,
            'is_active'     => $this->is_active,
        ];
    }
}
