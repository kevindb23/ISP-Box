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
            'status' => $data['status'] ?? 'UNASSIGNED',
            'created_at' => $data['created_at'] ?? null,
            'subscriber_id' => $data['subscriber_id'] ?? null,
            'subscriber_name' => $data['subscriber_name'] ?? null,
            'olt_id' => $data['olt_id'] ?? null,
            'frame' => $data['frame'] ?? null,
            'slot' => $data['slot'] ?? null,
            'port' => $data['port'] ?? null,
            'ont_id' => $data['ont_id'] ?? null,
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
