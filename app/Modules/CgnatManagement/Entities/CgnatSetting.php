<?php

namespace App\Modules\CgnatManagement\Entities;

class CgnatSetting
{
    public ?int $id = null;
    public int $enabled = 0;
    public string $inside_network = '';
    public string $public_start_ip = '';
    public string $public_end_ip = '';
    public ?string $egress_interface = null;
    public ?string $router_next_hop = null;
    public ?string $remarks = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function __construct(array $data = [])
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->enabled = isset($data['enabled']) ? (int)$data['enabled'] : 0;
        $this->inside_network = trim((string)($data['inside_network'] ?? ''));
        $this->public_start_ip = trim((string)($data['public_start_ip'] ?? ''));
        $this->public_end_ip = trim((string)($data['public_end_ip'] ?? ''));
        $this->egress_interface = isset($data['egress_interface']) && trim((string)$data['egress_interface']) !== ''
            ? trim((string)$data['egress_interface'])
            : null;
        $this->router_next_hop = isset($data['router_next_hop']) && trim((string)$data['router_next_hop']) !== ''
            ? trim((string)$data['router_next_hop'])
            : null;
        $this->remarks = isset($data['remarks']) && trim((string)$data['remarks']) !== ''
            ? trim((string)$data['remarks'])
            : null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'enabled' => $this->enabled,
            'inside_network' => $this->inside_network,
            'public_start_ip' => $this->public_start_ip,
            'public_end_ip' => $this->public_end_ip,
            'egress_interface' => $this->egress_interface,
            'router_next_hop' => $this->router_next_hop,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}