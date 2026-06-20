<?php

namespace App\Modules\OltManagement\Services;

use App\Modules\OltManagement\DTOs\CreateOltManagementDTO;
use App\Modules\OltManagement\Repositories\OltManagementRepository;
use App\Modules\OltManagement\Validators\CreateOltManagementValidator;

class OltManagementService
{
    private OltManagementRepository $repo;

    public function __construct(OltManagementRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getIndexData(?int $selectedOltId = null, ?int $selectedSlot = null): array
    {
        $devices = $this->getAllDevices();
        $allPortsForOlt = $selectedOltId && $selectedOltId > 0
            ? $this->getPortsByOltId($selectedOltId)
            : [];

        $selectedSlotPorts = [];
        $portGrid = [];

        if ($selectedSlot !== null) {
            foreach ($allPortsForOlt as $row) {
                if ((int)($row['slot'] ?? -1) === $selectedSlot) {
                    $selectedSlotPorts[] = $row;
                }
            }

            if (!empty($selectedSlotPorts)) {
                $portGrid[(string)$selectedSlot] = $selectedSlotPorts;
            }
        }

        $chassisSlots = [];
        $knownSlots = [];

        foreach ($allPortsForOlt as $p) {
            $slotNo = (int)($p['slot'] ?? -1);
            if ($slotNo < 0) {
                continue;
            }

            if (!isset($knownSlots[$slotNo])) {
                $knownSlots[$slotNo] = [
                    'slot' => $slotNo,
                    'board_name' => $p['board_name'] ?? 'BOARD',
                    'board_status' => $p['board_status'] ?? $this->deriveBoardStatus($p),
                    'is_gpon' => strtoupper((string)($p['board_type'] ?? '')) === 'GPON',
                    'port_count' => 0,
                    'ports' => [],
                ];
            }

            $knownSlots[$slotNo]['port_count']++;
            $knownSlots[$slotNo]['ports'][] = $p;
        }

        for ($i = 0; $i <= 4; $i++) {
            if (isset($knownSlots[$i])) {
                $slotInfo = $knownSlots[$i];
                $slotInfo['is_selected'] = ($selectedSlot !== null && $selectedSlot === $i);
                $slotInfo['slot_path'] = '0/' . $i;
                $chassisSlots[$i] = $slotInfo;
            } else {
                $defaultName = 'EMPTY / UNKNOWN';
                $defaultStatus = 'UNKNOWN';

                if ($i === 3 || $i === 4) {
                    $defaultName = 'CONTROL / UPLINK SLOT';
                }

                if ($i === 0) {
                    $defaultName = 'SERVICE / POWER SLOT';
                }

                $chassisSlots[$i] = [
                    'slot' => $i,
                    'slot_path' => '0/' . $i,
                    'board_name' => $defaultName,
                    'board_status' => $defaultStatus,
                    'is_gpon' => false,
                    'port_count' => 0,
                    'ports' => [],
                    'is_selected' => ($selectedSlot !== null && $selectedSlot === $i),
                ];
            }
        }

        return [
            'oltDevices' => $devices,
            'oltPorts' => $selectedSlotPorts,
            'selectedOltId' => $selectedOltId,
            'selectedSlot' => $selectedSlot,
            'portGrid' => $portGrid,
            'chassisSlots' => $chassisSlots,
            'allPortsForOlt' => $allPortsForOlt,
        ];
    }

    public function getAllDevices(): array
    {
        return $this->repo->getAllDevices();
    }

    public function getDeviceById(int $id): ?array
    {
        return $this->repo->findDeviceById($id);
    }

    public function getAllPorts(): array
    {
        return method_exists($this->repo, 'getAllPorts')
            ? $this->repo->getAllPorts()
            : [];
    }

    public function getPortsByOltId(int $oltId): array
    {
        return $this->repo->getPortsByOltId($oltId);
    }

    public function getPortById(int $id): ?array
    {
        return $this->repo->findPortById($id);
    }

    public function createDevice(array $input): array
    {
        $dto = CreateOltManagementDTO::fromDevice($input);
        $payload = $this->mergeDeviceOmciPayload($dto->toArray(), $input);

        $errors = CreateOltManagementValidator::validateDevice($payload);
        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $omciResult = $this->applyOmciIfNeeded($payload, null);

        if (!($omciResult['success'] ?? true)) {
            return [
                'ok' => false,
                'message' => $omciResult['message'] ?? 'Failed to apply OMCI config to OLT.',
                'errors' => [],
                'olt_output' => $omciResult,
            ];
        }

        $id = $this->repo->createDevice($payload);

        return [
            'ok' => true,
            'message' => 'OLT device created.',
            'id' => $id,
            'olt_output' => $omciResult,
            'errors' => [],
        ];
    }

    public function updateDevice(int $id, array $input): array
    {
        $existing = $this->repo->findDeviceById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT device not found.',
                'errors' => [],
            ];
        }

        $dto = CreateOltManagementDTO::fromDevice($input);
        $payload = $this->mergeDeviceOmciPayload($dto->toArray(), $input, $existing);

        $errors = CreateOltManagementValidator::validateDevice($payload);
        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $omciResult = $this->applyOmciIfNeeded($payload, $existing);

        if (!($omciResult['success'] ?? true)) {
            return [
                'ok' => false,
                'message' => $omciResult['message'] ?? 'Failed to apply OMCI config to OLT.',
                'errors' => [],
                'olt_output' => $omciResult,
            ];
        }

        $this->repo->updateDevice($id, $payload);

