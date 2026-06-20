<?php

namespace App\Modules\OntDevices\Services;

class OltService
{
    public function discover(): array
    {
        $script = __DIR__ . '/../Scripts/olt_autofind.py';
        $command = 'python3 ' . escapeshellarg($script) . ' 2>&1';

        $output = shell_exec($command);

        if (!$output) {
            file_put_contents(
                __DIR__ . '/../../../../storage/logs/olt_error.log',
                '[' . date('Y-m-d H:i:s') . "] Empty output from OLT autofind script\n",
                FILE_APPEND
            );
            return [];
        }

        $data = json_decode($output, true);

        if (!is_array($data) || !($data['success'] ?? false)) {
            file_put_contents(
                __DIR__ . '/../../../../storage/logs/olt_error.log',
                '[' . date('Y-m-d H:i:s') . '] ' . $output . "\n",
                FILE_APPEND
            );
            return [];
        }

        return $data['data'] ?? [];
    }
}