<?php

namespace App\Modules\SubscriberPlans\SystemSettings\Entities;

class SystemConfig
{
    public string $config_key;
    public string $config_value;

    public function __construct(array $data = [])
    {
        $this->config_key   = $data['config_key'] ?? '';
        $this->config_value = $data['config_value'] ?? '';
    }
}