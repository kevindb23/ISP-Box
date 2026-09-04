<?php

namespace App\Modules\ScheduledDowntime\DTOs;

class CreateScheduledDowntimeDTO
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromArray(array $data): self
    {
        return new self([
            'title' => trim((string)($data['title'] ?? '')),
            'message' => trim((string)($data['message'] ?? '')),
            'starts_at' => trim((string)($data['starts_at'] ?? '')),
            'ends_at' => trim((string)($data['ends_at'] ?? '')),
            'enabled' => filter_var($data['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ]);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
