<?php

namespace App\Modules\Audit\Validators;

use App\Modules\Audit\DTOs\AuditFilterDTO;

class AuditFilterValidator
{
    public function validate(AuditFilterDTO $filter): array
    {
        $errors = [];

        foreach (['dateFrom' => 'date_from', 'dateTo' => 'date_to'] as $property => $field) {
            $value = $filter->{$property};

            if ($value !== null && !$this->validDate($value)) {
                $errors[$field] = 'Date must use YYYY-MM-DD format.';
            }
        }

        if (
            $filter->dateFrom !== null
            && $filter->dateTo !== null
            && $filter->dateFrom > $filter->dateTo
        ) {
            $errors['date_range'] = 'From date cannot be later than to date.';
        }

        return $errors;
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
