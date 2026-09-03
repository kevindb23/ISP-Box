<?php

namespace App\Modules\OntDevices\DTOs;

final class AcsDeviceActionDTO
{
    private function __construct(public readonly string $deviceId)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(trim((string)($data['device_id'] ?? $data['deviceId'] ?? '')));
    }
}
