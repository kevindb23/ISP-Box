<?php

namespace App\Modules\SubscriberPlans\Validators;

class CreateSubscriberPlansValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        $planName     = trim((string)($data['plan_name'] ?? ''));
        $price        = (float)($data['price'] ?? 0);
        $planType     = strtoupper(trim((string)($data['plan_type'] ?? 'POSTPAID')));
        $validityDays = (int)($data['validity_days'] ?? 30);
        $speed        = (int)($data['speed'] ?? $data['speed_mbps'] ?? 0);
        $isActive     = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if ($planName === '') {
            $errors[] = 'Plan name is required.';
        }

        if ($speed <= 0) {
            $errors[] = 'Speed must be greater than 0.';
        }

        if ($price < 0) {
            $errors[] = 'Price cannot be negative.';
        }

        if (!in_array($planType, ['POSTPAID', 'PREPAID'], true)) {
            $errors[] = 'Invalid plan type.';
        }

        if ($planType === 'PREPAID' && !in_array($validityDays, [7, 14, 30], true)) {
            $errors[] = 'Prepaid validity must be 7, 14, or 30 days.';
        }

        if ($planType === 'POSTPAID' && $validityDays !== 30) {
            $errors[] = 'Postpaid validity must be 30 days.';
        }

        if (!in_array($isActive, [0, 1], true)) {
            $errors[] = 'Invalid plan status.';
        }

        return $errors;
    }
}
