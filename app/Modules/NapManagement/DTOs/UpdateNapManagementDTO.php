<?php


namespace App\Modules\NapManagement\DTOs;

class UpdateNapManagementDTO
{
    public int $box_id;
    public string $box_type; // LCP | NAP
    public string $box_name;

    public ?int $total_ports = null;      // for LCP
    public ?int $splitter_ports = null;  // for NAP

    public ?int $parent_lcp_id = null;
    public ?int $parent_nap_id = null;
    public ?int $parent_port_id = null;

    public ?string $location = null;
    public ?string $latitude = null;
    public ?string $longitude = null;
    public ?string $description = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();

        $dto->box_id = (int)($data['box_id'] ?? 0);
        $dto->box_type = strtoupper(trim($data['box_type'] ?? ''));
        $dto->box_name = trim($data['box_name'] ?? '');

        $dto->total_ports = isset($data['total_ports']) ? (int)$data['total_ports'] : null;
        $dto->splitter_ports = isset($data['splitter_ports']) ? (int)$data['splitter_ports'] : null;

        $dto->parent_lcp_id = isset($data['parent_lcp_id']) ? (int)$data['parent_lcp_id'] : null;
        $dto->parent_nap_id = isset($data['parent_nap_id']) ? (int)$data['parent_nap_id'] : null;
        $dto->parent_port_id = isset($data['parent_port_id']) ? (int)$data['parent_port_id'] : null;

        $dto->location = $data['location'] ?? null;
        $dto->latitude = $data['latitude'] ?? null;
        $dto->longitude = $data['longitude'] ?? null;
        $dto->description = $data['description'] ?? null;

        return $dto;
    }
}