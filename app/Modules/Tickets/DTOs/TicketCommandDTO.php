<?php

namespace App\Modules\Tickets\DTOs;

final class TicketCommandDTO
{
    public function __construct(private array $data)
    {
        foreach (['ticket_id', 'assigned_user_id'] as $field) {
            if (isset($this->data[$field])) $this->data[$field] = (int)$this->data[$field];
        }
        foreach (['status', 'priority'] as $field) {
            if (isset($this->data[$field])) $this->data[$field] = strtoupper(trim((string)$this->data[$field]));
        }
        foreach (['message', 'note'] as $field) {
            if (isset($this->data[$field])) $this->data[$field] = trim((string)$this->data[$field]);
        }
    }

    public function with(string $key, $value): self
    {
        $data = $this->data;
        $data[$key] = $value;
        return new self($data);
    }

    public function toArray(): array { return $this->data; }
}
