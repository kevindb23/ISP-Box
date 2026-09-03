<?php

namespace App\Modules\TechnicianManagement\Validators;

use App\Modules\TechnicianManagement\DTOs\TechnicianCommandDTO;

final class TechnicianCommandValidator
{
    public function validate(TechnicianCommandDTO $dto, string $action): array
    {
        $data = $dto->toArray();
        $errors = [];
        if (in_array($action, ['status', 'profile'], true) && (int)($data['user_id'] ?? 0) <= 0) {
            $errors['user_id'] = 'A valid technician is required.';
        }
        if ($action === 'assign') {
            if ((int)($data['work_order_id'] ?? 0) <= 0) $errors['work_order_id'] = 'A valid work order is required.';
            if ((int)($data['technician_id'] ?? 0) <= 0) $errors['technician_id'] = 'A valid technician is required.';
        }
        if ($action === 'work_order_status' && (int)($data['work_order_id'] ?? 0) <= 0) {
            $errors['work_order_id'] = 'A valid work order is required.';
        }
        return $errors;
    }
}
