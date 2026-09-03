<?php

namespace App\Modules\Auth\Validators;

use App\Modules\Auth\DTOs\LoginDTO;

class LoginValidator
{
    public function validate(LoginDTO $dto): array
    {
        $errors = $this->validateCredentials($dto);

        if ($dto->csrfToken === null) {
            $errors['csrf_token'] = 'Security token is required.';
        }

        return $errors;
    }

    public function validateCredentials(LoginDTO $dto): array
    {
        $errors = [];

        if ($dto->username === '') {
            $errors['username'] = 'Username is required.';
        }

        if ($dto->password === '') {
            $errors['password'] = 'Password is required.';
        }

        return $errors;
    }
}
