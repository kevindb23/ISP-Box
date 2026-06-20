<?php

namespace App\Modules\CgnatManagement\DTOs;

class CreateNatPoolDTO
{
    public string $pool_name;
    public string $type;
    public string $network;
    public ?string $gateway;
    public ?string $range_start;
    public ?string $range_end;
    public ?string $accel_pool_name;
    public string $status;
    public ?string $remarks;

    public function __construct(array $data)
    {
        $this->pool_name = trim((string)($data['pool_name'] ?? ''));
        $this->type = strtoupper(trim((string)($data['type'] ?? 'CGNAT')));
        $this->network = trim((string)($data['network'] ?? ''));
        $this->gateway = isset($data['gateway']) && trim((string)$data['gateway']) !== '' ? trim((string)$data['gateway']) : null;
        $this->range_start = isset($data['range_start']) && trim((string)$data['range_start']) !== '' ? trim((string)$data['range_start']) : null;
        $this->range_end = isset($data['range_end']) && trim((string)$data['range_end']) !== '' ? trim((string)$data['range_end']) : null;
        $this->accel_pool_name = isset($data['accel_pool_name']) && trim((string)$data['accel_pool_name']) !== '' ? trim((string)$data['accel_pool_name']) : null;
        $this->status = strtoupper(trim((string)($data['status'] ?? 'DRAFT')));
        $this->remarks = isset($data['remarks']) && trim((string)$data['remarks']) !== '' ? trim((string)$data['remarks']) : null;
    }

    public function toArray(): array
    {
        return [
            'pool_name' => $this->pool_name,
            'type' => $this->type,
            'network' => $this->network,
            'gateway' => $this->gateway,
            'range_start' => $this->range_start,
            'range_end' => $this->range_end,
            'accel_pool_name' => $this->accel_pool_name,
            'status' => $this->status,
            'remarks' => $this->remarks,
        ];
    }
}