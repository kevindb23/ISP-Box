<?php


namespace App\Modules\NapManagement\Entities;

class BoxUplinkConnectionEntity
{
    public ?int $id = null;
    public int $box_id;

    public string $feed_mode = ''; // DIRECT_LCP | DIRECT_DISTRIBUTION | PASS_THROUGH_SPLICE | SHARED_CABLE_SPLICE | CASCADE_FROM_NAP

    public ?int $source_box_id = null;
    public ?int $source_port_id = null;

    public ?int $source_cable_id = null;
    public ?int $source_fiber_core = null;

    public ?string $splice_point = null;
    public ?string $splice_note = null;

    public int $is_active = 1;
    public ?string $connected_at = null;
    public ?string $disconnected_at = null;

    public static function fromArray(array $row): self
    {
        $entity = new self();

        $entity->id = isset($row['id']) ? (int)$row['id'] : null;
        $entity->box_id = (int)($row['box_id'] ?? 0);

        $entity->feed_mode = (string)($row['feed_mode'] ?? '');

        $entity->source_box_id = isset($row['source_box_id'])
            ? (int)$row['source_box_id']
            : null;

        $entity->source_port_id = isset($row['source_port_id'])
            ? (int)$row['source_port_id']
            : null;

        $entity->source_cable_id = isset($row['source_cable_id'])
            ? (int)$row['source_cable_id']
            : null;

        $entity->source_fiber_core = isset($row['source_fiber_core'])
            ? (int)$row['source_fiber_core']
            : null;

        $entity->splice_point = $row['splice_point'] ?? null;
        $entity->splice_note = $row['splice_note'] ?? null;

        $entity->is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;
        $entity->connected_at = $row['connected_at'] ?? null;
        $entity->disconnected_at = $row['disconnected_at'] ?? null;

        return $entity;
    }

    public function isActive(): bool
    {
        return $this->is_active === 1;
    }

    public function isCascade(): bool
    {
        return strtoupper($this->feed_mode) === 'CASCADE_FROM_NAP';
    }

    public function isDirectLcp(): bool
    {
        return strtoupper($this->feed_mode) === 'DIRECT_LCP';
    }

    public function usesCableFeed(): bool
    {
        return in_array(
            strtoupper($this->feed_mode),
            ['DIRECT_DISTRIBUTION', 'PASS_THROUGH_SPLICE', 'SHARED_CABLE_SPLICE'],
            true
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'box_id' => $this->box_id,
            'feed_mode' => $this->feed_mode,
            'source_box_id' => $this->source_box_id,
            'source_port_id' => $this->source_port_id,
            'source_cable_id' => $this->source_cable_id,
            'source_fiber_core' => $this->source_fiber_core,
            'splice_point' => $this->splice_point,
            'splice_note' => $this->splice_note,
            'is_active' => $this->is_active,
            'connected_at' => $this->connected_at,
            'disconnected_at' => $this->disconnected_at,
        ];
    }
}