<?php

namespace App\Modules\CgnatManagement\Validators;

class ApplyNatPoolValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        if ((int)($data['pool_id'] ?? 0) <= 0) {
            $errors['pool_id'] = 'Valid NAT pool ID is required.';
        }

        $applyAccel = !empty($data['apply_accel']);
        $applyFrr = !empty($data['apply_frr']);

        if (!$applyAccel && !$applyFrr) {
            $errors['target'] = 'Select at least one target to apply.';
        }

        return $errors;
    }
}