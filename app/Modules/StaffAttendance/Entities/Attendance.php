<?php

namespace App\Modules\StaffAttendance\Entities;

final class Attendance
{
    public function __construct(private array $attributes)
    {
        foreach (['id', 'user_id'] as $field) {
            if (isset($this->attributes[$field])) $this->attributes[$field] = (int)$this->attributes[$field];
        }
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
