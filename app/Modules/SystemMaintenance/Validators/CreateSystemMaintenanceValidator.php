<?php

namespace App\Modules\SystemMaintenance\Validators;

use DateTimeImmutable;
use DateTimeZone;

class CreateSystemMaintenanceValidator
{
    public function validate(array $input): array
    {
        $errors = [];

        if (trim((string)($input['message'] ?? '')) === '') {
            $errors['message'] = 'A customer-facing maintenance message is required.';
        }

        $startsAt = trim((string)($input['starts_at'] ?? ''));
        $endsAt = trim((string)($input['ends_at'] ?? ''));

        if (($startsAt === '') !== ($endsAt === '')) {
            $errors['schedule'] = 'Both a start and end time are required for scheduled maintenance.';
            return $errors;
        }

        if ($startsAt === '' && $endsAt === '') {
            return $errors;
        }

        try {
            $start = $this->parse($startsAt);
            $end = $this->parse($endsAt);

            if (!$start || !$end || $end <= $start) {
                $errors['schedule'] = 'The maintenance end time must be after the start time.';
            }
        } catch (\Throwable $e) {
            $errors['schedule'] = 'Maintenance times must be valid date and time values.';
        }

        return $errors;
    }

    private function parse(string $value): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone(date_default_timezone_get() ?: 'UTC');

        foreach (['!Y-m-d\\TH:i', '!Y-m-d\\TH:i:s', '!Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

            if ($date && !$hasErrors) {
                return $date;
            }
        }

        return null;
    }
}
