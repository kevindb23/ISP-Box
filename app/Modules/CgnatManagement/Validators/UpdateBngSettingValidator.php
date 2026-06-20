<?php

namespace App\Modules\CgnatManagement\Validators;

class UpdateBngSettingValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        if (trim((string)($data['host'] ?? '')) === '') {
            $errors['host'] = 'BNG host is required.';
        }

        if (trim((string)($data['username'] ?? '')) === '') {
            $errors['username'] = 'BNG username is required.';
        }

        $port = isset($data['port']) ? (int)$data['port'] : 0;
        if ($port < 1 || $port > 65535) {
            $errors['port'] = 'SSH port must be between 1 and 65535.';
        }

        $authType = strtoupper(trim((string)($data['auth_type'] ?? 'PASSWORD')));
        if (!in_array($authType, ['PASSWORD', 'KEY'], true)) {
            $errors['auth_type'] = 'Auth type must be PASSWORD or KEY.';
        }

        if ($authType === 'KEY' && trim((string)($data['ssh_key_path'] ?? '')) === '') {
            $errors['ssh_key_path'] = 'SSH key path is required for KEY authentication.';
        }

        /**
         * 🔥 NEW: BNG Parent Interface (REQUIRED for VLAN creation)
         */
        if (trim((string)($data['bng_parent_interface'] ?? '')) === '') {
            $errors['bng_parent_interface'] = 'BNG parent interface is required (e.g., ens17).';
        }

        /**
         * 🔥 NEW: Auto SVLAN Interface (boolean)
         */
        if (isset($data['auto_create_svlan_interface'])) {
            $val = (int)$data['auto_create_svlan_interface'];
            if (!in_array($val, [0, 1], true)) {
                $errors['auto_create_svlan_interface'] = 'Auto create SVLAN must be 0 or 1.';
            }
        }

        /**
         * 🔥 NEW: VLAN Mode validation
         */
        $vlanMode = strtoupper(trim((string)($data['vlan_mode'] ?? 'QINQ')));
        if (!in_array($vlanMode, ['QINQ', 'DOT1Q'], true)) {
            $errors['vlan_mode'] = 'VLAN mode must be QINQ or DOT1Q.';
        }

        return $errors;
    }
}