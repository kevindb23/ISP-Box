<?php

namespace App\Modules\Notifications\Entities;

class Notifications
{
    private array $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
