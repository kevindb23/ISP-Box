<?php

namespace App\Modules\NapManagement\Entities;

class Nap
{
    private array $data;

    public function __construct(array $data = [])
    {
        $parentType = strtoupper((string)($data['parent_type'] ?? 'LCP'));

        $this->data = [
            'id' => $data['id'] ?? null,
            'nap_name' => $data['nap_name'] ?? null,

            // legacy fields
            'lcp_id' => $data['lcp_id'] ?? null,
            'olt_id' => $data['olt_id'] ?? null,
            'olt_port_id' => $data['olt_port_id'] ?? null,

            // hybrid parent fields
            'parent_type' => $parentType,
            'parent_lcp_id' => $data['parent_lcp_id'] ?? null,
            'parent_nap_id' => $data['parent_nap_id'] ?? null,
            'parent_port_id' => $data['parent_port_id'] ?? null,

            'parent_name' => $data['parent_name'] ?? null,
            'parent_port' => $data['parent_port'] ?? null,

            'lcp_name' => $data['lcp_name'] ?? null,
            'lcp_port_id' => $data['lcp_port_id'] ?? null,
            'lcp_port' => $data['lcp_port'] ?? null,

            'splitter_ports' => $data['splitter_ports'] ?? null,
            'location' => $data['location'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'description' => $data['description'] ?? null,
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
