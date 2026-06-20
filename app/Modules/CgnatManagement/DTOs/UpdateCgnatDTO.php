<?php

namespace App\Modules\CgnatManagement\DTOs;

class UpdateCgnatDTO
{
    public int $enabled;
    public string $inside_network;
    public string $public_start_ip;
    public string $public_end_ip;
    public ?string $egress_interface;
    public ?string $router_next_hop;
    public ?string $remarks;

    public function __construct(array $data)
    {
        $this->enabled = (int)($data['enabled'] ?? 0);
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
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'inside_network' => $this->inside_network,
            'public_start_ip' => $this->public_start_ip,
            'public_end_ip' => $this->public_end_ip,
            'egress_interface' => $this->egress_interface,
            'router_next_hop' => $this->router_next_hop,
            'remarks' => $this->remarks,
        ];
    }
}