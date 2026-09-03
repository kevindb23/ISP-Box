<?php

namespace App\Modules\OntDevices\Controllers;

use App\Modules\OntDevices\Services\AcsService;
use App\Modules\OntDevices\Services\OltOpticalService;
use App\Modules\OntDevices\Repositories\OntDevicesRepository;
use App\Modules\OntDevices\DTOs\AcsDeviceActionDTO;
use App\Modules\OntDevices\DTOs\AcsWanActionDTO;
use App\Modules\OntDevices\DTOs\AcsWifiConfigDTO;
use App\Modules\OntDevices\Validators\AcsDeviceActionValidator;
use App\Modules\OntDevices\Validators\AcsWanActionValidator;
use App\Modules\OntDevices\Validators\AcsWifiConfigValidator;
use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use Framework\ApiController;

class AcsActionsController extends ApiController
{
    private AcsService $acsService;
    private OltOpticalService $oltOpticalService;
    private OntDevicesRepository $repo;

    public function __construct(
        AcsService $acsService,
        OltOpticalService $oltOpticalService,
        OntDevicesRepository $repo,
        private AuditService $audit
    ) {
        $this->acsService = $acsService;
        $this->oltOpticalService = $oltOpticalService;
        $this->repo = $repo;
    }

    private function input(): array
    {
        return $this->request()->input();
    }

    private function deviceAction(): AcsDeviceActionDTO
    {
        return AcsDeviceActionDTO::fromArray($this->input());
    }

    public function devices(): void
    {
        try {
            $devices = $this->acsService->getDevices();
            $normalized = $this->acsService->normalizeDevices($devices);
            $this->acsService->syncSnapshots($normalized);

            $this->jsonOk('ACS devices loaded.', $normalized);
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to load ACS devices.');
        }
    }

    public function device($id): void
    {
        try {
            $device = $this->acsService->getDevice((string)$id);

            if (!$device) {
                $this->jsonError('Device not found.', 404);
                return;
            }

            $this->jsonOk('ACS device loaded.', $this->acsService->normalizeDevice($device));
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to load the ACS device.');
        }
    }

    public function parameters($id): void
    {
        try {
            $device = $this->acsService->getDevice((string)$id);

            if (!$device) {
                $this->jsonError('Device not found.', 404, []);
                return;
            }

            $this->jsonOk('Device parameters loaded.', $this->acsService->getUiParameters($device));
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to load ACS parameters.');
        }
    }

    public function optical($id): void
    {
        try {
            $device = $this->acsService->getDevice((string)$id);

            if (!$device) {
                $this->jsonError('ACS device not found.', 404);
                return;
            }

            $normalized = $this->acsService->normalizeDevice($device);
            $serial = trim((string)($normalized['serial_number'] ?? ''));

            if ($serial === '' || $serial === '-') {
                $this->jsonError('Serial number not found from ACS device.', 422);
                return;
            }

            $inventory = $this->repo->findOpticalMappingBySerial($serial);

            if (!$inventory) {
                $this->jsonError('ONT inventory record not found for this serial.', 404);
                return;
            }

            $oltId = isset($inventory['olt_id']) ? (int)$inventory['olt_id'] : 0;
            $frame = array_key_exists('frame', $inventory) && $inventory['frame'] !== null ? (int)$inventory['frame'] : null;
            $slot = array_key_exists('slot', $inventory) && $inventory['slot'] !== null ? (int)$inventory['slot'] : null;
            $port = array_key_exists('port', $inventory) && $inventory['port'] !== null ? (int)$inventory['port'] : null;
            $ontId = array_key_exists('ont_id', $inventory) && $inventory['ont_id'] !== null ? (int)$inventory['ont_id'] : null;

            if ($oltId <= 0 || $frame === null || $slot === null || $port === null || $ontId === null) {
                $resolved = null;
                $lookupErrors = [];
                foreach ($this->repo->getOpticalCandidateOlts($oltId > 0 ? $oltId : null) as $candidate) {
                    $candidateId = (int)($candidate['id'] ?? 0);
                    $candidateHost = trim((string)($candidate['ip_address'] ?? $candidate['host'] ?? ''));
                    $candidateUsername = trim((string)($candidate['username'] ?? ''));
                    $candidatePassword = trim((string)($candidate['password'] ?? ''));
                    $candidatePort = isset($candidate['ssh_port']) ? (int)$candidate['ssh_port'] : 22;
                    if ($candidateId <= 0 || $candidateHost === '' || $candidateUsername === '' || $candidatePassword === '') {
                        continue;
                    }

                    try {
                        $resolvedOptical = $this->oltOpticalService->fetchOpticalInfoBySerial(
                            $candidateHost,
                            $candidateUsername,
                            $candidatePassword,
                            $serial,
                            $candidatePort
                        );
                        $resolved = [
                            'olt_id' => $candidateId,
                            'frame' => (int)$resolvedOptical['frame'],
                            'slot' => (int)$resolvedOptical['slot'],
                            'port' => (int)$resolvedOptical['port'],
                            'ont_id' => (int)$resolvedOptical['ont_id'],
                            'optical' => $resolvedOptical,
                        ];
                        break;
                    } catch (\Throwable $lookupError) {
                        $lookupErrors[] = sprintf('%s: %s', (string)($candidate['name'] ?? $candidateHost), $lookupError->getMessage());
                    }
                }

                if ($resolved === null) {
                    $message = 'ONT mapping is incomplete and the serial was not found on any configured OLT.';
                    if ($lookupErrors !== []) {
                        $message .= ' ' . implode(' | ', $lookupErrors);
                    }
                    $this->jsonError($message, 422);
                    return;
                }

                $oltId = $resolved['olt_id'];
                $frame = $resolved['frame'];
                $slot = $resolved['slot'];
                $port = $resolved['port'];
                $ontId = $resolved['ont_id'];
                $this->repo->updateOpticalMappingById(
                    (int)$inventory['inventory_id'],
                    $oltId,
                    $frame,
                    $slot,
                    $port,
                    $ontId
                );
                $optical = $resolved['optical'];
            }

            $olt = $this->repo->findOltById($oltId);

            if (!$olt) {
                $this->jsonError('OLT device not found.', 404);
                return;
            }

            $host = trim((string)($olt['ip_address'] ?? $olt['host'] ?? ''));
            $username = trim((string)($olt['username'] ?? ''));
            $password = trim((string)($olt['password'] ?? ''));
            $sshPort = isset($olt['ssh_port']) && $olt['ssh_port'] !== null ? (int)$olt['ssh_port'] : 22;

            if ($host === '' || $username === '' || $password === '') {
                $this->jsonError('OLT credentials are incomplete.', 422);
                return;
            }

            if (!isset($optical)) {
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
            }

            $this->repo->updateLastOpticalById((int)$inventory['inventory_id'], $optical);
            $this->auditAction('OPTICAL_REFRESH', (string)$id, [
                'serial_number' => $serial,
                'olt_id' => $oltId,
                'frame' => $frame,
                'slot' => $slot,
                'port' => $port,
                'ont_id' => $ontId,
            ]);

            $this->jsonOk('OLT optical info loaded.', [
                    'serial_number' => $serial,
                    'olt_id' => $oltId,
                    'frame' => $frame,
                    'slot' => $slot,
                    'port' => $port,
                    'ont_id' => $ontId,
                    'optical' => $optical,
                ]);
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to refresh optical information.');
        }
    }

