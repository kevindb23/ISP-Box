<?php

namespace App\Modules\OntDevices\Controllers;

use App\Modules\OntDevices\Services\AcsService;
use App\Modules\OntDevices\Services\OltOpticalService;
use App\Modules\OntDevices\Repositories\OntDevicesRepository;

class AcsActionsController
{
    private AcsService $acsService;
    private OltOpticalService $oltOpticalService;
    private OntDevicesRepository $repo;

    public function __construct(
        AcsService $acsService,
        OltOpticalService $oltOpticalService,
        OntDevicesRepository $repo
    ) {
        $this->acsService = $acsService;
        $this->oltOpticalService = $oltOpticalService;
        $this->repo = $repo;
    }

    public function devices(): void
    {
        header('Content-Type: application/json');

        try {
            $devices = $this->acsService->getDevices();
            $normalized = $this->acsService->normalizeDevices($devices);

            echo json_encode([
                'ok' => true,
                'message' => 'ACS devices loaded.',
                'data' => $normalized,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function device($id): void
    {
        header('Content-Type: application/json');

        try {
            $device = $this->acsService->getDevice((string)$id);

            if (!$device) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Device not found.',
                    'data' => null,
                ]);
                return;
            }

            echo json_encode([
                'ok' => true,
                'message' => 'ACS device loaded.',
                'data' => $this->acsService->normalizeDevice($device),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }

    public function parameters($id): void
    {
        header('Content-Type: application/json');

        try {
            $device = $this->acsService->getDevice((string)$id);

            if (!$device) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Device not found.',
                    'data' => [],
                ]);
                return;
            }

            echo json_encode([
                'ok' => true,
                'message' => 'Device parameters loaded.',
                'data' => $device,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function optical($id): void
    {
        header('Content-Type: application/json');

        try {
            $device = $this->acsService->getDevice((string)$id);

            if (!$device) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'ACS device not found.',
                    'data' => null,
                ]);
                return;
            }

            $normalized = $this->acsService->normalizeDevice($device);
            $serial = trim((string)($normalized['serial_number'] ?? ''));

            if ($serial === '' || $serial === '-') {
                http_response_code(422);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Serial number not found from ACS device.',
                    'data' => null,
                ]);
                return;
            }

            $inventory = $this->repo->findBySerial($serial);

            if (!$inventory) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'ONT inventory record not found for this serial.',
                    'data' => null,
                ]);
                return;
            }

            $oltId = isset($inventory['olt_id']) ? (int)$inventory['olt_id'] : 0;
            $frame = array_key_exists('frame', $inventory) && $inventory['frame'] !== null ? (int)$inventory['frame'] : null;
            $slot = array_key_exists('slot', $inventory) && $inventory['slot'] !== null ? (int)$inventory['slot'] : null;
            $port = array_key_exists('port', $inventory) && $inventory['port'] !== null ? (int)$inventory['port'] : null;
            $ontId = array_key_exists('ont_id', $inventory) && $inventory['ont_id'] !== null ? (int)$inventory['ont_id'] : null;

            if ($oltId <= 0 || $frame === null || $slot === null || $port === null || $ontId === null) {
                http_response_code(422);
                echo json_encode([
                    'ok' => false,
                    'message' => 'ONT optical lookup is not yet fully mapped. Missing olt_id/frame/slot/port/ont_id in inventory.',
                    'data' => null,
                ]);
                return;
            }

            $olt = $this->repo->findOltById($oltId);

            if (!$olt) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'OLT device not found.',
                    'data' => null,
                ]);
                return;
            }

            $host = trim((string)($olt['ip_address'] ?? $olt['host'] ?? ''));
            $username = trim((string)($olt['username'] ?? ''));
            $password = trim((string)($olt['password'] ?? ''));
            $sshPort = isset($olt['ssh_port']) && $olt['ssh_port'] !== null ? (int)$olt['ssh_port'] : 22;

            if ($host === '' || $username === '' || $password === '') {
                http_response_code(422);
                echo json_encode([
                    'ok' => false,
                    'message' => 'OLT credentials are incomplete.',
                    'data' => null,
                ]);
                return;
            }

            $optical = $this->oltOpticalService->fetchOpticalInfo(
                $host,
                $username,
                $password,
                $frame,
                $slot,
                $port,
                $ontId,
                $sshPort
            );

            $this->repo->updateLastOpticalById((int)$inventory['id'], $optical);

            echo json_encode([
                'ok' => true,
                'message' => 'OLT optical info loaded.',
                'data' => [
                    'serial_number' => $serial,
                    'olt_id' => $oltId,
                    'frame' => $frame,
                    'slot' => $slot,
                    'port' => $port,
                    'ont_id' => $ontId,
                    'optical' => $optical,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }

    public function refreshDevice(): void
    {
        header('Content-Type: application/json');

        $deviceId = trim((string)($_POST['device_id'] ?? $_POST['deviceId'] ?? ''));

        if ($deviceId === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Device ID missing.',
            ]);
            return;
        }

        try {
            $this->acsService->refreshDevice($deviceId);

            echo json_encode([
                'ok' => true,
                'message' => 'Refresh task sent.',
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function reboot(): void
    {
        header('Content-Type: application/json');

        $deviceId = trim((string)($_POST['device_id'] ?? $_POST['deviceId'] ?? ''));

        if ($deviceId === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Device ID missing.'
            ]);
            return;
        }

        try {
            $device = $this->acsService->getDevice($deviceId);

            if (!$device) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Device not found.'
                ]);
                return;
            }

            if (!$this->acsService->isRecentlyOnline($device, 300)) {
                http_response_code(422);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Device offline.'
                ]);
                return;
            }

            $this->acsService->rebootDevice($deviceId);

            echo json_encode([
                'ok' => true,
                'message' => 'Reboot command sent.',
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function factoryReset(): void
    {
        header('Content-Type: application/json');

        $deviceId = trim((string)($_POST['device_id'] ?? $_POST['deviceId'] ?? ''));

        if ($deviceId === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Device ID missing.'
            ]);
            return;
        }

        try {
            $this->acsService->factoryResetDevice($deviceId);

            echo json_encode([
                'ok' => true,
                'message' => 'Factory reset scheduled.',
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function ping(): void
    {
        header('Content-Type: application/json');

        $deviceId = trim((string)($_POST['device_id'] ?? $_POST['deviceId'] ?? ''));

        if ($deviceId === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Device ID missing.',
                'data' => null,
            ]);
            return;
        }

        try {
            $result = $this->acsService->pingDevice($deviceId);

            echo json_encode([
                'ok' => true,
                'message' => 'ACS ping completed.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }

    public function cachedOptical($id): void
    {
        header('Content-Type: application/json');

        try {
            $device = $this->acsService->getDevice((string)$id);

            if (!$device) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'ACS device not found.',
                    'data' => null,
                ]);
                return;
            }

            $normalized = $this->acsService->normalizeDevice($device);
            $serial = trim((string)($normalized['serial_number'] ?? ''));

            if ($serial === '' || $serial === '-') {
                http_response_code(422);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Serial number not found from ACS device.',
                    'data' => null,
                ]);
                return;
            }

            $cached = $this->repo->getCachedOpticalBySerial($serial);

            echo json_encode([
                'ok' => true,
                'message' => 'Cached optical values loaded.',
                'data' => $cached,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }

    private function postString(string $key, string $default = ''): string
    {
        return trim((string)($_POST[$key] ?? $default));
    }

    private function postBoolOrNull(string $key): ?bool
    {
        if (!array_key_exists($key, $_POST)) {
            return null;
        }

        $raw = trim((string)$_POST[$key]);
        if ($raw === '') {
            return null;
        }

        return in_array($raw, ['1', 'true', 'TRUE', 'on', 'yes'], true);
    }

    private function jsonError(string $message, int $status = 422, $data = null): void
    {
        http_response_code($status);
        echo json_encode([
            'ok' => false,
            'message' => $message,
            'data' => $data,
        ]);
    }

    private function jsonOk(string $message, $data = null): void
    {
        echo json_encode([
            'ok' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    private function normalizeWanType(string $type): string
    {
        $safe = strtoupper(trim($type));
        return in_array($safe, ['DHCP', 'STATIC'], true) ? $safe : 'DHCP';
    }

    private function normalizeWanRole(string $role): string
    {
        $safe = strtoupper(trim($role));
        return in_array($safe, ['TR069', 'OTHER', 'IPTV', 'INTERNET'], true) ? $safe : 'OTHER';
    }

    private function assertEditableWanRequest(string $type, string $role): void
    {
        $safeType = strtoupper(trim($type));
        $safeRole = strtoupper(trim($role));

        if ($safeType === 'PPPOE' || $safeRole === 'INTERNET') {
            throw new \RuntimeException('Subscriber PPPoE WAN must be managed from Service Provisioning.');
        }
    }

    private function buildWanPayloadFromPost(bool $isUpdate = false): array
    {
        $payload = [
            'deviceId'     => $this->postString('deviceId'),
            'name'         => $this->postString('name'),
            'type'         => $this->normalizeWanType($this->postString('type', 'DHCP')),
            'role'         => $this->normalizeWanRole($this->postString('role', 'TR069')),
            'vlan_id'      => $this->postString('vlan_id'),
            'service_list' => $this->postString('service_list'),
            'enabled'      => $this->postBoolOrNull('enabled'),
            'ip_address'   => $this->postString('ip_address'),
            'subnet_mask'  => $this->postString('subnet_mask'),
            'gateway'      => $this->postString('gateway'),
            'dns'          => $this->postString('dns'),
        ];

        if ($isUpdate) {
            $payload['original_name'] = $this->postString('original_name');
        }

        if ($payload['deviceId'] === '') {
            throw new \InvalidArgumentException('Device ID missing.');
        }

        if ($payload['name'] === '') {
            throw new \InvalidArgumentException('WAN Name is required.');
        }

        if ($payload['vlan_id'] === '') {
            throw new \InvalidArgumentException('VLAN ID is required.');
        }

        if ($payload['service_list'] === '') {
            $payload['service_list'] = $payload['role'];
        }

        if ($payload['type'] === 'STATIC') {
            if ($payload['ip_address'] === '' || $payload['subnet_mask'] === '' || $payload['gateway'] === '') {
                throw new \InvalidArgumentException('Static WAN requires IP Address, Subnet Mask, and Gateway.');
            }
        }

        $this->assertEditableWanRequest($payload['type'], $payload['role']);

        return $payload;
    }

    public function createWan(): void
    {
        header('Content-Type: application/json');

        try {
            $payload = $this->buildWanPayloadFromPost(false);
            $result = $this->acsService->createWanInterface($payload);
            $this->jsonOk('WAN interface created.', $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function updateWan(): void
    {
        header('Content-Type: application/json');

        try {
            $payload = $this->buildWanPayloadFromPost(true);

            if ($payload['original_name'] === '') {
                throw new \InvalidArgumentException('Original WAN name is required.');
            }

            $result = $this->acsService->updateWanInterface($payload);
            $this->jsonOk('WAN interface updated.', $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function deleteWan(): void
    {
        header('Content-Type: application/json');

        try {
            $deviceId = $this->postString('deviceId');
            $name = $this->postString('name');
            $role = $this->normalizeWanRole($this->postString('role', 'OTHER'));
            $type = strtoupper($this->postString('type', 'DHCP'));

            if ($deviceId === '') {
                throw new \InvalidArgumentException('Device ID missing.');
            }

            if ($name === '') {
                throw new \InvalidArgumentException('WAN Name is required.');
            }

            $this->assertEditableWanRequest($type, $role);

            $payload = [
                'deviceId' => $deviceId,
                'name' => $name,
                'role' => $role,
                'type' => $type,
            ];

            $result = $this->acsService->deleteWanInterface($payload);
            $this->jsonOk('WAN interface deleted.', $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function wifiConfig(): void
    {
        header('Content-Type: application/json');

        $deviceId = $this->postString('deviceId');
        $ssid = $this->postString('ssid');
        $password = $this->postString('password');
        $enable = $this->postBoolOrNull('enable');
        $radioEnabled = $this->postBoolOrNull('radio_enabled');
        $hideSsid = $this->postBoolOrNull('hide_ssid');
        $autoChannel = $this->postBoolOrNull('auto_channel');
        $channel = $this->postString('channel');
        $txPower = $this->postString('tx_power');
        $beaconType = $this->postString('beacon_type');
        $encryption = $this->postString('encryption');
        $wpsEnable = $this->postBoolOrNull('wps_enable');
        $wmmEnable = $this->postBoolOrNull('wmm_enable');
        $macFilterEnable = $this->postBoolOrNull('mac_filter_enable');

        if ($deviceId === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Device ID missing.'
            ]);
            return;
        }

        if ($ssid === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'SSID is required.'
            ]);
            return;
        }

        try {
            $this->acsService->wifiConfig(
                $deviceId,
                $ssid,
                $password !== '' ? $password : null,
                $enable,
                $radioEnabled,
                $hideSsid,
                $autoChannel,
                $channel !== '' ? $channel : null,
                $txPower !== '' ? $txPower : null,
                $beaconType !== '' ? $beaconType : null,
                $encryption !== '' ? $encryption : null,
                $wpsEnable,
                $wmmEnable,
                $macFilterEnable
            );

            echo json_encode([
                'ok' => true,
                'message' => 'WiFi configuration pushed.',
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}