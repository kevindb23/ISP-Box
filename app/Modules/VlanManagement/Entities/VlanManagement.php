<?php

namespace App\Modules\VlanManagement\Entities;

class VlanManagement
{
    public ?int $id = null;

    public ?int $vlan_id = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $created_at = null;

    public ?int $service_id = null;
    public ?string $status = null;
    public ?int $olt_port_id = null;
    public ?string $reserved_at = null;
    public ?string $used_at = null;

    public ?string $service_status = null;
    public ?string $service_number = null;
    public ?string $ppp_username = null;
    public ?string $subscriber_name = null;
    public ?string $olt_port_label = null;

    public function __construct(array $data = [])
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;

        $this->vlan_id = isset($data['vlan_id']) ? (int)$data['vlan_id'] : null;
        $this->name = $data['name'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->created_at = $data['created_at'] ?? null;

        $this->service_id = isset($data['service_id']) ? (int)$data['service_id'] : null;
        $this->status = $data['status'] ?? null;
        $this->olt_port_id = isset($data['olt_port_id']) && $data['olt_port_id'] !== null ? (int)$data['olt_port_id'] : null;
        $this->reserved_at = $data['reserved_at'] ?? null;
        $this->used_at = $data['used_at'] ?? null;

        $this->service_status = $data['service_status'] ?? null;
        $this->service_number = isset($data['service_number']) ? (string)$data['service_number'] : null;
        $this->ppp_username = $data['ppp_username'] ?? null;
        $this->subscriber_name = $data['subscriber_name'] ?? null;
        $this->olt_port_label = $data['olt_port_label'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vlan_id' => $this->vlan_id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'service_id' => $this->service_id,
            'status' => $this->status,
            'olt_port_id' => $this->olt_port_id,
            'reserved_at' => $this->reserved_at,
            'used_at' => $this->used_at,
            'service_status' => $this->service_status,
            'service_number' => $this->service_number,
            'ppp_username' => $this->ppp_username,
            'subscriber_name' => $this->subscriber_name,
            'olt_port_label' => $this->olt_port_label,
        ];
    }
}