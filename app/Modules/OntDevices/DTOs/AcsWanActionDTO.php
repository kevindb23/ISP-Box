<?php

namespace App\Modules\OntDevices\DTOs;

final class AcsWanActionDTO
{
    private function __construct(private array $data)
    {
    }

    public static function fromArray(array $data, bool $isUpdate = false): self
    {
        $type = strtoupper(trim((string)($data['type'] ?? 'DHCP')));
        $type = in_array($type, ['DHCP', 'STATIC'], true) ? $type : 'DHCP';
        $role = strtoupper(trim((string)($data['role'] ?? 'TR069')));
        $role = in_array($role, ['TR069', 'OTHER', 'IPTV', 'INTERNET'], true) ? $role : 'OTHER';

        $payload = [
            'deviceId' => trim((string)($data['deviceId'] ?? $data['device_id'] ?? '')),
            'name' => trim((string)($data['name'] ?? '')),
            'type' => $type,
            'role' => $role,
            'vlan_id' => trim((string)($data['vlan_id'] ?? '')),
            'service_list' => trim((string)($data['service_list'] ?? '')) ?: $role,
            'enabled' => self::boolOrNull($data, 'enabled'),
            'ip_address' => trim((string)($data['ip_address'] ?? '')),
            'subnet_mask' => trim((string)($data['subnet_mask'] ?? '')),
            'gateway' => trim((string)($data['gateway'] ?? '')),
            'dns' => trim((string)($data['dns'] ?? '')),
        ];

        if ($isUpdate) {
            $payload['original_name'] = trim((string)($data['original_name'] ?? ''));
        }

        return new self($payload);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function boolOrNull(array $data, string $key): ?bool
    {
        if (!array_key_exists($key, $data) || $data[$key] === '') return null;
        return in_array(strtolower(trim((string)$data[$key])), ['1', 'true', 'on', 'yes'], true);
    }
}
