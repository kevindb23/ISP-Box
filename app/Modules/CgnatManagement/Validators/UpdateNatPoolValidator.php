<?php

namespace App\Modules\CgnatManagement\Validators;

class UpdateNatPoolValidator extends CreateNatPoolValidator
{
    public function validate(array $data): array
    {
        $errors = parent::validate($data);

        if ((int)($data['id'] ?? 0) <= 0) {
            $errors['id'] = 'Valid NAT pool ID is required.';
        }

        return $errors;
    }
}