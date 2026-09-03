<?php

namespace App\Modules\CgnatManagement\Validators;

use Exception;

class UpdateCgnatValidator
{
    public function validate(array $data): void
    {
        if (empty($data['inside_network'])) {
            throw new Exception('Inside network is required.');
        }

        if (!filter_var($data['public_start_ip'] ?? null, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new Exception('Invalid public start IP.');
        }

        if (!filter_var($data['public_end_ip'] ?? null, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new Exception('Invalid public end IP.');
        }

        if (!$this->validCidr((string)($data['inside_network'] ?? ''))) {
            throw new Exception('Inside network must be a valid IPv4 CIDR.');
        }

        foreach (['bng_interface' => 'BNG interface', 'egress_interface' => 'Egress interface'] as $field => $label) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value === '' || !preg_match('/^[A-Za-z0-9_.:-]{1,32}$/', $value)) {
                throw new Exception($label . ' is required and must be a valid Linux interface name.');
            }
            if (str_contains($value, '.') || str_contains($value, '@') || preg_match('/^1WAN\d+$/i', $value)) {
                throw new Exception($label . ' must be a physical server interface, not a VLAN or Accel-PPP session interface.');
            }
        }

        $start = ip2long((string)$data['public_start_ip']);
        $end = ip2long((string)$data['public_end_ip']);
        if ($start === false || $end === false || (int)sprintf('%u', $start) > (int)sprintf('%u', $end)) {
            throw new Exception('Public IP range is reversed or invalid.');
        }
    }

    private function validCidr(string $value): bool
    {
        if (!preg_match('/^([^\/]+)\/(\d{1,2})$/', trim($value), $match)) return false;
        return filter_var($match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && (int)$match[2] >= 0 && (int)$match[2] <= 32;
    }
}
