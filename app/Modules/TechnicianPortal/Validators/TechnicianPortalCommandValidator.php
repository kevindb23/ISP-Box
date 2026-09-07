<?php

namespace App\Modules\TechnicianPortal\Validators;

use App\Modules\TechnicianPortal\DTOs\TechnicianPortalCommandDTO;

final class TechnicianPortalCommandValidator
{
    public function validate(TechnicianPortalCommandDTO $dto, string $action): array
    {
        $data = $dto->toArray();
        $errors = [];
        if (!in_array($action, ['time_in', 'time_out', 'attendance_status', 'delete_photo', 'complete_task'], true)
            && (int)($data['work_order_id'] ?? 0) <= 0) {
            $errors['work_order_id'] = 'Invalid work order ID.';
        }
        if ($action === 'complete_task' && (int)($data['task_id'] ?? 0) <= 0) {
            $errors['task_id'] = 'A valid task is required.';
        }
        if ($action === 'delete_photo' && (int)($data['attachment_id'] ?? 0) <= 0) {
            $errors['attachment_id'] = 'A valid attachment is required.';
        }
        if ($action === 'note' && ($data['note'] ?? '') === '') $errors['note'] = 'Note is required.';
        if ($action === 'attendance_status' && ($data['status'] ?? '') === '') $errors['status'] = 'Status is required.';
        return $errors;
    }
}
