<?php

namespace App\Modules\NapManagement\DTOs;

class CreateNetworkDesignBuildDTO
{
    public int $profile_id;
    public int $olt_id;
    public int $olt_port_id;

    public ?int $feeder_cable_id = null;
    public ?int $feeder_fiber_core_id = null;

    public string $build_name = '';
    public string $lcp_name = '';

    public ?string $location = null;
    public ?string $latitude = null;
    public ?string $longitude = null;

    public int $nap_count = 0;
    public ?string $remarks = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();

        $dto->profile_id = (int)($data['profile_id'] ?? 0);
        $dto->olt_id = (int)($data['olt_id'] ?? 0);
        $dto->olt_port_id = (int)($data['olt_port_id'] ?? 0);

        $dto->feeder_cable_id = isset($data['feeder_cable_id']) && $data['feeder_cable_id'] !== ''
            ? (int)$data['feeder_cable_id']
            : null;

        $dto->feeder_fiber_core_id = isset($data['feeder_fiber_core_id']) && $data['feeder_fiber_core_id'] !== ''
            ? (int)$data['feeder_fiber_core_id']
            : null;

        $dto->build_name = trim((string)($data['build_name'] ?? ''));
        $dto->lcp_name = trim((string)($data['lcp_name'] ?? ''));

        $dto->location = isset($data['location']) ? trim((string)$data['location']) : null;
        $dto->latitude = isset($data['latitude']) && $data['latitude'] !== ''
            ? trim((string)$data['latitude'])
            : null;
        $dto->longitude = isset($data['longitude']) && $data['longitude'] !== ''
            ? trim((string)$data['longitude'])
            : null;

        $dto->nap_count = (int)($data['nap_count'] ?? 0);
        $dto->remarks = isset($data['remarks']) ? trim((string)$data['remarks']) : null;

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'profile_id' => $this->profile_id,
            'olt_id' => $this->olt_id,
            'olt_port_id' => $this->olt_port_id,
            'feeder_cable_id' => $this->feeder_cable_id,
            'feeder_fiber_core_id' => $this->feeder_fiber_core_id,
            'build_name' => $this->build_name,
            'lcp_name' => $this->lcp_name,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'nap_count' => $this->nap_count,
            'remarks' => $this->remarks,
        ];
    }
}