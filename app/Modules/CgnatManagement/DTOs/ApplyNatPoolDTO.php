<?php

namespace App\Modules\CgnatManagement\DTOs;

class ApplyNatPoolDTO
{
    public int $pool_id;
    public bool $apply_accel;
    public bool $apply_frr;

    public function __construct(array $data)
    {
        $this->pool_id = (int)($data['pool_id'] ?? 0);
        $this->apply_accel = !empty($data['apply_accel']);
        $this->apply_frr = !empty($data['apply_frr']);
    }

    public function toArray(): array
    {
        return [
            'pool_id' => $this->pool_id,
            'apply_accel' => $this->apply_accel,
            'apply_frr' => $this->apply_frr,
        ];
    }
}