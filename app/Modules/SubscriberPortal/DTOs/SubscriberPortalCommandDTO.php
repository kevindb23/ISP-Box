<?php

namespace App\Modules\SubscriberPortal\DTOs;

final class SubscriberPortalCommandDTO
{
    public function __construct(private array $data)
    {
        foreach (['category', 'subject', 'description', 'message', 'preferred_visit_date', 'preferred_visit_time', 'preferred_visit_notes'] as $key) {
            if (array_key_exists($key, $this->data)) $this->data[$key] = trim((string)$this->data[$key]);
        }
        if (isset($this->data['category'])) $this->data['category'] = strtoupper($this->data['category']);
        foreach (['ticket_id', 'service_id'] as $key) {
            if (isset($this->data[$key])) $this->data[$key] = (int)$this->data[$key];
        }
    }

    public function toArray(): array { return $this->data; }
}
