<?php

namespace App\Modules\Mfa\Validators;

class CreateMfaValidator
{
    public function validate(array $input): array
    {
        $errors = [];

        if (!in_array(strtoupper((string)($input['method'] ?? '')), ['AUTHENTICATOR', 'EMAIL'], true)) {
            $errors['method'] = 'Choose authenticator app or email OTP.';
        }

        return $errors;
    }
}
