<?php

namespace App\Modules\OntDevices\Validators;

use App\Modules\OntDevices\DTOs\AcsDeviceActionDTO;

final class AcsDeviceActionValidator
{
    public static function validate(AcsDeviceActionDTO $dto): array
    {
        return $dto->deviceId === '' ? ['Device ID missing.'] : [];
    }
}
