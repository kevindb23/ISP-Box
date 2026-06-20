<?php

namespace App\Modules\Audit\DTOs;

class AuditFilterDTO
{
    public ?string $module = null;
    public ?string $action = null;
    public ?string $username = null;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
}