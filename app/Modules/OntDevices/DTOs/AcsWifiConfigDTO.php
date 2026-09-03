<?php

namespace App\Modules\OntDevices\DTOs;

final class AcsWifiConfigDTO
{
    public static function fromArray(array $data): array
    {
        $payload = [];
        foreach (['deviceId', 'ssid', 'password', 'channel', 'tx_power', 'beacon_type', 'encryption'] as $key) {
            $source = $key === 'deviceId' ? ($data['deviceId'] ?? $data['device_id'] ?? '') : ($data[$key] ?? '');
            $payload[$key] = trim((string)$source);
        }
        foreach (['enable', 'radio_enabled', 'hide_ssid', 'auto_channel', 'wps_enable', 'wmm_enable', 'mac_filter_enable'] as $key) {
            $payload[$key] = !array_key_exists($key, $data) || $data[$key] === ''
                ? null
                : in_array(strtolower(trim((string)$data[$key])), ['1', 'true', 'on', 'yes'], true);
        }
        return $payload;
    }
}