        return [
            'ok' => true,
            'message' => 'OLT device updated.',
            'olt_output' => $omciResult,
            'errors' => [],
        ];
    }

    public function deleteDevice(int $id): array
    {
        $existing = $this->repo->findDeviceById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT device not found.',
                'errors' => [],
            ];
        }

        if ($this->repo->deviceHasPorts($id)) {
            return [
                'ok' => false,
                'message' => 'Cannot delete OLT device with existing ports.',
                'errors' => [],
            ];
        }

        $this->repo->deleteDevice($id);

        return [
            'ok' => true,
            'message' => 'OLT device deleted.',
            'errors' => [],
        ];
    }

    public function createPort(array $input): array
    {
        $dto = CreateOltManagementDTO::fromPort($input);
        $payload = $dto->toArray();
        $payload['allowed_svlans_csv'] = trim((string)($input['allowed_svlans_csv'] ?? ''));
        $payload['board_status'] = trim((string)($input['board_status'] ?? ''));
        $payload['ont_online'] = isset($input['ont_online']) && $input['ont_online'] !== ''
            ? (int)$input['ont_online']
            : null;

        $errors = CreateOltManagementValidator::validatePort($payload);
        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        if (!$this->repo->findDeviceById((int)$payload['olt_id'])) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if ($this->repo->physicalPortExists(
            (int)$payload['olt_id'],
            (int)$payload['frame'],
            (int)$payload['slot'],
            (int)$payload['port']
        )) {
            return [
                'ok' => false,
                'message' => 'Physical port already exists for this OLT.',
                'errors' => [],
            ];
        }

        $isControl = strtoupper((string)($payload['board_type'] ?? '')) === 'CONTROL';

        if (!$isControl && !empty($payload['svlan'])) {
            if ($this->repo->svlanExists((int)$payload['svlan'])) {
                return [
                    'ok' => false,
                    'message' => 'SVLAN is already assigned to another port.',
                    'errors' => [],
                ];
            }
        }

        if ($isControl) {
            $payload['svlan'] = null;
        }

        $id = $this->repo->createPort($payload);

        if ($isControl) {
            $members = $this->parseSvlanCsv($payload['allowed_svlans_csv'] ?? '');
            $this->repo->replacePortSvlanMembers($id, $members);
        } else {
            $this->repo->replacePortSvlanMembers($id, []);
        }

        return [
            'ok' => true,
            'message' => 'OLT port created.',
            'id' => $id,
            'errors' => [],
        ];
    }

    public function updatePort(int $id, array $input): array
    {
        $existing = $this->repo->findPortById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT port not found.',
                'errors' => [],
            ];
        }

        $dto = CreateOltManagementDTO::fromPort($input);
        $payload = $dto->toArray();
        $payload['allowed_svlans_csv'] = trim((string)($input['allowed_svlans_csv'] ?? ''));
        $payload['board_status'] = trim((string)($input['board_status'] ?? ($existing['board_status'] ?? '')));
        $payload['ont_online'] = isset($input['ont_online']) && $input['ont_online'] !== ''
            ? (int)$input['ont_online']
            : ($existing['ont_online'] ?? null);

        $errors = CreateOltManagementValidator::validatePort($payload);
        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        if (!$this->repo->findDeviceById((int)$payload['olt_id'])) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if ($this->repo->physicalPortExists(
            (int)$payload['olt_id'],
            (int)$payload['frame'],
            (int)$payload['slot'],
            (int)$payload['port'],
            $id
        )) {
            return [
                'ok' => false,
                'message' => 'Physical port already exists for this OLT.',
                'errors' => [],
            ];
        }

        $isControl = strtoupper((string)($payload['board_type'] ?? '')) === 'CONTROL';

        if (!$isControl && !empty($payload['svlan'])) {
            if ($this->repo->svlanExists((int)$payload['svlan'], $id)) {
                return [
                    'ok' => false,
                    'message' => 'SVLAN is already assigned to another port.',
                    'errors' => [],
                ];
            }
        }

        if ($isControl) {
            $payload['svlan'] = null;
        }

        $this->repo->updatePort($id, $payload);

        if ($isControl) {
            $members = $this->parseSvlanCsv($payload['allowed_svlans_csv'] ?? '');
            $this->repo->replacePortSvlanMembers($id, $members);
        } else {
            $this->repo->replacePortSvlanMembers($id, []);
        }

        return [
            'ok' => true,
            'message' => 'OLT port updated.',
            'errors' => [],
        ];
    }

    public function deletePort(int $id): array
    {
        $existing = $this->repo->findPortById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT port not found.',
                'errors' => [],
            ];
        }

        if ($this->repo->portIsUsedByLcp($id)) {
            return [
                'ok' => false,
                'message' => 'Cannot delete OLT port because it is assigned to an LCP.',
                'errors' => [],
            ];
        }

        $this->repo->deletePort($id);

        return [
            'ok' => true,
            'message' => 'OLT port deleted.',
            'errors' => [],
        ];
    }

    public function fetchPorts(int $oltId): array
    {
        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'OLT device not found.',
                'errors' => [],
            ];
        }

        $scriptPath = __DIR__ . '/../Scripts/olt_fetch_ports.py';

        if (!file_exists($scriptPath)) {
            return [
                'ok' => false,
                'message' => 'OLT fetch script not found.',
                'errors' => [],
            ];
        }

        $cmd = sprintf(
            'python3 %s %s %s %s',
            escapeshellarg($scriptPath),
            escapeshellarg($olt['ip_address']),
            escapeshellarg($olt['username']),
            escapeshellarg($olt['password'])
        );

        $output = shell_exec($cmd);
        $decoded = json_decode((string)$output, true);

        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Invalid response from fetch script.',
                'raw' => $output,
                'errors' => [],
            ];
        }

        if (!($decoded['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $decoded['error'] ?? 'OLT fetch failed.',
                'data' => $decoded,
                'errors' => [],
            ];
        }

        $ports = $decoded['ports'] ?? [];
        $preview = [];

        foreach ($ports as $p) {
            $existing = $this->repo->findPhysicalPort(
                $oltId,
                (int)$p['frame'],
                (int)$p['slot'],
                (int)$p['port']
            );

            $allowed = [];
            if ($existing && !empty($existing['id'])) {
                $allowed = $this->repo->getPortSvlanMembers((int)$existing['id']);
            }

            $preview[] = [
                'frame' => (int)($p['frame'] ?? 0),
                'slot' => (int)($p['slot'] ?? 0),
                'port' => (int)($p['port'] ?? 0),
                'port_path' => $p['port_path'] ?? (($p['frame'] ?? 0) . '/' . ($p['slot'] ?? 0) . '/' . ($p['port'] ?? 0)),
                'board_name' => $p['board_name'] ?? null,
                'board_status' => $p['board_status'] ?? null,
                'board_type' => $p['board_type'] ?? null,
                'port_type' => $p['port_type'] ?? null,
                'link_status' => $p['link_status'] ?? null,
                'optic_status' => $p['optic_status'] ?? null,
                'speed' => $p['speed'] ?? null,
                'duplex' => $p['duplex'] ?? null,
                'active_state' => $p['active_state'] ?? null,
                'ont_count' => $p['ont_count'] ?? null,
                'ont_online' => $p['ont_online'] ?? null,
                'importable' => (bool)($p['importable'] ?? false),
                'exists_in_db' => $existing ? true : false,
                'existing_id' => $existing['id'] ?? null,
                'svlan' => $existing['svlan'] ?? null,
                'allowed_svlans' => $allowed,
                'allowed_svlans_csv' => implode(',', $allowed),
                'description' => $existing['description'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'message' => 'OLT ports fetched successfully.',
            'olt_id' => $oltId,
            'olt_name' => $olt['name'],
            'preview' => $preview,
            'boards' => $decoded['boards'] ?? [],
            'errors' => [],
        ];
    }

    public function importFetchedPorts(int $oltId, array $ports, ?int $startingSvlan = null): array
    {
        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'OLT device not found.',
                'errors' => [],
            ];
        }

        if (empty($ports)) {
            return [
                'ok' => false,
                'message' => 'No ports provided for import.',
                'errors' => [],
            ];
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $nextGpSvlan = ($startingSvlan !== null && $startingSvlan > 0) ? $startingSvlan : null;

        foreach ($ports as $p) {
            $frame = (int)($p['frame'] ?? -1);
            $slot = (int)($p['slot'] ?? -1);
            $port = (int)($p['port'] ?? -1);

            if ($frame < 0 || $slot < 0 || $port < 0) {
                $skipped++;
                continue;
            }

            if (empty($p['importable'])) {
                $skipped++;
                continue;
            }

            $existing = $this->repo->findPhysicalPort($oltId, $frame, $slot, $port);

            if ($existing) {
                $updateData = [
                    'olt_id' => $oltId,
                    'frame' => $frame,
                    'slot' => $slot,
                    'port' => $port,
                    'board_name' => $p['board_name'] ?? ($existing['board_name'] ?? null),
                    'board_status' => $p['board_status'] ?? ($existing['board_status'] ?? null),
                    'board_type' => $p['board_type'] ?? ($existing['board_type'] ?? null),
                    'port_type' => $p['port_type'] ?? ($existing['port_type'] ?? null),
                    'svlan' => $existing['svlan'] ?? null,
                    'description' => $existing['description'] ?? 'Fetched from OLT',
                    'link_status' => $p['link_status'] ?? ($existing['link_status'] ?? null),
                    'optic_status' => $p['optic_status'] ?? ($existing['optic_status'] ?? null),
                    'speed' => $p['speed'] ?? ($existing['speed'] ?? null),
                    'duplex' => $p['duplex'] ?? ($existing['duplex'] ?? null),
                    'active_state' => $p['active_state'] ?? ($existing['active_state'] ?? null),
                    'ont_count' => $p['ont_count'] ?? ($existing['ont_count'] ?? null),
                    'ont_online' => $p['ont_online'] ?? ($existing['ont_online'] ?? null),
                ];

                $this->repo->updatePort((int)$existing['id'], $updateData);

                if (strtoupper((string)($updateData['board_type'] ?? '')) === 'CONTROL') {
                    $members = $this->parseSvlanCsv((string)($p['allowed_svlans_csv'] ?? ''));
                    $this->repo->replacePortSvlanMembers((int)$existing['id'], $members);
                }

                $skipped++;
                continue;
            }

            $boardType = strtoupper((string)($p['board_type'] ?? ''));
            $svlan = null;

            if ($boardType !== 'CONTROL') {
                if (isset($p['svlan']) && $p['svlan'] !== null && $p['svlan'] !== '') {
                    $svlan = (int)$p['svlan'];
                } elseif ($nextGpSvlan !== null) {
                    while ($this->repo->svlanExists($nextGpSvlan)) {
                        $nextGpSvlan++;
                    }
                    $svlan = $nextGpSvlan;
                    $nextGpSvlan++;
                }

                if ($svlan !== null && $svlan > 0 && $this->repo->svlanExists($svlan)) {
                    $errors[] = "Skipped {$frame}/{$slot}/{$port}: SVLAN {$svlan} already exists.";
                    $skipped++;
                    continue;
                }
            }

            $data = [
                'olt_id' => $oltId,
                'frame' => $frame,
                'slot' => $slot,
                'port' => $port,
                'board_name' => $p['board_name'] ?? null,
                'board_status' => $p['board_status'] ?? null,
                'board_type' => $p['board_type'] ?? null,
                'port_type' => $p['port_type'] ?? null,
                'svlan' => $svlan,
                'description' => 'Fetched from OLT',
                'link_status' => $p['link_status'] ?? null,
                'optic_status' => $p['optic_status'] ?? null,
                'speed' => $p['speed'] ?? null,
                'duplex' => $p['duplex'] ?? null,
                'active_state' => $p['active_state'] ?? null,
                'ont_count' => $p['ont_count'] ?? null,
                'ont_online' => $p['ont_online'] ?? null,
            ];

            $id = $this->repo->createPort($data);

            if ($boardType === 'CONTROL') {
                $members = $this->parseSvlanCsv((string)($p['allowed_svlans_csv'] ?? ''));
                if (!empty($members)) {
                    $this->repo->replacePortSvlanMembers($id, $members);
                }
            }

            $created++;
        }

        return [
            'ok' => true,
            'message' => "Import completed. Created: {$created}, Updated/Skipped Existing: {$skipped}.",
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public function getAvailablePorts(int $oltId, int $maxOntsPerPort = 64): array
    {
        $ports = $this->repo->getPortsByOltId($oltId);
        $available = [];

        foreach ($ports as $port) {
            $boardType = strtoupper((string)($port['board_type'] ?? ''));
            $ontCount = (int)($port['ont_count'] ?? 0);
            $portId = (int)($port['id'] ?? 0);

            if ($boardType !== 'GPON') {
                continue;
            }

            if (strtolower((string)$port['link_status']) !== 'online') {
                continue;
            }

            if (strtolower((string)$port['optic_status']) !== 'online') {
                continue;
            }

            if (strtolower((string)$port['board_status']) !== 'normal') {
                continue;
            }

            if ($this->repo->portIsUsedByLcp($portId)) {
                continue;
            }

            if ($ontCount >= $maxOntsPerPort) {
                continue;
            }

            $available[] = $port;
        }

        return $available;
    }

    /* =========================================================
     * LINE PROFILES
     * ========================================================= */

    public function getAllLineProfiles(): array
    {
        return method_exists($this->repo, 'getAllLineProfiles')
            ? $this->repo->getAllLineProfiles()
            : [];
    }
    public function getLineProfileById(int $id): ?array
    {
        return method_exists($this->repo, 'findLineProfileById')
            ? $this->repo->findLineProfileById($id)
            : null;
    }
    public function getLineProfilesByOltId(int $oltId): array
    {
        return method_exists($this->repo, 'getLineProfilesByOltId')
            ? $this->repo->getLineProfilesByOltId($oltId)
            : $this->getAllLineProfiles();
    }

    public function createLineProfile(array $input): array
    {
        $payload = $this->normalizeLineProfilePayload($input);
        $errors = $this->validateLineProfilePayload($payload);

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'OLT device is required before deploying line profile.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'lineProfileIdExists') &&
            $this->repo->lineProfileIdExists((int)$payload['profile_id'], $oltId)
        ) {
            return [
                'ok' => false,
                'message' => 'Line profile ID already exists for this OLT.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'lineProfileNameExists') &&
            $this->repo->lineProfileNameExists((string)$payload['profile_name'], $oltId)
        ) {
            return [
                'ok' => false,
                'message' => 'Line profile name already exists for this OLT.',
                'errors' => [],
            ];
        }

        $deploy = $this->runLineProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'customer_cvlan' => (int)$payload['customer_cvlan'],
            'management_vlan' => (int)$payload['management_vlan'],
            'dba_profile_id' => (int)$payload['dba_profile_id'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy line profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $id = $this->repo->createLineProfile($payload);

        return [
            'ok' => true,
            'message' => 'OLT line profile created and deployed to OLT.',
            'id' => $id,
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function updateLineProfile(int $id, array $input): array
    {
        $existing = $this->getLineProfileById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT line profile not found.',
                'errors' => [],
            ];
        }

        $payload = $this->normalizeLineProfilePayload($input, $existing);
        $errors = $this->validateLineProfilePayload($payload);

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'OLT device is required before updating line profile.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'lineProfileIdExists') &&
            $this->repo->lineProfileIdExists((int)$payload['profile_id'], $oltId, $id)
        ) {
            return [
                'ok' => false,
                'message' => 'Line profile ID already exists for this OLT.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'lineProfileNameExists') &&
            $this->repo->lineProfileNameExists((string)$payload['profile_name'], $oltId, $id)
        ) {
            return [
                'ok' => false,
                'message' => 'Line profile name already exists for this OLT.',
                'errors' => [],
            ];
        }

        $oldProfileId = (int)($existing['profile_id'] ?? 0);
        if ($oldProfileId > 0) {
            $deleteOld = $this->runLineProfileScript($olt, [
                'action' => 'delete',
                'profile_id' => $oldProfileId,
                'save_config' => false,
            ]);

            if (!($deleteOld['success'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $deleteOld['message'] ?? $deleteOld['error'] ?? 'Failed to remove old line profile from OLT.',
                    'errors' => [],
                    'olt_output' => $deleteOld,
                ];
            }
        }

        $deploy = $this->runLineProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'customer_cvlan' => (int)$payload['customer_cvlan'],
            'management_vlan' => (int)$payload['management_vlan'],
            'dba_profile_id' => (int)$payload['dba_profile_id'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy updated line profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->updateLineProfile($id, $payload);

        return [
            'ok' => true,
            'message' => 'OLT line profile updated and redeployed to OLT.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function deleteLineProfile(int $id): array
    {
        $existing = $this->getLineProfileById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT line profile not found.',
                'errors' => [],
            ];
        }

        $oltId = (int)($existing['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'Cannot delete from OLT because this line profile has no OLT assigned.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        $deploy = $this->runLineProfileScript($olt, [
            'action' => 'delete',
            'profile_id' => (int)$existing['profile_id'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to delete line profile from OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->deleteLineProfile($id);

        return [
            'ok' => true,
            'message' => 'OLT line profile deleted from OLT and database.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }
    public function getLineProfileCliPreview(int $id): array
    {
        $profile = $this->getLineProfileById($id);
        if (!$profile) {
            return [
                'ok' => false,
                'message' => 'OLT line profile not found.',
                'errors' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'CLI preview generated.',
            'data' => [
                'id' => (int)$profile['id'],
                'profile_id' => (int)$profile['profile_id'],
                'profile_name' => (string)$profile['profile_name'],
                'cli' => $this->buildLineProfileCliPreview($profile),
            ],
            'errors' => [],
        ];
    }

    /* =========================================================
     * DBA PROFILES
     * ========================================================= */

    public function getAllDbaProfiles(): array
    {
        return method_exists($this->repo, 'getAllDbaProfiles')
            ? $this->repo->getAllDbaProfiles()
            : [];
    }

    public function getDbaProfileById(int $id): ?array
    {
        return method_exists($this->repo, 'findDbaProfileById')
            ? $this->repo->findDbaProfileById($id)
            : null;
    }

    public function getDbaProfilesByOltId(int $oltId): array
    {
        return method_exists($this->repo, 'getDbaProfilesByOltId')
            ? $this->repo->getDbaProfilesByOltId($oltId)
            : $this->getAllDbaProfiles();
    }

    public function createDbaProfile(array $input): array
    {
        $payload = $this->normalizeDbaProfilePayload($input);
        $errors = $this->validateDbaProfilePayload($payload);

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'OLT device is required before deploying DBA profile.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'dbaProfileIdExists') &&
            $this->repo->dbaProfileIdExists((int)$payload['profile_id'], $oltId)
        ) {
            return [
                'ok' => false,
                'message' => 'DBA profile ID already exists for this OLT.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'dbaProfileNameExists') &&
            $this->repo->dbaProfileNameExists((string)$payload['profile_name'], $oltId)
        ) {
            return [
                'ok' => false,
                'message' => 'DBA profile name already exists for this OLT.',
                'errors' => [],
            ];
        }

        $deploy = $this->runDbaProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'dba_type' => (string)$payload['profile_type'],
            'max_bandwidth' => (int)$payload['max_bandwidth'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy DBA profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $id = $this->repo->createDbaProfile($payload);

        return [
            'ok' => true,
            'message' => 'OLT DBA profile created and deployed to OLT.',
            'id' => $id,
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function updateDbaProfile(int $id, array $input): array
    {
        $existing = $this->getDbaProfileById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT DBA profile not found.',
                'errors' => [],
            ];
        }

        $payload = $this->normalizeDbaProfilePayload($input, $existing);
        $errors = $this->validateDbaProfilePayload($payload);

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'OLT device is required before updating DBA profile.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'dbaProfileIdExists') &&
            $this->repo->dbaProfileIdExists((int)$payload['profile_id'], $oltId, $id)
        ) {
            return [
                'ok' => false,
                'message' => 'DBA profile ID already exists for this OLT.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'dbaProfileNameExists') &&
            $this->repo->dbaProfileNameExists((string)$payload['profile_name'], $oltId, $id)
        ) {
            return [
                'ok' => false,
                'message' => 'DBA profile name already exists for this OLT.',
                'errors' => [],
            ];
        }

        /*
         * Huawei DBA profile update is safest as:
         * delete old profile-id, then add new definition.
         */
        $oldProfileId = (int)($existing['profile_id'] ?? 0);
        if ($oldProfileId > 0) {
            $deleteOld = $this->runDbaProfileScript($olt, [
                'action' => 'delete',
                'profile_id' => $oldProfileId,
                'save_config' => false,
            ]);

            if (!($deleteOld['success'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $deleteOld['message'] ?? $deleteOld['error'] ?? 'Failed to remove old DBA profile from OLT.',
                    'errors' => [],
                    'olt_output' => $deleteOld,
                ];
            }
        }

        $deploy = $this->runDbaProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'dba_type' => (string)$payload['profile_type'],
            'max_bandwidth' => (int)$payload['max_bandwidth'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy updated DBA profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->updateDbaProfile($id, $payload);

        return [
            'ok' => true,
            'message' => 'OLT DBA profile updated and redeployed to OLT.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function deleteDbaProfile(int $id): array
    {
        $existing = $this->getDbaProfileById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT DBA profile not found.',
                'errors' => [],
            ];
        }

        $oltId = (int)($existing['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'Cannot delete from OLT because this DBA profile has no OLT assigned.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        $deploy = $this->runDbaProfileScript($olt, [
            'action' => 'delete',
            'profile_id' => (int)$existing['profile_id'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to delete DBA profile from OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->deleteDbaProfile($id);

        return [
            'ok' => true,
            'message' => 'OLT DBA profile deleted from OLT and database.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function getDbaProfileCliPreview(int $id): array
    {
        $profile = $this->getDbaProfileById($id);
        if (!$profile) {
            return [
                'ok' => false,
                'message' => 'OLT DBA profile not found.',
                'errors' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'CLI preview generated.',
            'data' => [
                'id' => (int)$profile['id'],
                'profile_id' => (int)$profile['profile_id'],
                'profile_name' => (string)$profile['profile_name'],
                'cli' => $this->buildDbaProfileCliPreview($profile),
            ],
            'errors' => [],
        ];
    }

    /* =========================================================
     * WAN PROFILES
     * ========================================================= */

    public function getAllWanProfiles(): array
    {
        return method_exists($this->repo, 'getAllWanProfiles')
            ? $this->repo->getAllWanProfiles()
            : [];
    }
    public function getWanProfileById(int $id): ?array
    {
        return method_exists($this->repo, 'findWanProfileById')
            ? $this->repo->findWanProfileById($id)
            : null;
    }
    public function getWanProfilesByOltId(int $oltId): array
    {
        return method_exists($this->repo, 'getWanProfilesByOltId')
            ? $this->repo->getWanProfilesByOltId($oltId)
            : $this->getAllWanProfiles();
    }

    public function createWanProfile(array $input): array
    {
        $payload = $this->normalizeWanProfilePayload($input);
        $errors = $this->validateWanProfilePayload($payload);

        if (!empty($errors)) {
            return ['ok' => false, 'message' => $errors[0], 'errors' => $errors];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return ['ok' => false, 'message' => 'OLT device is required before deploying WAN profile.', 'errors' => []];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return ['ok' => false, 'message' => 'Selected OLT device not found.', 'errors' => []];
        }

        $deploy = $this->runWanProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'connection_type' => (string)$payload['connection_type'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy WAN profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $id = $this->repo->createWanProfile($payload);

        return [
            'ok' => true,
            'message' => 'OLT WAN profile created and deployed to OLT.',
            'id' => $id,
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function updateWanProfile(int $id, array $input): array
    {
        $existing = $this->getWanProfileById($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'OLT WAN profile not found.', 'errors' => []];
        }

        $payload = $this->normalizeWanProfilePayload($input, $existing);
        $errors = $this->validateWanProfilePayload($payload);

        if (!empty($errors)) {
            return ['ok' => false, 'message' => $errors[0], 'errors' => $errors];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return ['ok' => false, 'message' => 'OLT device is required before updating WAN profile.', 'errors' => []];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return ['ok' => false, 'message' => 'Selected OLT device not found.', 'errors' => []];
        }

        $oldProfileId = (int)($existing['profile_id'] ?? 0);
        if ($oldProfileId > 0) {
            $deleteOld = $this->runWanProfileScript($olt, [
                'action' => 'delete',
                'profile_id' => $oldProfileId,
                'save_config' => false,
            ]);

            if (!($deleteOld['success'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $deleteOld['message'] ?? $deleteOld['error'] ?? 'Failed to remove old WAN profile from OLT.',
                    'errors' => [],
                    'olt_output' => $deleteOld,
                ];
            }
        }

        $deploy = $this->runWanProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'connection_type' => (string)$payload['connection_type'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy updated WAN profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->updateWanProfile($id, $payload);

        return [
            'ok' => true,
            'message' => 'OLT WAN profile updated and redeployed to OLT.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function deleteWanProfile(int $id): array
    {
        $existing = $this->getWanProfileById($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'OLT WAN profile not found.', 'errors' => []];
        }

        $oltId = (int)($existing['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return ['ok' => false, 'message' => 'Cannot delete from OLT because this WAN profile has no OLT assigned.', 'errors' => []];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return ['ok' => false, 'message' => 'Selected OLT device not found.', 'errors' => []];
        }

        $deploy = $this->runWanProfileScript($olt, [
            'action' => 'delete',
            'profile_id' => (int)$existing['profile_id'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to delete WAN profile from OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->deleteWanProfile($id);

        return [
            'ok' => true,
            'message' => 'OLT WAN profile deleted from OLT and database.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }
    public function getWanProfileCliPreview(int $id): array
    {
        $profile = $this->getWanProfileById($id);
        if (!$profile) {
            return [
                'ok' => false,
                'message' => 'OLT WAN profile not found.',
                'errors' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'CLI preview generated.',
            'data' => [
                'id' => (int)$profile['id'],
                'profile_id' => (int)$profile['profile_id'],
                'profile_name' => (string)$profile['profile_name'],
                'cli' => $this->buildWanProfileCliPreview($profile),
            ],
            'errors' => [],
        ];
    }

    /* =========================================================
     * TR069 PROFILES
     * ========================================================= */

    public function getAllTr069Profiles(): array
    {
        return method_exists($this->repo, 'getAllTr069Profiles')
            ? $this->repo->getAllTr069Profiles()
            : [];
    }

    public function getTr069ProfileById(int $id): ?array
    {
        return method_exists($this->repo, 'findTr069ProfileById')
            ? $this->repo->findTr069ProfileById($id)
            : null;
    }

    public function getTr069ProfilesByOltId(int $oltId): array
    {
        return method_exists($this->repo, 'getTr069ProfilesByOltId')
            ? $this->repo->getTr069ProfilesByOltId($oltId)
            : $this->getAllTr069Profiles();
    }

    public function createTr069Profile(array $input): array
    {
        $payload = $this->normalizeTr069ProfilePayload($input);
        $errors = $this->validateTr069ProfilePayload($payload);

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'OLT device is required before deploying TR069 profile.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'tr069ProfileIdExists') &&
            $this->repo->tr069ProfileIdExists((int)$payload['profile_id'], $oltId)
        ) {
            return [
                'ok' => false,
                'message' => 'TR069 profile ID already exists for this OLT.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'tr069ProfileNameExists') &&
            $this->repo->tr069ProfileNameExists((string)$payload['profile_name'], $oltId)
        ) {
            return [
                'ok' => false,
                'message' => 'TR069 profile name already exists for this OLT.',
                'errors' => [],
            ];
        }

        $deploy = $this->runTr069ProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'acs_url' => (string)$payload['acs_url'],
            'acs_username' => (string)$payload['acs_username'],
            'acs_password' => (string)$payload['acs_password'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy TR069 profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $id = $this->repo->createTr069Profile($payload);

        return [
            'ok' => true,
            'message' => 'OLT TR069 profile created and deployed to OLT.',
            'id' => $id,
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function updateTr069Profile(int $id, array $input): array
    {
        $existing = $this->getTr069ProfileById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT TR069 profile not found.',
                'errors' => [],
            ];
        }

        $payload = $this->normalizeTr069ProfilePayload($input, $existing);
        $errors = $this->validateTr069ProfilePayload($payload);

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'OLT device is required before updating TR069 profile.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'tr069ProfileIdExists') &&
            $this->repo->tr069ProfileIdExists((int)$payload['profile_id'], $oltId, $id)
        ) {
            return [
                'ok' => false,
                'message' => 'TR069 profile ID already exists for this OLT.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'tr069ProfileNameExists') &&
            $this->repo->tr069ProfileNameExists((string)$payload['profile_name'], $oltId, $id)
        ) {
            return [
                'ok' => false,
                'message' => 'TR069 profile name already exists for this OLT.',
                'errors' => [],
            ];
        }

        $oldProfileId = (int)($existing['profile_id'] ?? 0);
        if ($oldProfileId > 0) {
            $deleteOld = $this->runTr069ProfileScript($olt, [
                'action' => 'delete',
                'profile_id' => $oldProfileId,
                'save_config' => false,
            ]);

            if (!($deleteOld['success'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $deleteOld['message'] ?? $deleteOld['error'] ?? 'Failed to remove old TR069 profile from OLT.',
                    'errors' => [],
                    'olt_output' => $deleteOld,
                ];
            }
        }

        $deploy = $this->runTr069ProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'acs_url' => (string)$payload['acs_url'],
            'acs_username' => (string)$payload['acs_username'],
            'acs_password' => (string)$payload['acs_password'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy updated TR069 profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->updateTr069Profile($id, $payload);

        return [
            'ok' => true,
            'message' => 'OLT TR069 profile updated and redeployed to OLT.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function deleteTr069Profile(int $id): array
    {
        $existing = $this->getTr069ProfileById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT TR069 profile not found.',
                'errors' => [],
            ];
        }

        $oltId = (int)($existing['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'Cannot delete from OLT because this TR069 profile has no OLT assigned.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        $deploy = $this->runTr069ProfileScript($olt, [
            'action' => 'delete',
            'profile_id' => (int)$existing['profile_id'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to delete TR069 profile from OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->deleteTr069Profile($id);

        return [
            'ok' => true,
            'message' => 'OLT TR069 profile deleted from OLT and database.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function getTr069ProfileCliPreview(int $id): array
    {
        $profile = $this->getTr069ProfileById($id);
        if (!$profile) {
            return [
                'ok' => false,
                'message' => 'OLT TR069 profile not found.',
                'errors' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'CLI preview generated.',
            'data' => [
                'id' => (int)$profile['id'],
                'profile_id' => (int)$profile['profile_id'],
                'profile_name' => (string)$profile['profile_name'],
                'cli' => $this->buildTr069ProfileCliPreview($profile),
            ],
            'errors' => [],
        ];
    }

    /* =========================================================
  * SERVICE PROFILES
  * ========================================================= */

    public function getAllSrvProfiles(): array
    {
        return method_exists($this->repo, 'getAllSrvProfiles')
            ? $this->repo->getAllSrvProfiles()
            : [];
    }

    public function getSrvProfilesByOltId(int $oltId): array
    {
        return method_exists($this->repo, 'getSrvProfilesByOltId')
            ? $this->repo->getSrvProfilesByOltId($oltId)
            : $this->getAllSrvProfiles();
    }

    public function getSrvProfileById(int $id): ?array
    {
        return method_exists($this->repo, 'findSrvProfileById')
            ? $this->repo->findSrvProfileById($id)
            : null;
    }

    public function createSrvProfile(array $input): array
    {
        $payload = $this->normalizeSrvProfilePayload($input);
        $errors = $this->validateSrvProfilePayload($payload);

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'OLT device is required before deploying service profile.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'srvProfileIdExists') &&
            $this->repo->srvProfileIdExists((int)$payload['profile_id'], $oltId)
        ) {
            return [
                'ok' => false,
                'message' => 'Service profile ID already exists for this OLT.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'srvProfileNameExists') &&
            $this->repo->srvProfileNameExists((string)$payload['profile_name'], $oltId)
        ) {
            return [
                'ok' => false,
                'message' => 'Service profile name already exists for this OLT.',
                'errors' => [],
            ];
        }

        $deploy = $this->runSrvProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'eth_port_count' => (int)$payload['eth_port_count'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy service profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $id = $this->repo->createSrvProfile($payload);

        return [
            'ok' => true,
            'message' => 'OLT service profile created and deployed to OLT.',
            'id' => $id,
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function updateSrvProfile(int $id, array $input): array
    {
        $existing = $this->getSrvProfileById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT service profile not found.',
                'errors' => [],
            ];
        }

        $payload = $this->normalizeSrvProfilePayload($input, $existing);
        $errors = $this->validateSrvProfilePayload($payload);

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
            ];
        }

        $oltId = (int)($payload['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'OLT device is required before updating service profile.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'srvProfileIdExists') &&
            $this->repo->srvProfileIdExists((int)$payload['profile_id'], $oltId, $id)
        ) {
            return [
                'ok' => false,
                'message' => 'Service profile ID already exists for this OLT.',
                'errors' => [],
            ];
        }

        if (
            method_exists($this->repo, 'srvProfileNameExists') &&
            $this->repo->srvProfileNameExists((string)$payload['profile_name'], $oltId, $id)
        ) {
            return [
                'ok' => false,
                'message' => 'Service profile name already exists for this OLT.',
                'errors' => [],
            ];
        }

        $oldProfileId = (int)($existing['profile_id'] ?? 0);
        if ($oldProfileId > 0) {
            $deleteOld = $this->runSrvProfileScript($olt, [
                'action' => 'delete',
                'profile_id' => $oldProfileId,
                'save_config' => false,
            ]);

            if (!($deleteOld['success'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $deleteOld['message'] ?? $deleteOld['error'] ?? 'Failed to remove old service profile from OLT.',
                    'errors' => [],
                    'olt_output' => $deleteOld,
                ];
            }
        }

        $deploy = $this->runSrvProfileScript($olt, [
            'action' => 'add',
            'profile_id' => (int)$payload['profile_id'],
            'profile_name' => (string)$payload['profile_name'],
            'eth_port_count' => (int)$payload['eth_port_count'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to deploy updated service profile to OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->updateSrvProfile($id, $payload);

        return [
            'ok' => true,
            'message' => 'OLT service profile updated and redeployed to OLT.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function deleteSrvProfile(int $id): array
    {
        $existing = $this->getSrvProfileById($id);
        if (!$existing) {
            return [
                'ok' => false,
                'message' => 'OLT service profile not found.',
                'errors' => [],
            ];
        }

        $oltId = (int)($existing['olt_id'] ?? 0);
        if ($oltId <= 0) {
            return [
                'ok' => false,
                'message' => 'Cannot delete from OLT because this service profile has no OLT assigned.',
                'errors' => [],
            ];
        }

        $olt = $this->repo->findDeviceById($oltId);
        if (!$olt) {
            return [
                'ok' => false,
                'message' => 'Selected OLT device not found.',
                'errors' => [],
            ];
        }

        $deploy = $this->runSrvProfileScript($olt, [
            'action' => 'delete',
            'profile_id' => (int)$existing['profile_id'],
            'save_config' => true,
        ]);

        if (!($deploy['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to delete service profile from OLT.',
                'errors' => [],
                'olt_output' => $deploy,
            ];
        }

        $this->repo->deleteSrvProfile($id);

        return [
            'ok' => true,
            'message' => 'OLT service profile deleted from OLT and database.',
            'olt_output' => $deploy,
            'errors' => [],
        ];
    }

    public function getSrvProfileCliPreview(int $id): array
    {
        $profile = $this->getSrvProfileById($id);
        if (!$profile) {
            return [
                'ok' => false,
                'message' => 'OLT service profile not found.',
                'errors' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'CLI preview generated.',
            'data' => [
                'id' => (int)$profile['id'],
                'profile_id' => (int)$profile['profile_id'],
                'profile_name' => (string)$profile['profile_name'],
                'cli' => $this->buildSrvProfileCliPreview($profile),
            ],
            'errors' => [],
        ];
    }

    /* =========================================================
        * CONTROL BOARD VLAN BINDINGS
        * ========================================================= */

    public function getControlBoardVlanWorkspace(int $oltId): array
{
    return [
        'ports' => method_exists($this->repo, 'getControlBoardPortsByOltId')
            ? $this->repo->getControlBoardPortsByOltId($oltId)
            : [],

        'bindings' => method_exists($this->repo, 'getPortVlanBindingsByOltId')
            ? $this->repo->getPortVlanBindingsByOltId($oltId)
            : [],
    ];
}

public function getPonSvlanOptions(int $oltId, int $portId = 0): array
{
    return $this->repo->getPonSvlanOptions($oltId, $portId);
}

public function assignPonPortSvlan(int $portId, int $svlan): array
{
    if ($portId <= 0 || $svlan <= 0) {
        return [
            'ok' => false,
            'message' => 'PON port and SVLAN are required.',
        ];
    }

    $port = $this->getPortById($portId);

    if (!$port) {
        return [
            'ok' => false,
            'message' => 'OLT port not found.',
        ];
    }

    $slot = (int)($port['slot'] ?? 0);

    if (in_array($slot, [3, 4], true) || strtoupper((string)($port['board_type'] ?? '')) === 'CONTROL') {
        return [
            'ok' => false,
            'message' => 'SVLAN assignment is only allowed on PON ports.',
        ];
    }

    $oltId = (int)($port['olt_id'] ?? 0);

if (
    method_exists($this->repo, 'ponSvlanAssignedToAnotherPort') &&
    $this->repo->ponSvlanAssignedToAnotherPort($oltId, $svlan, $portId)
) {
    return [
        'ok' => false,
        'message' => "SVLAN {$svlan} is already assigned to another PON port.",
    ];
}

    $this->repo->assignPonPortSvlan($portId, $svlan);

    return [
        'ok' => true,
        'message' => 'PON port SVLAN assigned.',
        'port_id' => $portId,
        'svlan' => $svlan,
    ];
}

public function getVlanBindingOptions(int $oltId, string $vlanType, int $portId = 0): array
{
    return method_exists($this->repo, 'getVlanBindingDropdownOptions')
        ? $this->repo->getVlanBindingDropdownOptions($oltId, $vlanType, $portId)
        : [];
}

public function getControlBoardVlanOptions(int $oltId, string $vlanType, int $portId = 0): array
{
    return $this->getVlanBindingOptions($oltId, $vlanType, $portId);
}

public function createPortVlanBinding(array $input): array
{
    $oltPortId = (int)($input['olt_port_id'] ?? 0);
    $networkVlanId = (int)($input['network_vlan_id'] ?? 0);
    $vlanType = strtoupper(trim((string)($input['vlan_type'] ?? '')));

    if ($oltPortId <= 0) {
        return ['ok' => false, 'message' => 'OLT port is required.', 'errors' => []];
    }

    if ($networkVlanId <= 0) {
        return ['ok' => false, 'message' => 'VLAN is required.', 'errors' => []];
    }

    if (!in_array($vlanType, ['SERVICE', 'MGMT'], true)) {
        return ['ok' => false, 'message' => 'Invalid VLAN type.', 'errors' => []];
    }

    $port = $this->repo->findPortById($oltPortId);
    if (!$port) {
        return ['ok' => false, 'message' => 'OLT port not found.', 'errors' => []];
    }

    $isControl = strtoupper((string)($port['board_type'] ?? '')) === 'CONTROL'
        || in_array((int)($port['slot'] ?? 0), [3, 4], true);

    if (!$isControl) {
        return [
            'ok' => false,
            'message' => 'VLAN binding is allowed only on control board ports 0/3 and 0/4.',
            'errors' => [],
        ];
    }

    if ($vlanType === 'MGMT') {
        $vlan = method_exists($this->repo, 'findMgmtVlanById')
            ? $this->repo->findMgmtVlanById($networkVlanId)
            : null;

        if (!$vlan) {
            return ['ok' => false, 'message' => 'MGMT VLAN not found in VLAN Management.', 'errors' => []];
        }

        $realVlanId = (int)($vlan['mgmt_vlan'] ?? 0);
    } else {
        $vlan = $this->repo->findNetworkVlanById($networkVlanId);

        if (!$vlan) {
            return ['ok' => false, 'message' => 'Service VLAN not found in VLAN Management.', 'errors' => []];
        }

        $realVlanId = (int)($vlan['vlan_id'] ?? 0);
    }

    if ((int)($vlan['olt_id'] ?? 0) !== (int)($port['olt_id'] ?? 0)) {
        return ['ok' => false, 'message' => 'Selected VLAN does not belong to the selected OLT.', 'errors' => []];
    }

    if ($realVlanId <= 0) {
        return ['ok' => false, 'message' => 'Invalid VLAN ID from VLAN Management.', 'errors' => []];
    }

    if (
        method_exists($this->repo, 'portVlanBindingExists') &&
        $this->repo->portVlanBindingExists($oltPortId, $vlanType, $realVlanId)
    ) {
        return [
            'ok' => false,
            'message' => 'This VLAN is already bound to this control board port.',
            'errors' => [],
        ];
    }

    $olt = $this->repo->findDeviceById((int)$port['olt_id']);
    if (!$olt) {
        return ['ok' => false, 'message' => 'OLT device not found.', 'errors' => []];
    }

    $deploy = $this->runControlBoardVlanScript($olt, [
        'action' => 'add',
        'vlan_id' => $realVlanId,
        'frame' => (int)($port['frame'] ?? 0),
        'slot' => (int)($port['slot'] ?? 0),
        'port_no' => (int)($port['port'] ?? 0),
        'save_config' => true,
    ]);

    if (!($deploy['success'] ?? false)) {
        return [
            'ok' => false,
            'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to bind VLAN to control board port on OLT.',
            'errors' => [],
            'olt_output' => $deploy,
        ];
    }

    $id = $this->repo->createPortVlanBinding([
    'olt_id' => (int)$port['olt_id'],
    'olt_port_id' => $oltPortId,
    'network_vlan_id' => $networkVlanId,
    'vlan_id' => $realVlanId,
    'vlan_type' => $vlanType,
    'frame' => (int)($port['frame'] ?? 0),
    'slot' => (int)($port['slot'] ?? 0),
    'control_board_port' => (int)($port['port'] ?? 0),
]);

    return [
        'ok' => true,
        'message' => 'VLAN binding deployed and saved successfully.',
        'id' => $id,
        'olt_output' => $deploy,
        'errors' => [],
    ];
}

public function deletePortVlanBinding(int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'message' => 'Binding ID is required.', 'errors' => []];
    }

    $binding = method_exists($this->repo, 'findPortVlanBindingById')
        ? $this->repo->findPortVlanBindingById($id)
        : null;

    if (!$binding) {
        return ['ok' => false, 'message' => 'VLAN binding not found.', 'errors' => []];
    }

    $olt = $this->repo->findDeviceById((int)$binding['olt_id']);
    if (!$olt) {
        return ['ok' => false, 'message' => 'OLT device not found.', 'errors' => []];
    }

    // 🔥 CALL UNBIND SCRIPT
    $deploy = $this->runControlBoardVlanScript($olt, [
        'action' => 'delete', // IMPORTANT
        'vlan_id' => (int)$binding['vlan_id'],
        'frame' => (int)$binding['frame'],
        'slot' => (int)$binding['slot'],
        'port_no' => (int)$binding['control_board_port'],
        'save_config' => true,
    ]);

    if (!($deploy['success'] ?? false)) {
        return [
            'ok' => false,
            'message' => $deploy['message'] ?? $deploy['error'] ?? 'Failed to unbind VLAN from control board port on OLT.',
            'errors' => [],
            'olt_output' => $deploy,
        ];
    }

    // ✅ ONLY delete DB after success
    $this->repo->deletePortVlanBinding($id);

    return [
        'ok' => true,
        'message' => 'VLAN unbound and removed successfully.',
        'olt_output' => $deploy,
        'errors' => [],
    ];
}

    public function unassignPonPortSvlan(int $portId): array
    {
        if ($portId <= 0) {
            return [
                'ok' => false,
                'message' => 'PON port is required.',
                'errors' => [],
            ];
        }

        $port = $this->repo->findPortById($portId);

        if (!$port) {
            return [
                'ok' => false,
                'message' => 'OLT port not found.',
                'errors' => [],
            ];
        }

        $slot = (int)($port['slot'] ?? 0);
        $boardType = strtoupper((string)($port['board_type'] ?? ''));

        if ($boardType === 'CONTROL' || in_array($slot, [3, 4], true)) {
            return [
                'ok' => false,
                'message' => 'SVLAN removal is only allowed on PON ports.',
                'errors' => [],
            ];
        }

        $this->repo->clearPonPortSvlan($portId);

        return [
            'ok' => true,
            'message' => 'PON port SVLAN removed.',
            'errors' => [],
        ];
    }

    /* =========================================================
     * NORMALIZERS / VALIDATORS
     * ========================================================= */

    private function normalizeLineProfilePayload(array $input, ?array $existing = null): array
    {
        return [
            'olt_id' => isset($input['olt_id']) && (int)$input['olt_id'] > 0
                ? (int)$input['olt_id']
                : ($existing['olt_id'] ?? null),
            'profile_id' => (int)($input['profile_id'] ?? ($existing['profile_id'] ?? 0)),
            'profile_name' => trim((string)($input['profile_name'] ?? ($existing['profile_name'] ?? ''))),
            'customer_cvlan' => (int)($input['customer_cvlan'] ?? ($existing['customer_cvlan'] ?? 0)),
            'management_vlan' => (int)($input['management_vlan'] ?? ($existing['management_vlan'] ?? 0)),
            'omcc_encrypt' => isset($input['omcc_encrypt'])
                ? (int)((string)$input['omcc_encrypt'] === '1' ? 1 : 0)
                : (int)($existing['omcc_encrypt'] ?? 1),
            'tr069_management_enable' => isset($input['tr069_management_enable'])
                ? (int)((string)$input['tr069_management_enable'] === '1' ? 1 : 0)
                : (int)($existing['tr069_management_enable'] ?? 1),
            'tr069_ip_index' => (int)($input['tr069_ip_index'] ?? ($existing['tr069_ip_index'] ?? 1)),
            'tcont_id' => (int)($input['tcont_id'] ?? ($existing['tcont_id'] ?? 1)),
            'dba_profile_id' => (int)($input['dba_profile_id'] ?? ($existing['dba_profile_id'] ?? 20)),
            'gem_subscriber_id' => (int)($input['gem_subscriber_id'] ?? ($existing['gem_subscriber_id'] ?? 1)),
            'gem_management_id' => (int)($input['gem_management_id'] ?? ($existing['gem_management_id'] ?? 2)),
            'gem_subscriber_encrypt' => isset($input['gem_subscriber_encrypt'])
                ? (int)((string)$input['gem_subscriber_encrypt'] === '1' ? 1 : 0)
                : (int)($existing['gem_subscriber_encrypt'] ?? 1),
            'gem_management_encrypt' => isset($input['gem_management_encrypt'])
                ? (int)((string)$input['gem_management_encrypt'] === '1' ? 1 : 0)
                : (int)($existing['gem_management_encrypt'] ?? 1),
            'description' => trim((string)($input['description'] ?? ($existing['description'] ?? ''))),
        ];
    }

    private function validateLineProfilePayload(array $payload): array
    {
        $errors = [];

        if ((int)($payload['profile_id'] ?? 0) <= 0) {
            $errors[] = 'Profile ID is required.';
        }
        if (trim((string)($payload['profile_name'] ?? '')) === '') {
            $errors[] = 'Profile name is required.';
        }
        if ((int)($payload['customer_cvlan'] ?? 0) <= 0) {
            $errors[] = 'Customer CVLAN is required.';
        }
        if ((int)($payload['management_vlan'] ?? 0) <= 0) {
            $errors[] = 'Management VLAN is required.';
        }
        if ((int)($payload['tr069_ip_index'] ?? 0) <= 0) {
            $errors[] = 'TR069 IP index is required.';
        }
        if ((int)($payload['tcont_id'] ?? 0) <= 0) {
            $errors[] = 'T-CONT ID is required.';
        }
        if ((int)($payload['dba_profile_id'] ?? 0) <= 0) {
            $errors[] = 'DBA profile ID is required.';
        }
        if ((int)($payload['gem_subscriber_id'] ?? 0) <= 0) {
            $errors[] = 'Subscriber GEM ID is required.';
        }
        if ((int)($payload['gem_management_id'] ?? 0) <= 0) {
            $errors[] = 'Management GEM ID is required.';
        }
        if ((int)$payload['gem_subscriber_id'] === (int)$payload['gem_management_id']) {
            $errors[] = 'Subscriber GEM ID and Management GEM ID must be different.';
        }

        return $errors;
    }

    private function normalizeDbaProfilePayload(array $input, ?array $existing = null): array
    {
        return [
            'olt_id' => isset($input['olt_id']) && (int)$input['olt_id'] > 0
                ? (int)$input['olt_id']
                : ($existing['olt_id'] ?? null),
            'profile_id' => (int)($input['profile_id'] ?? ($existing['profile_id'] ?? 0)),
            'profile_name' => trim((string)($input['profile_name'] ?? ($existing['profile_name'] ?? ''))),
            'profile_type' => strtolower(trim((string)(
                $input['profile_type']
                ?? $input['dba_type']
                ?? ($existing['profile_type'] ?? 'type4')
            ))),
            'max_bandwidth' => (int)($input['max_bandwidth'] ?? $input['max'] ?? ($existing['max_bandwidth'] ?? 0)),
            'description' => trim((string)($input['description'] ?? ($existing['description'] ?? ''))),
        ];
    }

    private function validateDbaProfilePayload(array $payload): array
    {
        $errors = [];

        if ((int)($payload['profile_id'] ?? 0) <= 0) {
            $errors[] = 'DBA profile ID is required.';
        }

        if (trim((string)($payload['profile_name'] ?? '')) === '') {
            $errors[] = 'DBA profile name is required.';
        }

        $allowedTypes = ['type1', 'type2', 'type3', 'type4', 'type5'];

        if (!in_array(strtolower((string)$payload['profile_type']), $allowedTypes, true)) {
            $errors[] = 'DBA type must be type1, type2, type3, type4, or type5.';
        }

        if ((int)($payload['max_bandwidth'] ?? 0) <= 0) {
            $errors[] = 'Max bandwidth is required.';
        }

        return $errors;
    }

    private function normalizeWanProfilePayload(array $input, ?array $existing = null): array
    {
        return [
            'olt_id' => isset($input['olt_id']) && (int)$input['olt_id'] > 0
                ? (int)$input['olt_id']
                : ($existing['olt_id'] ?? null),
            'profile_id' => (int)($input['profile_id'] ?? ($existing['profile_id'] ?? 0)),
            'profile_name' => trim((string)($input['profile_name'] ?? ($existing['profile_name'] ?? ''))),
            'connection_type' => strtoupper(trim((string)($input['connection_type'] ?? $input['wan_mode'] ?? ($existing['connection_type'] ?? 'PPPOE')))),
            'service_type' => trim((string)($input['service_type'] ?? ($existing['service_type'] ?? 'INTERNET'))),
            'nat_enable' => isset($input['nat_enable'])
                ? (int)((string)$input['nat_enable'] === '1' ? 1 : 0)
                : (int)($existing['nat_enable'] ?? 1),
            'vlan_mode' => strtoupper(trim((string)($input['vlan_mode'] ?? ($existing['vlan_mode'] ?? 'TRANSPARENT')))),
            'description' => trim((string)($input['description'] ?? ($existing['description'] ?? ''))),
        ];
    }

    private function validateWanProfilePayload(array $payload): array
    {
        $errors = [];

        if ((int)($payload['profile_id'] ?? 0) <= 0) {
            $errors[] = 'WAN profile ID is required.';
        }
        if (trim((string)($payload['profile_name'] ?? '')) === '') {
            $errors[] = 'WAN profile name is required.';
        }
        if (trim((string)($payload['connection_type'] ?? '')) === '') {
            $errors[] = 'Connection type is required.';
        }

        return $errors;
    }

    private function normalizeTr069ProfilePayload(array $input, ?array $existing = null): array
    {
        return [
            'olt_id' => isset($input['olt_id']) && (int)$input['olt_id'] > 0
                ? (int)$input['olt_id']
                : ($existing['olt_id'] ?? null),
            'profile_id' => (int)($input['profile_id'] ?? ($existing['profile_id'] ?? 0)),
            'profile_name' => trim((string)($input['profile_name'] ?? ($existing['profile_name'] ?? ''))),
            'acs_url' => trim((string)($input['acs_url'] ?? $input['server_url'] ?? ($existing['acs_url'] ?? ''))),
            'acs_username' => trim((string)($input['acs_username'] ?? $input['username'] ?? ($existing['acs_username'] ?? ''))),
            'acs_password' => trim((string)($input['acs_password'] ?? $input['password'] ?? ($existing['acs_password'] ?? ''))),
            'periodic_inform_enable' => isset($input['periodic_inform_enable'])
                ? (int)((string)$input['periodic_inform_enable'] === '1' ? 1 : 0)
                : (int)($existing['periodic_inform_enable'] ?? 1),
            'periodic_inform_interval' => (int)($input['periodic_inform_interval'] ?? ($existing['periodic_inform_interval'] ?? 300)),
            'description' => trim((string)($input['description'] ?? ($existing['description'] ?? ''))),
        ];
    }

    private function validateTr069ProfilePayload(array $payload): array
    {
        $errors = [];

        if ((int)($payload['profile_id'] ?? 0) <= 0) {
            $errors[] = 'TR069 profile ID is required.';
        }
        if (trim((string)($payload['profile_name'] ?? '')) === '') {
            $errors[] = 'TR069 profile name is required.';
        }
        if (trim((string)($payload['acs_url'] ?? '')) === '') {
            $errors[] = 'TR069 server URL is required.';
        }

        return $errors;
    }

    private function normalizeSrvProfilePayload(array $input, ?array $existing = null): array
    {
        return [
            'olt_id' => isset($input['olt_id']) && (int)$input['olt_id'] > 0
                ? (int)$input['olt_id']
                : ($existing['olt_id'] ?? null),

            'profile_id' => (int)($input['profile_id'] ?? ($existing['profile_id'] ?? 0)),

            'profile_name' => trim((string)($input['profile_name'] ?? ($existing['profile_name'] ?? ''))),

            'eth_port_count' => (int)($input['eth_port_count']
                ?? $input['eth_ports']
                ?? ($existing['eth_port_count'] ?? 4)
            ),

            'service_mode' => strtoupper(trim((string)(
                $input['service_mode']
                ?? ($existing['service_mode'] ?? 'TRANSPARENT')
            ))),

            'description' => trim((string)($input['description'] ?? ($existing['description'] ?? ''))),
        ];
    }
    private function validateSrvProfilePayload(array $payload): array
    {
        $errors = [];
        if ((int)($payload['profile_id'] ?? 0) <= 0) {
            $errors[] = 'Service profile ID is required.';
        }
        if (trim((string)($payload['profile_name'] ?? '')) === '') {
            $errors[] = 'Service profile name is required.';
        }
        if ((int)($payload['eth_port_count'] ?? 0) <= 0) {
            $errors[] = 'ETH port count must be greater than 0.';
        }
        return $errors;
    }

    /* =========================================================
     * CLI PREVIEW BUILDERS
     * ========================================================= */

    private function buildLineProfileCliPreview(array $profile): string
    {
        $profileId = (int)($profile['profile_id'] ?? 0);
        $profileName = (string)($profile['profile_name'] ?? ('LP_' . $profileId));
        $customerCvlan = (int)($profile['customer_cvlan'] ?? 0);
        $managementVlan = (int)($profile['management_vlan'] ?? 0);
        $omccEncrypt = ((int)($profile['omcc_encrypt'] ?? 1) === 1) ? 'on' : 'off';
        $tr069Enable = ((int)($profile['tr069_management_enable'] ?? 1) === 1);
        $tr069IpIndex = (int)($profile['tr069_ip_index'] ?? 1);
        $tcontId = (int)($profile['tcont_id'] ?? 1);
        $dbaProfileId = (int)($profile['dba_profile_id'] ?? 20);
        $gemSubscriberId = (int)($profile['gem_subscriber_id'] ?? 1);
        $gemManagementId = (int)($profile['gem_management_id'] ?? 2);
        $gemSubscriberEncrypt = ((int)($profile['gem_subscriber_encrypt'] ?? 1) === 1) ? 'on' : 'off';
        $gemManagementEncrypt = ((int)($profile['gem_management_encrypt'] ?? 1) === 1) ? 'on' : 'off';

        $lines = [];
        $lines[] = sprintf('ont-lineprofile gpon profile-id %d profile-name "%s"', $profileId, $profileName);
        $lines[] = sprintf('  omcc encrypt %s', $omccEncrypt);

        if ($tr069Enable) {
            $lines[] = '  tr069-management enable';
            $lines[] = sprintf('  tr069-management ip-index %d', $tr069IpIndex);
        }

        $lines[] = sprintf('  tcont %d dba-profile-id %d', $tcontId, $dbaProfileId);
        $lines[] = sprintf('  gem add %d eth tcont %d encrypt %s', $gemSubscriberId, $tcontId, $gemSubscriberEncrypt);
        $lines[] = sprintf('  gem add %d eth tcont %d encrypt %s', $gemManagementId, $tcontId, $gemManagementEncrypt);
        $lines[] = sprintf('  gem mapping %d 0 vlan %d', $gemSubscriberId, $customerCvlan);
        $lines[] = sprintf('  gem mapping %d 0 vlan %d', $gemManagementId, $managementVlan);
        $lines[] = '  commit';
        $lines[] = '  quit';

        return implode("\n", $lines);
    }

    private function buildDbaProfileCliPreview(array $profile): string
    {
        $profileId = (int)($profile['profile_id'] ?? 0);
        $profileName = (string)($profile['profile_name'] ?? ('DBA_' . $profileId));
        $profileType = strtolower((string)($profile['profile_type'] ?? 'type4'));
        $maxBandwidth = (int)($profile['max_bandwidth'] ?? 0);

        return sprintf(
            'dba-profile add profile-id %d profile-name "%s" %s max %d',
            $profileId,
            $profileName,
            $profileType,
            $maxBandwidth
        );
    }

    private function buildWanProfileCliPreview(array $profile): string
    {
        $profileId = (int)($profile['profile_id'] ?? 0);
        $profileName = (string)($profile['profile_name'] ?? ('WAN_' . $profileId));
        $connectionType = strtoupper((string)($profile['connection_type'] ?? 'PPPOE'));

        $lines = [];
        $lines[] = sprintf('ont wan-profile profile-id %d profile-name "%s"', $profileId, $profileName);

        if ($connectionType === 'PPPOE') {
            $lines[] = '  nat enable';
        }

        $lines[] = '  quit';

        return implode("\n", $lines);
    }

    private function buildTr069ProfileCliPreview(array $profile): string
    {
        $profileId = (int)($profile['profile_id'] ?? 0);
        $profileName = (string)($profile['profile_name'] ?? ('TR069_' . $profileId));
        $serverUrl = (string)($profile['acs_url'] ?? '');
        $username = (string)($profile['acs_username'] ?? '');
        $password = (string)($profile['acs_password'] ?? '');

        $lines = [];
        $lines[] = sprintf(
            'ont tr069-server-profile add profile-id %d profile-name "%s" url',
            $profileId,
            $profileName
        );

        $lines[] = sprintf(
            '"%s" user "%s" "%s"',
            $serverUrl,
            $username,
            $password
        );

        return implode("\n", $lines);
    }

    private function buildSrvProfileCliPreview(array $profile): string
    {
        $profileId = (int)($profile['profile_id'] ?? 0);
        $profileName = (string)($profile['profile_name'] ?? ('SRV_' . $profileId));
        $ethPorts = (int)($profile['eth_port_count'] ?? 4);

        $lines = [];
        $lines[] = sprintf('ont-srvprofile gpon profile-id %d profile-name "%s"', $profileId, $profileName);
        $lines[] = sprintf('  ont-port eth %d', $ethPorts);

        for ($i = 1; $i <= $ethPorts; $i++) {
            $lines[] = sprintf('  port vlan eth %d transparent', $i);
        }

        $lines[] = '  commit';
        $lines[] = '  quit';

        return implode("\n", $lines);
    }

    private function parseSvlanCsv(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $csv);
        $seen = [];
        $out = [];

        foreach ($parts as $part) {
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }

            $svlan = (int)$part;
            if ($svlan <= 0) {
                continue;
            }

            if (!isset($seen[$svlan])) {
                $seen[$svlan] = true;
                $out[] = $svlan;
            }
        }

        sort($out, SORT_NUMERIC);
        return $out;
    }

    private function runDbaProfileScript(array $olt, array $payload): array
    {
        return $this->runOltScript('olt_dba_profile_apply.py', $olt, $payload);
    }

    private function runLineProfileScript(array $olt, array $payload): array
    {
        return $this->runOltScript('olt_line_profile_apply.py', $olt, $payload);
    }

    private function runWanProfileScript(array $olt, array $payload): array
    {
        return $this->runOltScript('olt_wan_profile_apply.py', $olt, $payload);
    }

    private function runTr069ProfileScript(array $olt, array $payload): array
    {
        return $this->runOltScript('olt_tr069_profile_apply.py', $olt, $payload);
    }

    private function runSrvProfileScript(array $olt, array $payload): array
    {
        return $this->runOltScript('olt_srv_profile_apply.py', $olt, $payload);
    }

    private function deriveBoardStatus(array $portRow): string
    {
        $boardStatus = strtoupper((string)($portRow['board_status'] ?? ''));
        if ($boardStatus !== '') {
            return $boardStatus;
        }

        $active = strtoupper((string)($portRow['active_state'] ?? ''));
        $link = strtoupper((string)($portRow['link_status'] ?? ''));

        if ($active === 'ACTIVE') {
            return 'ACTIVE';
        }

        if ($link === 'ONLINE') {
            return 'NORMAL';
        }

        return strtoupper((string)($portRow['board_type'] ?? '')) === 'GPON'
            ? 'NORMAL'
            : 'UNKNOWN';
    }

    private function mergeDeviceOmciPayload(array $payload, array $input, ?array $existing = null): array
    {
        $payload['enable_home_gateway_omci'] = array_key_exists('enable_home_gateway_omci', $input)
            ? (int)((string)$input['enable_home_gateway_omci'] === '1')
            : (int)($existing['enable_home_gateway_omci'] ?? 0);

        $payload['auto_detect_omci_support'] = array_key_exists('auto_detect_omci_support', $input)
            ? (int)((string)$input['auto_detect_omci_support'] === '1')
            : (int)($existing['auto_detect_omci_support'] ?? 0);

        return $payload;
    }

    private function applyOmciIfNeeded(array $payload, ?array $existing = null): array
    {
        $newEnabled = (int)($payload['enable_home_gateway_omci'] ?? 0);
        $newAutoDetect = (int)($payload['auto_detect_omci_support'] ?? 0);

        /*
         * OMCI command should only be active when BOTH toggles are enabled.
         */
        $newEffectiveEnabled = ($newEnabled === 1 && $newAutoDetect === 1) ? 1 : 0;

        if ($existing === null) {
            if ($newEffectiveEnabled !== 1) {
                return [
                    'success' => true,
                    'message' => 'OMCI config disabled. Script skipped on create.',
                    'skipped' => true,
                ];
            }

            return $this->runOmciConfigScript($payload, [
                'mode' => 'enable',
                'save_config' => true,
            ]);
        }

        $oldEnabled = (int)($existing['enable_home_gateway_omci'] ?? 0);
        $oldAutoDetect = (int)($existing['auto_detect_omci_support'] ?? 0);
        $oldEffectiveEnabled = ($oldEnabled === 1 && $oldAutoDetect === 1) ? 1 : 0;

        if ($newEffectiveEnabled === $oldEffectiveEnabled) {
            return [
                'success' => true,
                'message' => 'OMCI config unchanged.',
                'skipped' => true,
            ];
        }

        return $this->runOmciConfigScript($payload, [
            'mode' => $newEffectiveEnabled === 1 ? 'enable' : 'disable',
            'save_config' => true,
        ]);
    }

    private function runOmciConfigScript(array $olt, array $payload): array
    {
        return $this->runOltScript('omci_config.py', $olt, $payload);
    }

    private function runControlBoardVlanScript(array $olt, array $payload): array
    {
        return $this->runOltScript('olt_control_board_vlan_apply.py', $olt, $payload);
    }

    private function runOltScript(string $scriptFile, array $olt, array $payload): array
    {
        $scriptPath = __DIR__ . '/../Scripts/' . $scriptFile;

        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'message' => "Script not found: {$scriptFile}",
                'script' => $scriptPath,
            ];
        }

        $scriptPayload = array_merge([
            'host' => $olt['ip_address'] ?? '',
            'username' => $olt['username'] ?? '',
            'password' => $olt['password'] ?? '',
            'port' => 22,
            'dry_run' => false,
            'save_config' => true,
        ], $payload);

        $cmd = sprintf(
            'python3 %s %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg(json_encode($scriptPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        );

        $output = shell_exec($cmd);
        $decoded = json_decode((string)$output, true);

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Invalid response from script.',
                'raw' => $output,
                'command' => $cmd,
            ];
        }

        return $decoded;
    }
}