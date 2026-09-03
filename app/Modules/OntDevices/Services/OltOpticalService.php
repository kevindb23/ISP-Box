<?php

namespace App\Modules\OntDevices\Services;

use App\Infrastructure\NetworkAutomation\NetworkCommandRunner;
use RuntimeException;

class OltOpticalService
{
    public function __construct(private NetworkCommandRunner $networkRunner)
    {
    }

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

        $execution = $this->networkRunner->runPythonJson($script, [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'frame' => $frame,
            'slot' => $slot,
            'port' => $port,
            'ont_id' => $ontId,
            'ssh_port' => $sshPort,
        ]);
        $output = $execution->stdout !== '' ? $execution->stdout : $execution->stderr;

        if ($output === '') {
            throw new RuntimeException('Empty output from OLT optical script.');
        }

        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid response from OLT optical automation process.');
        }

        if (!($decoded['success'] ?? false)) {
            throw new RuntimeException($decoded['error'] ?? 'OLT optical fetch failed.');
        }

        return $decoded['data'] ?? [];
    }

    public function fetchOpticalInfoBySerial(
        string $host,
        string $username,
        string $password,
        string $serial,
        int $sshPort = 22
    ): array {
        $script = realpath(__DIR__ . '/../Scripts/olt_optical_info.py');

        if (!$script || !is_file($script)) {
            throw new RuntimeException('OLT optical script not found.');
        }

        $execution = $this->networkRunner->runPythonJson($script, [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'serial' => strtoupper(trim($serial)),
            'ssh_port' => $sshPort,
        ]);
        $output = $execution->stdout !== '' ? $execution->stdout : $execution->stderr;
        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid response from OLT optical automation process.');
        }

        if (!($decoded['success'] ?? false)) {
            throw new RuntimeException($decoded['error'] ?? 'ONT serial lookup failed on the OLT.');
        }

        return $decoded['data'] ?? [];
    }
}
