<?php

namespace App\Modules\SystemMaintenance\Services;

use App\Modules\SystemMaintenance\Entities\SystemMaintenance;
use App\Modules\SystemMaintenance\Repositories\SystemMaintenanceRepository;
use App\Modules\SystemMaintenance\Validators\CreateSystemMaintenanceValidator;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class SystemMaintenanceService
{
    public function __construct(
        private SystemMaintenanceRepository $repo,
        private CreateSystemMaintenanceValidator $validator
    ) {}

    public function summary(): array
    {
        $row = $this->repo->get();
        return $this->adminPayload($row);
    }

    public function settings(): array
    {
        return $this->adminPayload($this->repo->get());
    }

    public function saveSettings(array $input): array
    {
        $errors = $this->validator->validate($input);

        if ($errors !== []) {
            throw new RuntimeException($this->validationMessage($errors));
        }

        $saved = $this->repo->save([
            'enabled' => $this->toBoolean($input['enabled'] ?? false),
            'message' => trim((string)$input['message']),
            'starts_at' => $this->storageTimestamp($input['starts_at'] ?? null),
            'ends_at' => $this->storageTimestamp($input['ends_at'] ?? null),
        ]);

        return $this->summaryFromRow($saved);
    }

    public function activeState(?DateTimeImmutable $now = null): array
    {
        try {
            $settings = $this->repo->get();
            $active = $this->isActive($settings, $now);
        } catch (\Throwable $e) {
            // A missing or unavailable configuration must never lock out the portal.
            return ['active' => false, 'message' => ''];
        }

        return [
            'active' => $active,
            'message' => $active ? trim((string)($settings['message'] ?? '')) : '',
        ];
    }

    public function isActive(array $settings, ?DateTimeImmutable $now = null): bool
    {
        if (!$this->toBoolean($settings['enabled'] ?? false)) {
            return false;
        }

        $startsAt = trim((string)($settings['starts_at'] ?? ''));
        $endsAt = trim((string)($settings['ends_at'] ?? ''));

        if ($startsAt === '' && $endsAt === '') {
            return true;
        }

        $start = $this->parseStoredTimestamp($startsAt);
        $end = $this->parseStoredTimestamp($endsAt);

        if (!$start || !$end || $end <= $start) {
            return false;
        }

        $current = $now ?: new DateTimeImmutable('now', $start->getTimezone());

        return $current >= $start && $current <= $end;
    }

    private function adminPayload(array $row): array
    {
        return (new SystemMaintenance($row))->toArray() + [
            'active' => $this->isActive($row),
        ];
    }

    private function summaryFromRow(array $row): array
    {
        return $this->adminPayload($row);
    }

    private function toBoolean($value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'TRUE', 'on', 'ON'], true);
    }

    private function storageTimestamp($value): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') return null;

        $date = $this->parseInputTimestamp($value);
        if (!$date) {
            throw new RuntimeException('Maintenance times must be valid date and time values.');
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function parseInputTimestamp(string $value): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone(date_default_timezone_get() ?: 'UTC');

        foreach (['!Y-m-d\\TH:i', '!Y-m-d\\TH:i:s', '!Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

            if ($date && !$hasErrors) return $date;
        }

        return null;
    }

    private function parseStoredTimestamp(string $value): ?DateTimeImmutable
    {
        if ($value === '') return null;

        $date = $this->parseInputTimestamp($value);
        return $date ?: null;
    }

    private function validationMessage(array $errors): string
    {
        $first = reset($errors);
        if (is_array($first)) {
            return implode(' ', array_map('strval', $first));
        }

        return (string)$first;
    }
}
