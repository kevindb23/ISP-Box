<?php

namespace App\Modules\Users\Validators;

use App\Modules\Users\DTOs\CreateUsersDTO;

final class CreateUsersValidator
{
    private const ROLES = ['SUPERADMIN', 'NOC', 'SUPPORT', 'BILLING', 'TECHNICIAN'];
    private const STATUSES = ['ACTIVE', 'DISABLED'];

    public function validate(CreateUsersDTO $dto, bool $requirePassword = true): array
    {
        $errors = [];

        if ($dto->username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $dto->username)) {
            $errors['username'] = 'Username must be 3-64 characters and may contain letters, numbers, dot, dash, or underscore only.';
        }

        if ($dto->fullName === '') {
            $errors['full_name'] = 'Full name is required.';
        }

        if ($dto->email !== null && !filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        if (!in_array($dto->role, self::ROLES, true)) {
            $errors['role'] = 'Invalid system role.';
        }

        if (!in_array($dto->status, self::STATUSES, true)) {
            $errors['status'] = 'Invalid user status.';
        }

        if ($requirePassword && $dto->password === '') {
            $errors['password'] = 'Password is required.';
        } elseif ($dto->password !== '' && strlen($dto->password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        return $errors;
    }
}
