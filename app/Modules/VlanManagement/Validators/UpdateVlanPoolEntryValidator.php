<?php

namespace App\Modules\VlanManagement\Validators;

class UpdateVlanPoolEntryValidator extends CreateVlanPoolEntryValidator
{
    public function validate(array $data): array
    {
        $errors = parent::validate($data);

        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            $errors['id'][] = 'Invalid VLAN pool entry ID.';
        }

        return $errors;
    }
}