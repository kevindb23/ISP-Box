<?php


namespace App\Modules\NapManagement\Entities;

class BoxSplitterEntity
{
    public ?int $id = null;
    public int $box_id;

    public string $splitter_name = '';
    public int $splitter_ratio = 0; // e.g. 8, 16

    public ?string $status = 'ACTIVE';
    public ?string $deleted_at = null;

    public static function fromArray(array $row): self
    {
        $entity = new self();

        $entity->id = isset($row['id']) ? (int)$row['id'] : null;
        $entity->box_id = (int)($row['box_id'] ?? 0);

        $entity->splitter_name = (string)($row['splitter_name'] ?? '');
        $entity->splitter_ratio = (int)($row['splitter_ratio'] ?? 0);

        $entity->status = $row['status'] ?? 'ACTIVE';
        $entity->deleted_at = $row['deleted_at'] ?? null;

        return $entity;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'box_id' => $this->box_id,
            'splitter_name' => $this->splitter_name,
            'splitter_ratio' => $this->splitter_ratio,
            'status' => $this->status,
            'deleted_at' => $this->deleted_at,
        ];
    }
}