<?php

namespace App\Modules\BngManagement\Validators;

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
        if ($authType !== 'PASSWORD') {
            $errors['auth_type'] = 'BNG authentication must use PASSWORD.';
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
        if (isset($data['auto_create_svlan_interface']) && (int)$data['auto_create_svlan_interface'] !== 1) {
            $errors['auto_create_svlan_interface'] = 'BNG S-VLAN automation must remain enabled.';
        }

        /**
         * 🔥 NEW: VLAN Mode validation
         */
        $vlanMode = strtoupper(trim((string)($data['vlan_mode'] ?? 'QINQ')));
        if ($vlanMode !== 'QINQ') {
            $errors['vlan_mode'] = 'BNG VLAN mode must remain QINQ.';
        }

        return $errors;
    }
}
