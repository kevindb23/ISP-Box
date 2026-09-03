<?php

namespace App\Modules\SystemSettings\Validators;

class GeneralSettingsValidator
{
    public function validate(array $data): array
    {
        $errors = [];
        if (trim((string)($data['system_name'] ?? '')) === '') $errors['system_name'] = 'System name is required.';
        if (!in_array(strtoupper((string)($data['provisioning_mode'] ?? '')), ['FULL_AUTO', 'ASSISTED', 'MANUAL'], true)) {
            $errors['provisioning_mode'] = 'Provisioning mode is invalid.';
        }
        if (!in_array(strtoupper((string)($data['installation_assignment_mode'] ?? 'MANUAL')), ['MANUAL', 'AUTO_NEAREST'], true)) {
            $errors['installation_assignment_mode'] = 'Installation assignment mode is invalid.';
        }
        if (!in_array(strtoupper((string)($data['currency'] ?? '')), ['PHP', 'USD'], true)) $errors['currency'] = 'Currency is invalid.';
        if (!in_array((string)($data['timezone'] ?? ''), timezone_identifiers_list(), true)) $errors['timezone'] = 'Timezone is invalid.';

        foreach ([
            'invoice_due_days' => [0, 365],
            'grace_period_days' => [0, 365],
            'session_timeout' => [300, 86400],
            'max_login_attempts' => [1, 100],
            'log_retention_days' => [1, 3650],
        ] as $field => [$min, $max]) {
            $value = filter_var($data[$field] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) $errors[$field] = "Value must be between {$min} and {$max}.";
        }

        foreach (['subscriber_prefix', 'service_prefix', 'job_prefix'] as $field) {
            if (!preg_match('/^[A-Za-z0-9_-]{1,12}$/', trim((string)($data[$field] ?? '')))) {
                $errors[$field] = 'Use 1-12 letters, numbers, underscores, or hyphens.';
            }
        }

        return $errors;
    }
}
