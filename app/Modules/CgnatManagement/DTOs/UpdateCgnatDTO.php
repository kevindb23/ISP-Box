<?php

namespace App\Modules\CgnatManagement\DTOs;

class UpdateCgnatDTO
{
    public int $enabled;
    public string $inside_network;
    public ?string $bng_interface;
    public string $public_start_ip;
    public string $public_end_ip;
    public ?string $egress_interface;

    public function __construct(array $data)
    {
        $this->enabled = (int)($data['enabled'] ?? 0);
        $this->inside_network = trim((string)($data['inside_network'] ?? ''));
        $this->bng_interface = isset($data['bng_interface']) && trim((string)$data['bng_interface']) !== ''
            ? trim((string)$data['bng_interface'])
            : null;
        $this->public_start_ip = trim((string)($data['public_start_ip'] ?? ''));
        $this->public_end_ip = trim((string)($data['public_end_ip'] ?? ''));
        $this->egress_interface = isset($data['egress_interface']) && trim((string)$data['egress_interface']) !== ''
            ? trim((string)$data['egress_interface'])
            : null;
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'inside_network' => $this->inside_network,
            'bng_interface' => $this->bng_interface,
            'public_start_ip' => $this->public_start_ip,
            'public_end_ip' => $this->public_end_ip,
            'egress_interface' => $this->egress_interface,
        ];
    }
}
