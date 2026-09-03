<?php

namespace App\Modules\OntDevices\Validators;

final class AcsWanActionValidator
{
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];
        if (($data['deviceId'] ?? '') === '') $errors[] = 'Device ID missing.';
        if (($data['name'] ?? '') === '') $errors[] = 'WAN Name is required.';
        if (($data['vlan_id'] ?? '') === '' || !ctype_digit((string)$data['vlan_id'])) $errors[] = 'A numeric VLAN ID is required.';
        if ((int)($data['vlan_id'] ?? 0) < 1 || (int)($data['vlan_id'] ?? 0) > 4094) $errors[] = 'VLAN ID must be between 1 and 4094.';
        if ($isUpdate && ($data['original_name'] ?? '') === '') $errors[] = 'Original WAN name is required.';
        if (($data['type'] ?? '') === 'STATIC') {
            foreach (['ip_address' => 'IP Address', 'subnet_mask' => 'Subnet Mask', 'gateway' => 'Gateway'] as $key => $label) {
                if (($data[$key] ?? '') === '') $errors[] = "Static WAN requires {$label}.";
            }
        }
        if (($data['role'] ?? '') === 'INTERNET') $errors[] = 'Subscriber PPPoE WAN must be managed from Service Provisioning.';
        return $errors;
    }
}
