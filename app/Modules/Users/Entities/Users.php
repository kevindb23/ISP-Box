<?php

namespace App\Modules\Users\Entities;

final class Users
{
    public function __construct(private array $attributes)
    {
        unset($this->attributes['password']);
    }

    public function id(): int
    {
        return (int)($this->attributes['id'] ?? 0);
    }

    public function username(): string
    {
        return (string)($this->attributes['username'] ?? '');
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
