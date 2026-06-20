<?php

namespace App\Modules\OltManagement\Validators;

class CreateOltManagementValidator
{
    public static function validateDevice(array $data): array
    {
        $errors = [];

        if (trim((string)($data['name'] ?? '')) === '') {
            $errors[] = 'OLT name is required.';
        }

        if (trim((string)($data['ip_address'] ?? '')) === '') {
            $errors[] = 'IP address is required.';
        }

        if (trim((string)($data['username'] ?? '')) === '') {
            $errors[] = 'Username is required.';
        }

        if (trim((string)($data['password'] ?? '')) === '') {
            $errors[] = 'Password is required.';
        }

        if (trim((string)($data['vendor'] ?? '')) === '') {
            $errors[] = 'Vendor is required.';
        }

        return $errors;
    }

    public static function validatePort(array $data): array
    {
        $errors = [];

        if ((int)($data['olt_id'] ?? 0) <= 0) {
            $errors[] = 'OLT device is required.';
        }

        if (!isset($data['frame']) || $data['frame'] === '') {
            $errors[] = 'Frame is required.';
        }

        if (!isset($data['slot']) || $data['slot'] === '') {
            $errors[] = 'Slot is required.';
        }

        if (!isset($data['port']) || $data['port'] === '') {
            $errors[] = 'Port is required.';
        }

        $boardType = strtoupper((string)($data['board_type'] ?? ''));

        if ($boardType !== 'CONTROL') {
            if (isset($data['svlan']) && $data['svlan'] !== null && $data['svlan'] !== '') {
                if ((int)$data['svlan'] <= 0) {
                    $errors[] = 'SVLAN must be a valid positive number.';
                }
            }
        }

        $allowedCsv = trim((string)($data['allowed_svlans_csv'] ?? ''));
        if ($boardType === 'CONTROL' && $allowedCsv !== '') {
            $parts = preg_split('/\s*,\s*/', $allowedCsv);
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if (!ctype_digit($part) || (int)$part <= 0) {
                    $errors[] = 'Allowed SVLANs must be comma-separated positive numbers only.';
                    break;
                }
            }
        }

        return $errors;
    }
}
