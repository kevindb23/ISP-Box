<?php

namespace App\Modules\Audit\Validators;

use App\Modules\Audit\DTOs\AuditEventDTO;
use InvalidArgumentException;

class AuditEventValidator
{
    private const RESULTS = ['SUCCESS', 'FAILED', 'DENIED', 'PARTIAL', 'SKIPPED', 'REDIRECT'];

    public function validate(AuditEventDTO $event): void
    {
        if ($event->module === '') {
            throw new InvalidArgumentException('Audit module is required.');
        }

        if ($event->action === '') {
            throw new InvalidArgumentException('Audit action is required.');
        }

        if (!in_array($event->result, self::RESULTS, true)) {
            throw new InvalidArgumentException('Invalid audit result.');
        }
    }
}
