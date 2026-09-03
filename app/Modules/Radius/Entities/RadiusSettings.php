<?php

namespace App\Modules\Radius\Entities;

final class RadiusSettings
{
    public function __construct(private array $attributes)
    {
        unset($this->attributes['db_password'], $this->attributes['password']);
        if (array_key_exists('is_active', $this->attributes)) {
            $this->attributes['is_active'] = (bool)$this->attributes['is_active'];
        }
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
