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
    }
}