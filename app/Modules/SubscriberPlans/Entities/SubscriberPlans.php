<?php

namespace App\Modules\SubscriberPlans\Entities;

class SubscriberPlans
{
    public ?int $id;
    public ?string $plan_name;
    public ?float $price;
    public ?string $description;
    public string $plan_type;
    public int $validity_days;
    public int $speed_down;
    public int $speed_up;
    public int $is_active;
    public int $speed_mbps;

    public function __construct(array $data = [])
    {
        $this->id            = isset($data['id']) ? (int)$data['id'] : null;
        $this->plan_name     = isset($data['plan_name']) ? (string)$data['plan_name'] : null;
        $this->price         = isset($data['price']) ? (float)$data['price'] : null;
        $this->description   = isset($data['description']) ? (string)$data['description'] : null;
        $this->plan_type     = isset($data['plan_type']) ? (string)$data['plan_type'] : 'POSTPAID';
        $this->validity_days = isset($data['validity_days']) ? (int)$data['validity_days'] : 30;
        $this->speed_down    = isset($data['speed_down']) ? (int)$data['speed_down'] : 0;
        $this->speed_up      = isset($data['speed_up']) ? (int)$data['speed_up'] : 0;
        $this->is_active     = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $this->speed_mbps    = isset($data['speed_mbps']) ? (int)$data['speed_mbps'] : 0;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'plan_name'     => $this->plan_name,
            'price'         => $this->price,
            'description'   => $this->description,
            'plan_type'     => $this->plan_type,
            'validity_days' => $this->validity_days,
            'speed_down'    => $this->speed_down,
            'speed_up'      => $this->speed_up,
            'is_active'     => $this->is_active,
            'speed_mbps'    => $this->speed_mbps,
        ];
    }
}
