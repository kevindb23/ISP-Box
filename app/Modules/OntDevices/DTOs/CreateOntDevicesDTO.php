<?php

namespace App\Modules\OntDevices\DTOs;

class CreateOntDevicesDTO
{
    private array $data;

    private function __construct(array $data = [])
    {
        $status = strtoupper(trim((string)($data['status'] ?? 'UNASSIGNED')));
        $this->data = [
            'id' => isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null,
            'serial_number' => strtoupper(trim((string)($data['serial_number'] ?? ''))),
            'model' => $this->nullableString($data['model'] ?? null),
            'vendor' => $this->nullableString($data['vendor'] ?? null),
            'subscriber_id' => $this->nullableInt($data['subscriber_id'] ?? null),
            'status' => $status,
            'olt_id' => $this->nullableInt($data['olt_id'] ?? null),
            'frame' => $this->nullableInt($data['frame'] ?? null),
            'slot' => $this->nullableInt($data['slot'] ?? null),
            'port' => $this->nullableInt($data['port'] ?? null),
            'ont_id' => $this->nullableInt($data['ont_id'] ?? null),
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