    public function refreshDevice(): void
    {
        $action = $this->deviceAction();
        $deviceId = $action->deviceId;

        if ($errors = AcsDeviceActionValidator::validate($action)) {
            $this->jsonError($errors[0], 422);
            return;
        }

        try {
            $this->acsService->refreshDevice($deviceId);
            $this->auditAction('ACS_REFRESH', $deviceId);

            $this->jsonOk('Refresh task sent.');
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to send the ACS refresh task.');
        }
    }

    public function reboot(): void
    {
        $action = $this->deviceAction();
        $deviceId = $action->deviceId;

        if ($errors = AcsDeviceActionValidator::validate($action)) {
            $this->jsonError($errors[0], 422);
            return;
        }

        try {
            $device = $this->acsService->getDevice($deviceId);

            if (!$device) {
                $this->jsonError('Device not found.', 404);
                return;
            }

            if (!$this->acsService->isRecentlyOnline($device, 300)) {
                $this->jsonError('Device offline.', 422);
                return;
            }

            $this->acsService->rebootDevice($deviceId);
            $this->auditAction('ACS_REBOOT', $deviceId);

            $this->jsonOk('Reboot command sent.');
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to send the reboot command.');
        }
    }

    public function factoryReset(): void
    {
        $action = $this->deviceAction();
        $deviceId = $action->deviceId;

        if ($errors = AcsDeviceActionValidator::validate($action)) {
            $this->jsonError($errors[0], 422);
            return;
        }

        try {
            $this->acsService->factoryResetDevice($deviceId);
            $this->auditAction('ACS_FACTORY_RESET', $deviceId);

            $this->jsonOk('Factory reset scheduled.');
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to schedule the factory reset.');
        }
    }

    public function ping(): void
    {
        $action = $this->deviceAction();
        $deviceId = $action->deviceId;

        if ($errors = AcsDeviceActionValidator::validate($action)) {
            $this->jsonError($errors[0], 422);
            return;
        }

        try {
            $result = $this->acsService->pingDevice($deviceId);
            $this->auditAction('ACS_PING', $deviceId);

            $this->jsonOk('ACS ping completed.', $result);
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to complete the ACS ping.');
        }
    }

