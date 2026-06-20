<?php

namespace App\Modules\VlanManagement\DTOs;

class CreateVlanPoolEntryDTO
{
    public int $service_id = 0;
    public int $vlan_id = 0; // references network_vlans.id
    public string $status = 'FREE';
    public ?int $olt_port_id = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->service_id = (int)($data['service_id'] ?? 0);
        $dto->vlan_id = (int)($data['vlan_id'] ?? 0);
        $dto->status = strtoupper(trim((string)($data['status'] ?? 'FREE')));
        $dto->olt_port_id = isset($data['olt_port_id']) && $data['olt_port_id'] !== ''
            ? (int)$data['olt_port_id']
            : null;

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'service_id' => $this->service_id,
            'vlan_id' => $this->vlan_id,
            'status' => $this->status,
            'olt_port_id' => $this->olt_port_id,
        ];
    }
}