<?php

namespace App\Modules\WorkOrders\Validators;

use App\Modules\WorkOrders\DTOs\WorkOrderCommandDTO;

final class WorkOrderCommandValidator
{
    public function validate(WorkOrderCommandDTO $dto, string $action): array
    {
        $data = $dto->toArray();
        $errors = [];
        if ($action === 'create' && (int)($data['ticket_id'] ?? 0) <= 0) {
            $errors['ticket_id'] = 'A valid ticket is required.';
        }
        if ($action !== 'create' && !in_array($action, ['complete_task', 'reopen_task'], true)
            && (int)($data['work_order_id'] ?? 0) <= 0) {
            $errors['work_order_id'] = 'A valid work order is required.';
        }
        if (in_array($action, ['complete_task', 'reopen_task'], true) && (int)($data['task_id'] ?? 0) <= 0) {
            $errors['task_id'] = 'A valid task is required.';
        }
        if ($action === 'status' && ($data['status'] ?? '') === '') $errors['status'] = 'Status is required.';
        return $errors;
    }
}
