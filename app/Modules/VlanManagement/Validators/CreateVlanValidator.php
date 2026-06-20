<?php

namespace App\Modules\VlanManagement\Validators;

class CreateVlanValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        $vlanId = (int)($data['vlan_id'] ?? 0);
        $type = strtoupper($data['vlan_type'] ?? '');
        $allowed = ['C_VLAN','S_VLAN'];

        if ($vlanId <= 0 || $vlanId > 4094) {
            $errors['vlan_id'][] = 'VLAN ID must be 1-4094.';
        }

        if (!in_array($type, $allowed, true)) {
            $errors['vlan_type'][] = 'Invalid VLAN type.';
        }

        if (trim($data['name'] ?? '') === '') {
            $errors['name'][] = 'Name is required.';
        }
        if (empty($data['olt_id']) || (int)$data['olt_id'] <= 0) {
            $errors['olt_id'][] = 'OLT is required.';
        }
        return $errors;
    }
}