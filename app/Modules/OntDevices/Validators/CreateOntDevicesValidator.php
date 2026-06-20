<?php

namespace App\Modules\OntDevices\Validators;

class CreateOntDevicesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        if (($data['serial_number'] ?? '') === '') {
            $errors[] = 'Serial number is required.';
        }

        $status = strtoupper((string)($data['status'] ?? 'UNASSIGNED'));
        if (!in_array($status, ['UNASSIGNED', 'ASSIGNED', 'OFFLINE'], true)) {
            $errors[] = 'Invalid status selected.';
        }

        return $errors;
    }
}