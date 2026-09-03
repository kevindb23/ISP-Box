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

        foreach (['olt_id', 'frame', 'slot', 'port', 'ont_id', 'subscriber_id'] as $field) {
            if (($data[$field] ?? null) !== null && (int)$data[$field] < 0) {
                $errors[] = sprintf('%s cannot be negative.', str_replace('_', ' ', ucfirst($field)));
            }
        }

        return $errors;
    }
}
