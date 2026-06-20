<?php

namespace App\Modules\CgnatManagement\Validators;

class CreateNatPoolValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        $poolName = trim((string)($data['pool_name'] ?? ''));
        $type = strtoupper(trim((string)($data['type'] ?? '')));
        $network = trim((string)($data['network'] ?? ''));
        $gateway = trim((string)($data['gateway'] ?? ''));
        $rangeStart = trim((string)($data['range_start'] ?? ''));
        $rangeEnd = trim((string)($data['range_end'] ?? ''));
        $accelPoolName = trim((string)($data['accel_pool_name'] ?? ''));
        $status = strtoupper(trim((string)($data['status'] ?? 'DRAFT')));

        if ($poolName === '') {
            $errors['pool_name'] = 'NAT pool name is required.';
        }

        if (!in_array($type, ['CGNAT', 'PUBLIC', 'MANAGEMENT'], true)) {
            $errors['type'] = 'Invalid NAT pool type.';
        }

        if ($network === '') {
            $errors['network'] = 'Network is required.';
        } elseif (!$this->isValidCidr($network)) {
            $errors['network'] = 'Network must be a valid CIDR.';
        }

        if ($gateway !== '' && !filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $errors['gateway'] = 'Gateway must be a valid IPv4 address.';
        }

        if ($rangeStart !== '' && !filter_var($rangeStart, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $errors['range_start'] = 'Range start must be a valid IPv4 address.';
        }

        if ($rangeEnd !== '' && !filter_var($rangeEnd, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $errors['range_end'] = 'Range end must be a valid IPv4 address.';
        }

        if ($type === 'CGNAT' && $accelPoolName === '') {
            $errors['accel_pool_name'] = 'ACCEL pool name is required for CGNAT pools.';
        }

        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'DRAFT'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        return $errors;
    }

    private function isValidCidr(string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$ip, $prefix] = explode('/', $cidr, 2);

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (!is_numeric($prefix)) {
            return false;
        }

        $prefix = (int)$prefix;
        return $prefix >= 0 && $prefix <= 32;
    }
}