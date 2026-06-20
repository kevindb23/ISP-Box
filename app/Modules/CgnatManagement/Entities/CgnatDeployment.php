<?php

namespace App\Modules\CgnatManagement\Entities;

class CgnatDeployment
{
    public ?int $id;
    public int $pool_id;
    public string $target_type;
    public string $status;
    public ?string $rendered_config;
    public ?string $result_message;
    public ?string $applied_at;
    public ?string $created_at;

    public function __construct(array $data = [])
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->pool_id = (int)($data['pool_id'] ?? 0);
        $this->target_type = (string)($data['target_type'] ?? 'ACCEL');
        $this->status = (string)($data['status'] ?? 'PENDING');
        $this->rendered_config = isset($data['rendered_config']) ? (string)$data['rendered_config'] : null;
        $this->result_message = isset($data['result_message']) ? (string)$data['result_message'] : null;
        $this->applied_at = isset($data['applied_at']) ? (string)$data['applied_at'] : null;
        $this->created_at = isset($data['created_at']) ? (string)$data['created_at'] : null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pool_id' => $this->pool_id,
            'target_type' => $this->target_type,
            'status' => $this->status,
            'rendered_config' => $this->rendered_config,
            'result_message' => $this->result_message,
            'applied_at' => $this->applied_at,
            'created_at' => $this->created_at,
        ];
    }
}