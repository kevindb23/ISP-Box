<?php


namespace App\Modules\NapManagement\Entities;

class SplitterOutputPortEntity
{
    public ?int $id = null;
    public int $splitter_id;

    public int $port_number;

    public ?string $connected_entity_type = null; // SUBSCRIBER | NAP | etc
    public ?int $connected_entity_id = null;

    public string $status = 'AVAILABLE'; // AVAILABLE | USED | RESERVED | MAINTENANCE
    public ?string $deleted_at = null;

    public static function fromArray(array $row): self
    {
        $entity = new self();

        $entity->id = isset($row['id']) ? (int)$row['id'] : null;
        $entity->splitter_id = (int)($row['splitter_id'] ?? 0);

        $entity->port_number = (int)($row['port_number'] ?? 0);

        $entity->connected_entity_type = $row['connected_entity_type'] ?? null;
        $entity->connected_entity_id = isset($row['connected_entity_id'])
            ? (int)$row['connected_entity_id']
            : null;

        $entity->status = (string)($row['status'] ?? 'AVAILABLE');
        $entity->deleted_at = $row['deleted_at'] ?? null;

        return $entity;
    }

    public function isUsed(): bool
    {
        return $this->connected_entity_id !== null;
    }

    public function isNapConnection(): bool
    {
        return $this->connected_entity_type === 'NAP';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'splitter_id' => $this->splitter_id,
            'port_number' => $this->port_number,
            'connected_entity_type' => $this->connected_entity_type,
            'connected_entity_id' => $this->connected_entity_id,
            'status' => $this->status,
            'deleted_at' => $this->deleted_at,
        ];
    }
}