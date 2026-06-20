<?php

namespace App\Modules\OntDevices\Entities;

class OntDevices
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = [
            'id' => $data['id'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'model' => $data['model'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'status' => $data['status'] ?? 'UNASSIGNED',
            'created_at' => $data['created_at'] ?? null,
            'equipment_id' => $data['equipment_id'] ?? null,
            'subscriber_id' => $data['subscriber_id'] ?? null,
            'subscriber_name' => $data['subscriber_name'] ?? null,
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}