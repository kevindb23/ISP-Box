<?php

namespace App\Modules\NapManagement\Entities;

class NapManagement
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
            'status' => $data['status'] ?? null,
            'created_at' => $data['created_at'] ?? null,
            'deleted_at' => $data['deleted_at'] ?? null,

            'nap_name' => $data['nap_name'] ?? null,
            'lcp_id' => $data['lcp_id'] ?? null,
            'lcp_port_id' => $data['lcp_port_id'] ?? null,
            'lcp_port' => $data['lcp_port'] ?? null,

            'port_number' => $data['port_number'] ?? null,
            'nap_id' => $data['nap_id'] ?? null,
            'splitter_id' => $data['splitter_id'] ?? null,
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
