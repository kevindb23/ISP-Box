<?php

namespace App\Modules\Dashboard\Entities;

class DashboardStats
{
    private array $attributes = [
        'subscribers' => 0,
        'active_services' => 0,
        'suspended_services' => 0,
        'unpaid_invoices' => 0,
        'unpaid_subscribers' => 0,
        'paid_invoices' => 0,
        'today_revenue' => 0.0,
        'active_sessions' => 0,
        'online_onts' => 0,
        'offline_onts' => 0,
        'available_nap_ports' => 0,
        'used_nap_ports' => 0,
        'provisioning_success' => 0,
        'provisioning_failed' => 0,
        'provisioning_in_progress' => 0,
    ];

    public function fill(array $data): self
    {
        foreach ($this->attributes as $key => $default) {
            if (array_key_exists($key, $data)) {
                $this->attributes[$key] = $data[$key];
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
