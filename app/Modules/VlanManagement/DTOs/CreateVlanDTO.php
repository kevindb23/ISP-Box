<?php

namespace App\Modules\VlanManagement\DTOs;

class CreateVlanDTO
{
    public int $vlan_id;
    public string $vlan_type;
    public int $olt_id;
    public ?int $olt_port_id;
    public ?int $parent_svlan_id;
    public string $name;
    public string $description;

    public static function fromArray(array $data): self
    {
        $dto = new self();

        $dto->vlan_id = (int)($data['vlan_id'] ?? 0);
        $dto->vlan_type = strtoupper((string)($data['vlan_type'] ?? ''));
        $dto->olt_id = (int)($data['olt_id'] ?? 0);

        $dto->olt_port_id = null;
        if (isset($data['olt_port_id']) && $data['olt_port_id'] !== '' && $data['olt_port_id'] !== null) {
            $dto->olt_port_id = (int)$data['olt_port_id'];
        }
        $dto->parent_svlan_id = isset($data['parent_svlan_id']) && $data['parent_svlan_id'] !== '' ? (int)$data['parent_svlan_id'] : null;

        $dto->name = (string)($data['name'] ?? '');
        $dto->description = (string)($data['description'] ?? '');

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'vlan_id' => $this->vlan_id,
            'vlan_type' => $this->vlan_type,
            'olt_id' => $this->olt_id,
            'olt_port_id' => $this->olt_port_id,
            'parent_svlan_id' => $this->parent_svlan_id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
