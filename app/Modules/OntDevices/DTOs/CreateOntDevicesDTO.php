<?php

namespace App\Modules\OntDevices\DTOs;

class CreateOntDevicesDTO
{
    private array $data;

    private function __construct(array $data = [])
    {
        $status = strtoupper(trim((string)($data['status'] ?? 'UNASSIGNED')));
        if (!in_array($status, ['UNASSIGNED', 'ASSIGNED', 'OFFLINE'], true)) {
            $status = 'UNASSIGNED';
        }

        $this->data = [
            'id' => isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null,
            'serial_number' => strtoupper(trim((string)($data['serial_number'] ?? ''))),
            'model' => $this->nullableString($data['model'] ?? null),
            'vendor' => $this->nullableString($data['vendor'] ?? null),
            'equipment_id' => $this->nullableString($data['equipment_id'] ?? null),
            'mac_address' => $this->nullableString($data['mac_address'] ?? null),
            'subscriber_id' => $this->nullableInt($data['subscriber_id'] ?? null),
            'status' => $status,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }
}