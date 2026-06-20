<?php

namespace App\Modules\VlanManagement\Validators;

class CreateVlanPoolEntryValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        $serviceId = (int)($data['service_id'] ?? 0);
        $vlanId = (int)($data['vlan_id'] ?? 0);
        $status = strtoupper(trim((string)($data['status'] ?? 'FREE')));
        $allowedStatuses = ['FREE', 'RESERVED', 'USED'];

        if ($serviceId <= 0) {
            $errors['service_id'][] = 'Service ID is required.';
        }

        if ($vlanId <= 0) {
            $errors['vlan_id'][] = 'VLAN is required.';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors['status'][] = 'Status must be FREE, RESERVED, or USED.';
        }

        if (isset($data['olt_port_id']) && $data['olt_port_id'] !== null && $data['olt_port_id'] !== '') {
            if ((int)$data['olt_port_id'] <= 0) {
                $errors['olt_port_id'][] = 'OLT port ID must be a valid positive number.';
            }
        }

        return $errors;
    }
}