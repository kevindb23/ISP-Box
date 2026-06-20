<?php

namespace App\Modules\CgnatManagement\Entities;

class NatPool
{
    public ?int $id;
    public string $pool_name;
    public string $type;
    public string $network;
    public ?string $gateway;
    public ?string $range_start;
    public ?string $range_end;
    public ?string $accel_pool_name;
    public string $status;
    public ?string $remarks;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct(array $data = [])
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->pool_name = (string)($data['pool_name'] ?? '');
        $this->type = (string)($data['type'] ?? 'CGNAT');
        $this->network = (string)($data['network'] ?? '');
        $this->gateway = isset($data['gateway']) && $data['gateway'] !== '' ? (string)$data['gateway'] : null;
        $this->range_start = isset($data['range_start']) && $data['range_start'] !== '' ? (string)$data['range_start'] : null;
        $this->range_end = isset($data['range_end']) && $data['range_end'] !== '' ? (string)$data['range_end'] : null;
        $this->accel_pool_name = isset($data['accel_pool_name']) && $data['accel_pool_name'] !== '' ? (string)$data['accel_pool_name'] : null;
        $this->status = (string)($data['status'] ?? 'DRAFT');
        $this->remarks = isset($data['remarks']) && $data['remarks'] !== '' ? (string)$data['remarks'] : null;
        $this->created_at = isset($data['created_at']) ? (string)$data['created_at'] : null;
        $this->updated_at = isset($data['updated_at']) ? (string)$data['updated_at'] : null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pool_name' => $this->pool_name,
            'type' => $this->type,
            'network' => $this->network,
            'gateway' => $this->gateway,
            'range_start' => $this->range_start,
            'range_end' => $this->range_end,
            'accel_pool_name' => $this->accel_pool_name,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}