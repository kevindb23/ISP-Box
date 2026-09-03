<?php

namespace App\Modules\ServiceProvisioning\Validators;

class CreateProvisioningValidator
{
    public function validate(array $input): array
    {
        $errors = [];

        $requiredInts = [
            'subscriber_id' => 'Subscriber is required.',
            'plan_id' => 'Plan is required.',
            'ont_id' => 'ONT is required.',
            'olt_id' => 'OLT is required.',
            'olt_port_id' => 'OLT port is required.',
            'network_box_id' => 'NAP is required.',
            'splitter_id' => 'Splitter is required.',
            'splitter_output_port_id' => 'Splitter output port is required.',
        ];

        foreach ($requiredInts as $field => $message) {
            if ((int)($input[$field] ?? 0) <= 0) {
                $errors[$field][] = $message;
            }
        }

        if (isset($input['service_id']) && (int)$input['service_id'] < 0) {
            $errors['service_id'][] = 'Invalid service ID.';
        }

        $serial = strtoupper(trim((string)($input['ont_serial'] ?? '')));
        if ($serial === '') {
            $errors['ont_serial'][] = 'ONT serial is required.';
        }

        $mode = strtoupper(trim((string)($input['provision_mode'] ?? 'FULL_AUTO')));
        if (!in_array($mode, ['FULL_AUTO', 'ASSISTED', 'MANUAL'], true)) {
            $errors['provision_mode'][] = 'Invalid provision mode.';
        }

        return $errors;
    }
}
