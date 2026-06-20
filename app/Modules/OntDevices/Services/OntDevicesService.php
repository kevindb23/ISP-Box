<?php

namespace App\Modules\OntDevices\Services;

use App\Modules\OntDevices\Repositories\OntDevicesRepository;

class OntDevicesService
{
    private OntDevicesRepository $repo;

    public function __construct(OntDevicesRepository $repo)
    {
        $this->repo = $repo;
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTORY
    |--------------------------------------------------------------------------
    */

    public function getInventory(): array
    {
        return $this->repo->getAll();
    }

    public function getInventoryById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    /*
    |--------------------------------------------------------------------------
    | DISCOVERY
    |--------------------------------------------------------------------------
    */

    public function getDiscovery(): array
    {
        return $this->repo->getDiscovery();
    }

    public function discoverAndSave(): array
    {
        try {
            $scriptPath = __DIR__ . '/../Scripts/olt_autofind.py';
            $command = 'python3 ' . escapeshellarg($scriptPath) . ' 2>&1';

            $output = shell_exec($command);

            if (!$output || trim($output) === '') {
                return [
                    'ok' => false,
                    'message' => 'OLT script returned empty output.',
                    'errors' => [],
                ];
            }

            $json = json_decode($output, true);

            if (!is_array($json)) {
                return [
                    'ok' => false,
                    'message' => 'Invalid script JSON response.',
                    'errors' => [],
                    'raw' => $output,
                ];
            }

            if (!($json['success'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => (string)($json['error'] ?? 'OLT discovery failed.'),
                    'errors' => [],
                    'raw' => $json,
                ];
            }

            $devices = is_array($json['data'] ?? null) ? $json['data'] : [];
            $saved = 0;

            foreach ($devices as $d) {
                if (!is_array($d)) {
                    continue;
                }

                $serial = strtoupper(trim((string)($d['serial_number'] ?? '')));
                if ($serial === '') {
                    continue;
                }

                $fsp = trim((string)($d['fsp'] ?? ''));
                $frame = null;
                $slot = null;
                $port = null;

                if ($fsp !== '' && preg_match('/^(\d+)\/(\d+)\/(\d+)$/', $fsp, $m)) {
                    $frame = (int)$m[1];
                    $slot = (int)$m[2];
                    $port = (int)$m[3];
                }

                $ok = $this->repo->saveDiscovery([
                    'serial_number' => $serial,
                    'model' => trim((string)($d['model'] ?? '')) ?: null,
                    'vendor' => trim((string)($d['vendor'] ?? '')) ?: null,
                    'vendor_code' => trim((string)($d['vendor_code'] ?? '')) ?: null,
                    'fsp' => $fsp !== '' ? $fsp : null,
                    'frame' => $frame,
                    'slot' => $slot,
                    'port' => $port,
                    'status' => trim((string)($d['status'] ?? 'NEW')) ?: 'NEW',
                    'last_seen' => $d['last_seen'] ?? null,
                    'autofind_time' => $d['autofind_time'] ?? ($d['last_seen'] ?? null),
                ]);

                if ($ok) {
                    $saved++;
                }
            }

            return [
                'ok' => true,
                'message' => 'Discovery completed.',
                'count' => count($devices),
                'saved' => $saved,
                'data' => $devices,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ];
        }
    }

    public function addToInventory(array $data): array
    {
        try {
            $serial = strtoupper(trim((string)($data['serial_number'] ?? '')));

            if ($serial === '') {
                return [
                    'ok' => false,
                    'message' => 'Serial number is required.',
                    'errors' => [],
                ];
            }

            if ($this->repo->existsBySerial($serial)) {
                return [
                    'ok' => false,
                    'message' => 'Serial number already exists in inventory.',
                    'errors' => [],
                ];
            }

            $this->repo->addToInventory([
                'serial_number' => $serial,
                'model' => trim((string)($data['model'] ?? '')) ?: null,
                'vendor' => trim((string)($data['vendor'] ?? '')) ?: null,
                'mac_address' => trim((string)($data['mac_address'] ?? '')) ?: null,
                'status' => trim((string)($data['status'] ?? 'UNASSIGNED')) ?: 'UNASSIGNED',
                'equipment_id' => trim((string)($data['equipment_id'] ?? '')) ?: null,
                'subscriber_id' => ($data['subscriber_id'] ?? '') !== '' ? (int)$data['subscriber_id'] : null,
            ]);

            return [
                'ok' => true,
                'message' => 'ONT added to inventory.',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACS
    |--------------------------------------------------------------------------
    */

    public function getAcsDevices(): array
    {
        $acs = new AcsService();
        return $acs->normalizeDevices($acs->getDevices());
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD
    |--------------------------------------------------------------------------
    */

    public function create(array $data): array
    {
        try {
            $serial = strtoupper(trim((string)($data['serial_number'] ?? '')));

            if ($serial === '') {
                return [
                    'ok' => false,
                    'message' => 'Serial number is required.',
                    'errors' => [],
                ];
            }

            if ($this->repo->existsBySerial($serial)) {
                return [
                    'ok' => false,
                    'message' => 'Serial number already exists.',
                    'errors' => [],
                ];
            }

            $this->repo->create([
                'serial_number' => $serial,
                'model' => trim((string)($data['model'] ?? '')) ?: null,
                'vendor' => trim((string)($data['vendor'] ?? '')) ?: null,
                'mac_address' => trim((string)($data['mac_address'] ?? '')) ?: null,
                'status' => trim((string)($data['status'] ?? 'UNASSIGNED')) ?: 'UNASSIGNED',
                'equipment_id' => trim((string)($data['equipment_id'] ?? '')) ?: null,
                'subscriber_id' => ($data['subscriber_id'] ?? '') !== '' ? (int)$data['subscriber_id'] : null,
            ]);

            return [
                'ok' => true,
                'message' => 'ONT created.',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ];
        }
    }

    public function update(array $data): array
    {
        try {
            $id = (int)($data['id'] ?? 0);
            $serial = strtoupper(trim((string)($data['serial_number'] ?? '')));

            if ($id <= 0) {
                return [
                    'ok' => false,
                    'message' => 'Invalid ONT ID.',
                    'errors' => [],
                ];
            }

            if ($serial === '') {
                return [
                    'ok' => false,
                    'message' => 'Serial number is required.',
                    'errors' => [],
                ];
            }

            if ($this->repo->existsBySerial($serial, $id)) {
                return [
                    'ok' => false,
                    'message' => 'Serial number already exists.',
                    'errors' => [],
                ];
            }

            $ok = $this->repo->update([
                'id' => $id,
                'serial_number' => $serial,
                'model' => trim((string)($data['model'] ?? '')) ?: null,
                'vendor' => trim((string)($data['vendor'] ?? '')) ?: null,
                'mac_address' => trim((string)($data['mac_address'] ?? '')) ?: null,
                'status' => trim((string)($data['status'] ?? 'UNASSIGNED')) ?: 'UNASSIGNED',
                'equipment_id' => trim((string)($data['equipment_id'] ?? '')) ?: null,
                'subscriber_id' => ($data['subscriber_id'] ?? '') !== '' ? (int)$data['subscriber_id'] : null,
            ]);

            if (!$ok) {
                return [
                    'ok' => false,
                    'message' => 'Failed to update ONT.',
                    'errors' => [],
                ];
            }

            return [
                'ok' => true,
                'message' => 'ONT updated.',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ];
        }
    }

    public function delete(int $id): array
    {
        try {
            if ($id <= 0) {
                return [
                    'ok' => false,
                    'message' => 'Invalid ONT ID.',
                    'errors' => [],
                ];
            }

            $ok = $this->repo->delete($id);

            if (!$ok) {
                return [
                    'ok' => false,
                    'message' => 'Failed to delete ONT.',
                    'errors' => [],
                ];
            }

            return [
                'ok' => true,
                'message' => 'ONT deleted.',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ];
        }
    }
}