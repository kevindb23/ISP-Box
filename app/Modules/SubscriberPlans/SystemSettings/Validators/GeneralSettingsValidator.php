<?php

namespace App\Modules\SubscriberPlans\SystemSettings\Validators;

class GeneralSettingsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['system_name'])) {
            $errors['system_name'] = 'System name is required';
        }

        if (!in_array($data['provisioning_mode'] ?? '', ['FULL_AUTO', 'ASSISTED', 'MANUAL'])) {
            $errors['provisioning_mode'] = 'Invalid provisioning mode';
        }

        return $errors;
    }
}