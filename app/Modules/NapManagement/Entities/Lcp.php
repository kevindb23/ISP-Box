<?php

namespace App\Modules\NapManagement\Entities;

class Lcp
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = [
            'id' => $data['id'] ?? null,
            'lcp_name' => $data['lcp_name'] ?? null,
            'olt_port_id' => $data['olt_port_id'] ?? null,
            'olt_port_path' => $data['olt_port_path'] ?? null,
            'olt_name' => $data['olt_name'] ?? null,
            'splitter_ports' => $data['splitter_ports'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => $data['status'] ?? 'ACTIVE',

            'maintenance_mode' => $data['maintenance_mode'] ?? 0,
            'maintenance_reason' => $data['maintenance_reason'] ?? null,
            'maintenance_resolution' => $data['maintenance_resolution'] ?? null,
            'maintenance_started_at' => $data['maintenance_started_at'] ?? null,
            'maintenance_ended_at' => $data['maintenance_ended_at'] ?? null,

            'created_at' => $data['created_at'] ?? null,
            'deleted_at' => $data['deleted_at'] ?? null,
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
