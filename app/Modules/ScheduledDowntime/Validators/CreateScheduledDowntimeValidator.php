<?php

namespace App\Modules\ScheduledDowntime\Validators;

class CreateScheduledDowntimeValidator
{
    public function validate(array $input): array
    {
        $errors = [];

        if (trim((string)($input['title'] ?? '')) === '') {
            $errors['title'][] = 'Title is required.';
        }

        if (trim((string)($input['message'] ?? '')) === '') {
            $errors['message'][] = 'Message is required.';
        }

        if (array_key_exists('enabled', $input) && filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
            $errors['enabled'][] = 'Enabled must be a boolean value.';
        }

        $startsAt = $this->parseTimestamp($input['starts_at'] ?? null);
        if ($startsAt === null) {
            $errors['starts_at'][] = 'Start time must be a valid timestamp.';
        }

        $endsAt = $this->parseTimestamp($input['ends_at'] ?? null);
        if ($endsAt === null) {
            $errors['ends_at'][] = 'End time must be a valid timestamp.';
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            $errors['ends_at'][] = 'End time must be after start time.';
        }

        return $errors;
    }

    public function normalise(array $input): array
    {
        $startsAt = $this->parseTimestamp($input['starts_at'] ?? null);
        $endsAt = $this->parseTimestamp($input['ends_at'] ?? null);

        return [
            'title' => trim((string)($input['title'] ?? '')),
            'message' => trim((string)($input['message'] ?? '')),
            'starts_at' => $startsAt?->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            'enabled' => filter_var($input['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ];
    }

    private function parseTimestamp(mixed $value): ?\DateTimeImmutable
    {
        $value = is_string($value) ? trim($value) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}| \d{2}:\d{2}:\d{2})$/', $value)) {
            return null;
        }

        $format = str_contains($value, 'T') ? '!Y-m-d\\TH:i' : '!Y-m-d H:i:s';
        $date = \DateTimeImmutable::createFromFormat($format, $value);
        $dateErrors = \DateTimeImmutable::getLastErrors();

        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
