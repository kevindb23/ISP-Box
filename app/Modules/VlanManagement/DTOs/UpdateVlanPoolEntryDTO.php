<?php

namespace App\Modules\VlanManagement\DTOs;

class UpdateVlanPoolEntryDTO extends CreateVlanPoolEntryDTO
{
    public int $id = 0;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->id = (int)($data['id'] ?? 0);
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
        return parent::toArray() + [
                'id' => $this->id,
            ];
    }
}