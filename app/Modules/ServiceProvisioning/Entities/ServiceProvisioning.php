<?php

namespace App\Modules\ServiceProvisioning\Entities;

class ServiceProvisioningJob
{
    public int $id;
    public int $subscriber_id;
    public int $service_id;
    public int $ont_id;
    public int $olt_id;
    public int $olt_port_id;
    public int $cvlan;
    public int $svlan;
    public int $nap_port_id;
    public string $status;
    public string $created_at;

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}