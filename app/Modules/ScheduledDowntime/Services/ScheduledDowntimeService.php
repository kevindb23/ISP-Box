<?php

namespace App\Modules\ScheduledDowntime\Services;

use App\Modules\ScheduledDowntime\DTOs\CreateScheduledDowntimeDTO;
use App\Modules\ScheduledDowntime\Entities\ScheduledDowntime;
use App\Modules\ScheduledDowntime\Repositories\ScheduledDowntimeRepository;
use App\Modules\ScheduledDowntime\Validators\CreateScheduledDowntimeValidator;
use DateTimeImmutable;
use RuntimeException;

class ScheduledDowntimeService
{
    public function __construct(
        private ScheduledDowntimeRepository $repo,
        private CreateScheduledDowntimeValidator $validator
    )
    {
    }

    public function list(): array
    {
        return array_map(
            static fn(array $row): array => (new ScheduledDowntime($row))->toArray(),
            $this->repo->all()
        );
    }

    public function create(array $input, int $userId): array
    {
        return $this->persist(CreateScheduledDowntimeDTO::fromArray($input)->toArray(), $userId);
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Scheduled downtime not found.');
        }

        $changes = CreateScheduledDowntimeDTO::fromArray($input)->toArray();
        if (!array_key_exists('enabled', $input)) {
            $changes['enabled'] = (int)($existing['enabled'] ?? 0);
        }
        $data = array_merge($existing, $changes);
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            throw new RuntimeException($this->validationMessage($errors));
        }

        $updated = $this->repo->update($id, $this->validator->normalise($data));
        if (!$updated) {
            throw new RuntimeException('Unable to update scheduled downtime.');
        }

        return (new ScheduledDowntime($updated))->toArray();
    }

    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Scheduled downtime not found.');
        }
    }

    public function toggle(int $id, bool $enabled): array
    {
        $updated = $this->repo->toggle($id, $enabled);
        if (!$updated) {
            throw new RuntimeException('Scheduled downtime not found.');
        }

        return (new ScheduledDowntime($updated))->toArray();
    }

    public function activeWindow(?DateTimeImmutable $now = null): ?array
    {
        $now ??= new DateTimeImmutable();
        $row = $this->repo->activeWindow($now->format('Y-m-d H:i:s'));

        return $row ? (new ScheduledDowntime($row))->toArray() : null;
    }

    private function persist(array $input, int $userId): array
    {
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            throw new RuntimeException($this->validationMessage($errors));
        }

        $created = $this->repo->create($this->validator->normalise($input), $userId);
        if (!$created) {
            throw new RuntimeException('Unable to create scheduled downtime.');
        }

        return (new ScheduledDowntime($created))->toArray();
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
