<?php

namespace App\Modules\CgnatManagement\Entities;

class CgnatSetting
{
    public ?int $id = null;
    public int $enabled = 0;
    public string $inside_network = '';
    public ?string $bng_interface = null;
    public string $public_start_ip = '';
    public string $public_end_ip = '';
    public ?string $egress_interface = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function __construct(array $data = [])
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->enabled = isset($data['enabled']) ? (int)$data['enabled'] : 0;
        $this->inside_network = trim((string)($data['inside_network'] ?? ''));
        $this->bng_interface = isset($data['bng_interface']) && trim((string)$data['bng_interface']) !== ''
            ? trim((string)$data['bng_interface'])
            : null;
        $this->public_start_ip = trim((string)($data['public_start_ip'] ?? ''));
        $this->public_end_ip = trim((string)($data['public_end_ip'] ?? ''));
        $this->egress_interface = isset($data['egress_interface']) && trim((string)$data['egress_interface']) !== ''
            ? trim((string)$data['egress_interface'])
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
            'bng_interface' => $this->bng_interface,
            'public_start_ip' => $this->public_start_ip,
            'public_end_ip' => $this->public_end_ip,
            'egress_interface' => $this->egress_interface,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
