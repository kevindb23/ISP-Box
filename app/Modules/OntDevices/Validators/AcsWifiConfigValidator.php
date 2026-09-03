<?php

namespace App\Modules\OntDevices\Validators;

final class AcsWifiConfigValidator
{
    public static function validate(array $data): array
    {
        $errors = [];
        if (($data['deviceId'] ?? '') === '') $errors[] = 'Device ID missing.';
        if (($data['ssid'] ?? '') === '') $errors[] = 'SSID is required.';
        if (strlen((string)($data['ssid'] ?? '')) > 32) $errors[] = 'SSID cannot exceed 32 characters.';
        if (($data['password'] ?? '') !== '' && strlen((string)$data['password']) < 8) $errors[] = 'WiFi password must contain at least 8 characters.';
        return $errors;
    }
}
