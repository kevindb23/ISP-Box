<?php

namespace App\Modules\Subscribers\Validators;

class UpdateSubscriberValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        if (trim((string)($data['full_name'] ?? '')) === '') {
            $errors[] = 'Full name is required.';
        }

        if ((int)($data['plan_id'] ?? 0) <= 0) {
            $errors[] = 'Plan is required.';
        }

        $email = trim((string)($data['email'] ?? ''));
        if ($email === '') {
            $errors[] = 'Subscriber email is required for portal login.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }

        return $errors;
    }
}
