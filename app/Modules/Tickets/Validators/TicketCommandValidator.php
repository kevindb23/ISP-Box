<?php

namespace App\Modules\Tickets\Validators;

use App\Modules\Tickets\DTOs\TicketCommandDTO;

final class TicketCommandValidator
{
    public function validate(TicketCommandDTO $dto, string $action): array
    {
        $data = $dto->toArray();
        $errors = [];
        if ((int)($data['ticket_id'] ?? 0) <= 0) $errors['ticket_id'] = 'A valid ticket is required.';
        if (in_array($action, ['reply', 'internal_note'], true) && ($data['message'] ?? '') === '') {
            $errors['message'] = $action === 'reply' ? 'Reply message is required.' : 'Internal note is required.';
        }
        if ($action === 'status' && ($data['status'] ?? '') === '') $errors['status'] = 'Status is required.';
        if ($action === 'priority' && ($data['priority'] ?? '') === '') $errors['priority'] = 'Priority is required.';
        return $errors;
    }
}
