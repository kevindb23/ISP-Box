<?php

namespace App\Modules\VlanManagement\DTOs;

class UpdateVlanDTO extends CreateVlanDTO
{
    public int $id = 0;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->id = (int)($data['id'] ?? 0);
        $dto->vlan_id = (int)($data['vlan_id'] ?? 0);
        $dto->vlan_type = strtoupper($data['vlan_type'] ?? 'C_VLAN');
        $dto->olt_id = (int)($data['olt_id'] ?? 0);
        $dto->olt_port_id = isset($data['olt_port_id']) && $data['olt_port_id'] !== ''
            ? (int)$data['olt_port_id']
            : null;
        $dto->parent_svlan_id = isset($data['parent_svlan_id']) && $data['parent_svlan_id'] !== ''
            ? (int)$data['parent_svlan_id']
            : null;
        $dto->name = trim((string)($data['name'] ?? ''));

        $dto->description = isset($data['description']) && trim((string)$data['description']) !== ''
            ? trim((string)$data['description'])
            : null;

        return $dto;
    }
}
