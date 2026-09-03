<?php

namespace App\Modules\Users\DTOs;

final class CreateUsersDTO
{
    public string $username;
    public string $fullName;
    public ?string $email;
    public string $role;
    public string $status;
    public string $password;

    public function __construct(array $data = [])
    {
        $this->username = trim((string)($data['username'] ?? ''));
        $this->fullName = trim((string)($data['full_name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $this->email = $email !== '' ? $email : null;
        $this->role = strtoupper(trim((string)($data['role'] ?? 'SUPPORT')));
        $this->status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
        $this->password = (string)($data['password'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'password' => $this->password,
        ];
    }
}
