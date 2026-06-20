<?php

namespace App\Modules\NapManagement\Entities;

class NetworkBoxEntity
{
    public ?int $id = null;
    public ?string $legacy_table = null;
    public ?int $legacy_id = null;

    public string $box_type = '';
    public string $box_code = '';
    public string $box_name = '';

    public ?string $location = null;
    public ?string $latitude = null;
    public ?string $longitude = null;

    public string $status = 'ACTIVE';
    public ?string $remarks = null;
    public ?string $deleted_at = null;

    public static function fromArray(array $row): self
    {
        $entity = new self();

        $entity->id = isset($row['id']) ? (int)$row['id'] : null;
        $entity->legacy_table = $row['legacy_table'] ?? null;
        $entity->legacy_id = isset($row['legacy_id']) ? (int)$row['legacy_id'] : null;

        $entity->box_type = (string)($row['box_type'] ?? '');
        $entity->box_code = (string)($row['box_code'] ?? '');
        $entity->box_name = (string)($row['box_name'] ?? '');

        $entity->location = $row['location'] ?? null;
        $entity->latitude = isset($row['latitude']) ? (string)$row['latitude'] : null;
        $entity->longitude = isset($row['longitude']) ? (string)$row['longitude'] : null;

        $entity->status = (string)($row['status'] ?? 'ACTIVE');
        $entity->remarks = $row['remarks'] ?? null;
        $entity->deleted_at = $row['deleted_at'] ?? null;

        return $entity;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'legacy_table' => $this->legacy_table,
            'legacy_id' => $this->legacy_id,
            'box_type' => $this->box_type,
            'box_code' => $this->box_code,
            'box_name' => $this->box_name,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'deleted_at' => $this->deleted_at,
        ];
    }
}