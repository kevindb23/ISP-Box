<?php

namespace App\Modules\VlanManagement\Validators;

class UpdateVlanValidator extends CreateVlanValidator
{
    public function validate(array $data): array
    {
        $errors = parent::validate($data);

        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            $errors['id'][] = 'Invalid VLAN record ID.';
        }

        return $errors;
    }
}