    public function cachedOptical($id): void
    {
        try {
            $device = $this->acsService->getDevice((string)$id);

            if (!$device) {
                $this->jsonError('ACS device not found.', 404);
                return;
            }

            $normalized = $this->acsService->normalizeDevice($device);
            $serial = trim((string)($normalized['serial_number'] ?? ''));

            if ($serial === '' || $serial === '-') {
                $this->jsonError('Serial number not found from ACS device.', 422);
                return;
            }

            $cached = $this->repo->getCachedOpticalBySerial($serial);

            $this->jsonOk('Cached optical values loaded.', $cached);
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to load cached optical information.');
        }
    }

    private function postString(string $key, string $default = ''): string
    {
        return trim((string)($this->input()[$key] ?? $default));
    }

    private function postBoolOrNull(string $key): ?bool
    {
        if (!array_key_exists($key, $this->input())) {
            return null;
        }

        $raw = trim((string)$this->input()[$key]);
        if ($raw === '') {
            return null;
        }

        return in_array($raw, ['1', 'true', 'TRUE', 'on', 'yes'], true);
    }

    private function jsonError(string $message, int $status = 422, $data = null): void
    {
        $this->error($message, $status, [], $data);
    }

    private function jsonOk(string $message, $data = null): void
    {
        $this->success($data, $message);
    }

    private function unexpectedError(\Throwable $e, string $message = 'The ONT operation could not be completed.'): void
    {
        error_log('ONT ACS operation failed: ' . $e->getMessage());
        $this->jsonError($message, 500);
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
        $payload = AcsWanActionDTO::fromArray($this->input(), $isUpdate)->toArray();
        $errors = AcsWanActionValidator::validate($payload, $isUpdate);
        if ($errors !== []) throw new \InvalidArgumentException($errors[0]);
        return $payload;
    }

    public function createWan(): void
    {
        try {
            $payload = $this->buildWanPayloadFromPost(false);
            $result = $this->acsService->createWanInterface($payload);
            $this->auditAction('ACS_WAN_CREATE', $payload['deviceId'], [
                'name' => $payload['name'], 'type' => $payload['type'],
                'role' => $payload['role'], 'vlan_id' => $payload['vlan_id'],
            ]);
            $this->jsonOk('WAN interface created.', $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to create the WAN interface.');
        }
    }

    public function updateWan(): void
    {
        try {
            $payload = $this->buildWanPayloadFromPost(true);

            if ($payload['original_name'] === '') {
                throw new \InvalidArgumentException('Original WAN name is required.');
            }

            $result = $this->acsService->updateWanInterface($payload);
            $this->auditAction('ACS_WAN_UPDATE', $payload['deviceId'], [
                'name' => $payload['name'], 'original_name' => $payload['original_name'],
                'type' => $payload['type'], 'role' => $payload['role'],
                'vlan_id' => $payload['vlan_id'],
            ]);
            $this->jsonOk('WAN interface updated.', $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to update the WAN interface.');
        }
    }

    public function deleteWan(): void
    {
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
            $this->auditAction('ACS_WAN_DELETE', $deviceId, [
                'name' => $name, 'type' => $type, 'role' => $role,
            ]);
            $this->jsonOk('WAN interface deleted.', $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to delete the WAN interface.');
        }
    }

    public function wifiConfig(): void
    {
        $payload = AcsWifiConfigDTO::fromArray($this->input());
        $errors = AcsWifiConfigValidator::validate($payload);
        if ($errors !== []) {
            $this->jsonError($errors[0], 422);
            return;
        }

        $deviceId = $payload['deviceId'];
        $ssid = $payload['ssid'];
        $password = $payload['password'];
        $enable = $payload['enable'];
        $radioEnabled = $payload['radio_enabled'];
        $hideSsid = $payload['hide_ssid'];
        $autoChannel = $payload['auto_channel'];
        $channel = $payload['channel'];
        $txPower = $payload['tx_power'];
        $beaconType = $payload['beacon_type'];
        $encryption = $payload['encryption'];
        $wpsEnable = $payload['wps_enable'];
        $wmmEnable = $payload['wmm_enable'];
        $macFilterEnable = $payload['mac_filter_enable'];

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

            $this->auditAction('ACS_WIFI_UPDATE', $deviceId, [
                'ssid' => $ssid,
                'password_changed' => $password !== '',
                'enabled' => $enable,
                'radio_enabled' => $radioEnabled,
                'hide_ssid' => $hideSsid,
                'channel' => $channel !== '' ? $channel : null,
            ]);

            $this->jsonOk('WiFi configuration pushed.');
        } catch (\Throwable $e) {
            $this->unexpectedError($e, 'Unable to push the WiFi configuration.');
        }
    }

    private function auditAction(string $action, string $deviceId, array $metadata = []): void
    {
        try {
            $this->audit->logEvent(new AuditEventDTO(
                module: 'ONT_DEVICES',
                action: $action,
                description: sprintf('Executed %s for ACS device.', str_replace('_', ' ', strtolower($action))),
                objectType: 'ACS_DEVICE',
                objectId: null,
                metadata: ['device_id' => $deviceId] + $metadata
            ));
        } catch (\Throwable $e) {
            error_log('ONT ACS audit failed: ' . $e->getMessage());
        }
    }
}
