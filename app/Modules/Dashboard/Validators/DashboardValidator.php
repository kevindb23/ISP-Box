<?php

namespace App\Modules\Dashboard\Validators;

class DashboardValidator
{
    public function validateStatsRequest(): array
    {
        return [
            'valid' => true,
            'errors' => [],
        ];
    }
}