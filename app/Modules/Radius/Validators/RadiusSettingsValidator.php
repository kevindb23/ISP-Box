<?php

namespace App\Modules\Radius\Validators;

use App\Modules\Radius\DTOs\RadiusSettingsDTO;

final class RadiusSettingsValidator
{
    public function validate(RadiusSettingsDTO $dto): array
    {
        $errors = [];
        if ($dto->host === '') $errors['host'] = 'Host is required.';
        if ($dto->databaseUser === '') $errors['db_user'] = 'Database username is required.';
        if ($dto->databasePassword === '') $errors['db_password'] = 'Database password is required.';
        if ($dto->databaseName === '') $errors['db_name'] = 'Database name is required.';
        return $errors;
    }
}
