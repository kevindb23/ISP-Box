<?php

namespace App\Modules\Branding\Entities;

final class Branding
{
    public function __construct(private array $attributes)
    {
        $this->attributes['logo_text'] = trim((string)($attributes['portal_title'] ?? $attributes['logo_text'] ?? ''));
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
