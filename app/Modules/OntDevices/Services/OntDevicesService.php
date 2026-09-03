<?php

namespace App\Modules\OntDevices\Services;

use App\Infrastructure\NetworkAutomation\NetworkCommandRunner;
use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\OntDevices\DTOs\CreateOntDevicesDTO;
use App\Modules\OntDevices\Entities\OntDevices;
use App\Modules\OntDevices\Repositories\OntDevicesRepository;
use App\Modules\OntDevices\Validators\CreateOntDevicesValidator;

class OntDevicesService
{
    private OntDevicesRepository $repo;

    public function __construct(
        OntDevicesRepository $repo,
        private AuditService $audit,
        private AcsService $acs,
        private NetworkCommandRunner $networkRunner
    )
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
        return array_map(
            static fn(array $row): array => (new OntDevices($row))->toArray(),
            $this->repo->getAll()
        );
    }

    public function getInventoryById(int $id): ?array
    {
        $row = $this->repo->findById($id);
        return $row ? (new OntDevices($row))->toArray() : null;
    }

    public function getSubscriberOptions(): array
    {
        return $this->repo->getSubscriberOptions();
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

    public function discoverAndSave(int $oltId = 0): array
    {
        try {
            $scriptPath = __DIR__ . '/../Scripts/olt_autofind.py';
            $olt = $this->repo->findDiscoveryOlt($oltId > 0 ? $oltId : null);

            if (!$olt) {
                return [
                    'ok' => false,
                    'message' => 'No OLT with complete discovery credentials is configured.',
                    'errors' => [],
                ];
            }

            $execution = $this->networkRunner->runPythonJson($scriptPath, [
                'host' => (string)($olt['ip_address'] ?? ''),
                'username' => (string)($olt['username'] ?? ''),
                'password' => (string)($olt['password'] ?? ''),
                'ssh_port' => 22,
            ]);
            $output = $execution->stdout !== '' ? $execution->stdout : $execution->stderr;

            if ($output === '') {
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
                    'operation_id' => bin2hex(random_bytes(8)),
                ];
            }

            if (!($json['success'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => (string)($json['error'] ?? 'OLT discovery failed.'),
                    'errors' => [],
                    'operation_id' => bin2hex(random_bytes(8)),
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

            $this->auditAction('DISCOVER', 'Discovered ONT devices and saved discovery results.', null, [
                'olt_id' => (int)$olt['id'],
                'discovered_count' => count($devices), 'saved_count' => $saved,
            ]);

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
                'message' => 'ONT discovery could not be completed.',
                'errors' => [],
            ];
        }
    }

    public function addToInventory(array $data): array
    {
        try {
            $dto = CreateOntDevicesDTO::fromArray($data);
            $payload = $dto->toArray();
            $errors = CreateOntDevicesValidator::validate($payload);
            if ($errors !== []) {
                return [
                    'ok' => false,
                    'message' => $errors[0],
                    'errors' => $errors,
                ];
            }
            $serial = $payload['serial_number'];

            if (($payload['subscriber_id'] ?? null) !== null && !$this->repo->subscriberExists((int)$payload['subscriber_id'])) {
                return ['ok' => false, 'message' => 'Selected subscriber was not found.', 'errors' => ['Invalid subscriber.']];
            }

            $discovery = $this->repo->findDiscoveryBySerial($serial);
            if ($discovery) {
                foreach (['frame', 'slot', 'port'] as $field) {
                    if (($payload[$field] ?? null) === null && ($discovery[$field] ?? null) !== null) {
                        $payload[$field] = (int)$discovery[$field];
                    }
                }

                if (($payload['olt_id'] ?? null) === null) {
                    $sourceOlt = $this->repo->findDiscoveryOlt();
                    $payload['olt_id'] = $sourceOlt ? (int)$sourceOlt['id'] : null;
                }
            }

            if ($this->repo->existsBySerial($serial)) {
                return [
                    'ok' => false,
                    'message' => 'Serial number already exists in inventory.',
                    'errors' => [],
                ];
            }

            $id = $this->repo->addToInventory($payload);
            $this->auditAction('ADD_TO_INVENTORY', 'Added ONT to inventory.', $id, ['serial_number' => $serial]);

            return [
                'ok' => true,
                'message' => 'ONT added to inventory.',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'The ONT could not be added to inventory.',
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
        $devices = $this->acs->normalizeDevices($this->acs->getDevices());
        $this->acs->syncSnapshots($devices);
        return $devices;
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD
    |--------------------------------------------------------------------------
    */

    public function create(array $data): array
    {
        try {
            $dto = CreateOntDevicesDTO::fromArray($data);
            $payload = $dto->toArray();
            $errors = CreateOntDevicesValidator::validate($payload);
            if ($errors !== []) {
                return [
                    'ok' => false,
                    'message' => $errors[0],
                    'errors' => $errors,
                ];
            }
            $serial = $payload['serial_number'];

            if (($payload['subscriber_id'] ?? null) !== null && !$this->repo->subscriberExists((int)$payload['subscriber_id'])) {
                return ['ok' => false, 'message' => 'Selected subscriber was not found.', 'errors' => ['Invalid subscriber.']];
            }

            if ($this->repo->existsBySerial($serial)) {
                return [
                    'ok' => false,
                    'message' => 'Serial number already exists.',
                    'errors' => [],
                ];
            }

            $id = $this->repo->create($payload);
            $this->auditAction('CREATE', 'Created ONT inventory record.', $id, ['serial_number' => $serial]);

            return [
                'ok' => true,
                'message' => 'ONT created.',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'The ONT inventory record could not be created.',
                'errors' => [],
            ];
        }
    }

    public function update(array $data): array
    {
        try {
            $dto = CreateOntDevicesDTO::fromArray($data);
            $payload = $dto->toArray();
            $id = (int)($payload['id'] ?? 0);
            $serial = (string)($payload['serial_number'] ?? '');

            if (($payload['subscriber_id'] ?? null) !== null && !$this->repo->subscriberExists((int)$payload['subscriber_id'])) {
                return ['ok' => false, 'message' => 'Selected subscriber was not found.', 'errors' => ['Invalid subscriber.']];
            }

            if ($id <= 0) {
                return [
                    'ok' => false,
                    'message' => 'Invalid ONT ID.',
                    'errors' => [],
                ];
            }

            $errors = CreateOntDevicesValidator::validate($payload);
            if ($errors !== []) {
                return [
                    'ok' => false,
                    'message' => $errors[0],
                    'errors' => $errors,
                ];
            }

            if ($this->repo->existsBySerial($serial, $id)) {
                return [
                    'ok' => false,
                    'message' => 'Serial number already exists.',
                    'errors' => [],
                ];
            }

            $old = $this->repo->findById($id);
            if (!$old) {
                return [
                    'ok' => false,
                    'message' => 'ONT record not found.',
                    'errors' => [],
                ];
            }
            $ok = $this->repo->update($payload);

            if (!$ok) {
                return [
                    'ok' => false,
                    'message' => 'Failed to update ONT.',
                    'errors' => [],
                ];
            }

            $this->auditAction('UPDATE', 'Updated ONT inventory record.', $id, [
                'old' => $old ? (new OntDevices($old))->toArray() : null,
                'new' => (new OntDevices($payload))->toArray(),
            ]);

            return [
                'ok' => true,
                'message' => 'ONT updated.',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'The ONT inventory record could not be updated.',
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

            $old = $this->repo->findById($id);
            if (!$old) {
                return [
                    'ok' => false,
                    'message' => 'ONT record not found.',
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

            $this->auditAction('DELETE', 'Deleted ONT inventory record.', $id, [
                'old' => $old ? (new OntDevices($old))->toArray() : null,
            ]);

            return [
                'ok' => true,
                'message' => 'ONT deleted.',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'The ONT inventory record could not be deleted.',
                'errors' => [],
            ];
        }
    }

    private function auditAction(string $action, string $description, ?int $objectId, array $metadata = []): void
    {
        $this->audit->logEvent(new AuditEventDTO(
            module: 'ONT_DEVICES', action: $action, description: $description,
            objectType: 'ONT_DEVICE', objectId: $objectId, metadata: $metadata
        ));
    }
}
