<?php

namespace App\Modules\ServiceProvisioning\DTOs;

class CreateProvisioningDTO
{
    public int $subscriber_id;
    public int $plan_id;
    public int $service_id;
    public int $ont_id;
    public string $ont_serial;
    public int $olt_id;
    public int $olt_port_id;
    public int $network_box_id;
    public int $splitter_id;
    public int $splitter_output_port_id;
    public string $provision_mode;

    public function __construct(array $data)
    {
        $this->subscriber_id = (int)($data['subscriber_id'] ?? 0);
        $this->plan_id = (int)($data['plan_id'] ?? 0);
        $this->service_id = (int)($data['service_id'] ?? 0);
        $this->ont_id = (int)($data['ont_id'] ?? 0);
        $this->ont_serial = trim((string)($data['ont_serial'] ?? ''));
        $this->olt_id = (int)($data['olt_id'] ?? 0);
        $this->olt_port_id = (int)($data['olt_port_id'] ?? 0);
        $this->network_box_id = (int)($data['network_box_id'] ?? 0);
        $this->splitter_id = (int)($data['splitter_id'] ?? 0);
        $this->splitter_output_port_id = (int)($data['splitter_output_port_id'] ?? 0);
        $this->provision_mode = strtoupper($data['provision_mode'] ?? 'FULL_AUTO');
    }
}