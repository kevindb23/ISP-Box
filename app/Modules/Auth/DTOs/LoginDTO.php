<?php

namespace App\Modules\Auth\DTOs;

class LoginDTO
{
    public string $username;
    public string $password;
    public ?string $csrfToken;

    public function __construct(array $data = [])
    {
        $this->username = trim((string)($data['username'] ?? ''));
        $this->password = (string)($data['password'] ?? '');
        $token = trim((string)($data['csrf_token'] ?? ''));
        $this->csrfToken = $token !== '' ? $token : null;
    }
}
