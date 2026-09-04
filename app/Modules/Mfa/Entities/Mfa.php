<?php

namespace App\Modules\Mfa\Entities;

class Mfa
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
