<?php

namespace App\Modules\Auth\Entities;

class AuthenticatedUser
{
    private string $passwordHash;

    public function __construct(private array $attributes)
    {
        $this->passwordHash = (string)($attributes['password'] ?? '');
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

    public function passwordMatches(string $password): bool
    {
        return $this->passwordHash !== '' && password_verify($password, $this->passwordHash);
    }

    public function isActive(): bool
    {
        return strtoupper((string)($this->attributes['status'] ?? 'DISABLED')) === 'ACTIVE';
    }

    public function toSessionArray(): array
    {
        return $this->attributes;
    }
}
