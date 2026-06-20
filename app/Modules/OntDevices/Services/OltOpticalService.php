<?php

namespace App\Modules\OntDevices\Services;

use RuntimeException;

class OltOpticalService
{
    public function fetchOpticalInfo(
        string $host,
        string $username,
        string $password,
        int $frame,
        int $slot,
        int $port,
        int $ontId,
        int $sshPort = 22
    ): array {
        $script = realpath(__DIR__ . '/../Scripts/olt_optical_info.py');

        if (!$script || !is_file($script)) {
            throw new RuntimeException('OLT optical script not found.');
        }

        $command = sprintf(
            'python3 %s %s %s %s %d %d %d %d %d 2>&1',
            escapeshellarg($script),
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            $frame,
            $slot,
            $port,
            $ontId,
            $sshPort
        );

        $output = shell_exec($command);

        if ($output === null || trim($output) === '') {
            throw new RuntimeException('Empty output from OLT optical script.');
        }

        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON returned by OLT optical script: ' . $output);
        }

        if (!($decoded['success'] ?? false)) {
            throw new RuntimeException($decoded['error'] ?? 'OLT optical fetch failed.');
        }

        return $decoded['data'] ?? [];
    }
}