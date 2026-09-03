<?php

namespace App\Modules\ApiTokens\Validators;

use App\Modules\ApiTokens\DTOs\CreateApiTokensDTO;

final class CreateApiTokensValidator
{
    public function validate(CreateApiTokensDTO $dto): array
    {
        $errors = [];
        if ($dto->userId <= 0) $errors['user_id'] = 'A valid user is required.';
        $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($dto->name === '' || $length($dto->name) > 100) $errors['name'] = 'Token name is required and must not exceed 100 characters.';
        if ($length($dto->description) > 255) $errors['description'] = 'Description must not exceed 255 characters.';
        $purposes = ['CENTRAL_MONITORING', 'EXTERNAL_INTEGRATION', 'NOC_INTEGRATION', 'READ_ONLY_MONITORING', 'CUSTOM'];
        if (!in_array($dto->purpose, $purposes, true)) $errors['purpose'] = 'Token purpose is invalid.';
        if (!in_array($dto->transportPolicy, ['HTTPS', 'HTTP', 'BOTH'], true)) $errors['transport_policy'] = 'Transport policy must be HTTPS, HTTP, or both.';
        $allowedScopes = ['infrastructure.monitoring.read', 'monitoring.read', 'dashboard.read', 'subscribers.read', 'sessions.read',
            'billing.summary.read', 'network.status.read', 'olt.status.read', 'ont.status.read',
            'provisioning.read', 'tickets.read'];
        if ($dto->scopes === [] || array_diff($dto->scopes, $allowedScopes) !== []) {
            $errors['scopes'] = 'Select one or more valid read-only scopes.';
        }
        if ($dto->expiresAt !== null && strtotime($dto->expiresAt) === false) {
            $errors['expires_at'] = 'Expiration date is invalid.';
        }
        return $errors;
    }
}
