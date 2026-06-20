<?php

namespace App\Modules\NapManagement\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\NapManagement\Repositories\NapManagementRepository;
use App\Modules\NapManagement\DTOs\CreateNapManagementDTO;
use App\Modules\NapManagement\DTOs\UpdateNapManagementDTO;
use App\Modules\NapManagement\Validators\CreateNapManagementValidator;
use App\Modules\NapManagement\Validators\UpdateNapManagementValidator;
use RuntimeException;
use Throwable;

class NapManagementService
{
    private NapManagementRepository $repo;
    private CreateNapManagementValidator $createValidator;
    private UpdateNapManagementValidator $updateValidator;
    private ?AuditService $audit;

    public function __construct(
        NapManagementRepository $repo,
        ?AuditService $audit = null
    ) {
        $this->repo = $repo;
        $this->audit = $audit;
        $this->createValidator = new CreateNapManagementValidator();
        $this->updateValidator = new UpdateNapManagementValidator();
    }

    private function ok(string $message = '', array $extra = []): array
    {
        return array_merge([
            'ok' => true,
            'message' => $message,
            'errors' => [],
        ], $extra);
    }

    private function fail(string $message, array $errors = []): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    private function normalizeInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function normalizeDecimal($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string)$value);
        return is_numeric($value) ? $value : null;
    }

    private function normalizeBoxType($value): string
    {
        $value = strtoupper(trim((string)($value ?? 'NAP')));
        return in_array($value, ['LCP', 'NAP'], true) ? $value : 'NAP';
    }

    private function normalizeStatus($value): string
    {
        $value = strtoupper(trim((string)($value ?? 'ACTIVE')));
        return in_array($value, ['ACTIVE', 'INACTIVE', 'MAINTENANCE', 'FAULTY'], true) ? $value : 'ACTIVE';
    }

    private function normalizeFeedMode($value, string $parentType = 'LCP'): string
    {
        $value = strtoupper(trim((string)$value));
        if ($value !== '' && in_array($value, ['DIRECT_FROM_LCP', 'CASCADE_FROM_NAP', 'FIBER'], true)) {
            return $value;
        }

        return strtoupper($parentType) === 'NAP' ? 'CASCADE_FROM_NAP' : 'DIRECT_FROM_LCP';
    }

    private function resolveMaintenanceEnabled(array $data): bool
    {
        if (array_key_exists('maintenance_mode', $data)) {
            return (int)$data['maintenance_mode'] === 1;
        }

        if (array_key_exists('mode', $data)) {
            return (int)$data['mode'] === 1;
        }

        if (array_key_exists('status', $data)) {
            return strtoupper(trim((string)$data['status'])) === 'MAINTENANCE';
        }

        return true;
    }

    private function buildLcpCreatePayload(array $data): array
    {
        $dto = CreateNapManagementDTO::fromArray([
            'box_type' => 'LCP',
            'box_name' => trim((string)($data['lcp_name'] ?? $data['box_name'] ?? '')),
            'total_ports' => $data['total_ports'] ?? null,
            'location' => $data['location'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'description' => $data['remarks'] ?? $data['description'] ?? null,
        ]);

        $validation = $this->createValidator->validateLcp([
            'lcp_name' => $dto->box_name,
            'box_code' => $data['box_code'] ?? null,
            'total_ports' => $dto->total_ports,
            'parent_odf_id' => $data['parent_odf_id'] ?? null,
            'parent_odf_port_id' => $data['parent_odf_port_id'] ?? null,
        ]);

        if (!empty($validation)) {
            throw new RuntimeException((string)reset($validation));
        }

        $totalPorts = (int)($dto->total_ports ?? 0);
        if ($totalPorts <= 0) {
            throw new RuntimeException('Splitter ports must be greater than 0.');
        }

        $boxName = trim((string)$dto->box_name);
        if ($boxName === '') {
            throw new RuntimeException('LCP name is required.');
        }

        $boxCode = trim((string)($data['box_code'] ?? ''));
        if ($boxCode === '') {
            throw new RuntimeException('LCP code is required.');
        }

        $parentOdfId = $this->normalizeInt($data['parent_odf_id'] ?? null);
        $parentOdfPortId = $this->normalizeInt($data['parent_odf_port_id'] ?? null);
        $inputPortNumber = $this->normalizeInt($data['input_port_number'] ?? null);

        if (!$parentOdfId || !$parentOdfPortId) {
            throw new RuntimeException('Parent ODF and ODF port are required.');
        }

        $this->assertOdfNotInMaintenance($parentOdfId);

        if ($inputPortNumber !== null && ($inputPortNumber < 1 || $inputPortNumber > $totalPorts)) {
            throw new RuntimeException('Selected LCP input port is invalid.');
        }

        if ($this->repo->boxNameExistsByType('LCP', $boxName)) {
            throw new RuntimeException('LCP name already exists.');
        }

        if ($this->repo->boxCodeExistsByType('LCP', $boxCode)) {
            throw new RuntimeException('LCP code already exists.');
        }

        if ($this->repo->isOdfPortAlreadyAssignedToLcp($parentOdfPortId)) {
            throw new RuntimeException('Selected ODF port is already used by another LCP.');
        }

        return [
            'box_type' => 'LCP',
            'box_code' => $boxCode,
            'box_name' => $boxName,
            'location' => $dto->location,
            'address' => $data['address'] ?? null,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'pole_code' => $data['pole_code'] ?? null,
            'olt_id' => null,
            'olt_port_id' => null,
            'parent_odf_id' => $parentOdfId,
            'parent_odf_port_id' => $parentOdfPortId,
            'input_port_number' => $inputPortNumber,
            'status' => $this->normalizeStatus($data['status'] ?? 'ACTIVE'),
            'remarks' => $dto->description,
            'splitter_ports' => $totalPorts,
        ];
    }

    private function buildLcpUpdatePayload(int $id, array $data, array $current): array
    {
        $dto = UpdateNapManagementDTO::fromArray([
            'box_id' => $id,
            'box_type' => 'LCP',
            'box_name' => trim((string)($data['lcp_name'] ?? $data['box_name'] ?? $current['box_name'] ?? '')),
            'total_ports' => $data['total_ports'] ?? $data['rebuild_total_ports'] ?? $current['splitter_ratio'] ?? null,
            'location' => $data['location'] ?? ($current['location'] ?? null),
            'latitude' => $data['latitude'] ?? ($current['latitude'] ?? null),
            'longitude' => $data['longitude'] ?? ($current['longitude'] ?? null),
            'description' => $data['remarks'] ?? $data['description'] ?? ($current['remarks'] ?? null),
        ]);

        $validation = $this->updateValidator->validateLcp($id, [
            'lcp_name' => $dto->box_name,
            'box_code' => $data['box_code'] ?? ($current['box_code'] ?? null),
            'total_ports' => $dto->total_ports,
            'parent_odf_id' => $data['parent_odf_id'] ?? ($current['parent_odf_id'] ?? null),
            'parent_odf_port_id' => $data['parent_odf_port_id'] ?? ($current['parent_odf_port_id'] ?? null),
        ]);

        if (!empty($validation)) {
            throw new RuntimeException((string)reset($validation));
        }

        $totalPorts = (int)($dto->total_ports ?? 0);
        if ($totalPorts <= 0) {
            throw new RuntimeException('Splitter ports must be greater than 0.');
        }

        $boxName = trim((string)$dto->box_name);
        if ($boxName === '') {
            throw new RuntimeException('LCP name is required.');
        }

        $boxCode = trim((string)($data['box_code'] ?? ($current['box_code'] ?? '')));
        if ($boxCode === '') {
            throw new RuntimeException('LCP code is required.');
        }

        $parentOdfId = $this->normalizeInt($data['parent_odf_id'] ?? ($current['parent_odf_id'] ?? null));
        $parentOdfPortId = $this->normalizeInt($data['parent_odf_port_id'] ?? ($current['parent_odf_port_id'] ?? null));
        $inputPortNumber = $this->normalizeInt($data['input_port_number'] ?? ($current['input_port_number'] ?? null));

        if (!$parentOdfId || !$parentOdfPortId) {
            throw new RuntimeException('Parent ODF and ODF port are required.');
        }

        $this->assertOdfNotInMaintenance($parentOdfId);

        if ($inputPortNumber !== null && ($inputPortNumber < 1 || $inputPortNumber > $totalPorts)) {
            throw new RuntimeException('Selected LCP input port is invalid.');
        }

        if ($this->repo->boxNameExistsByType('LCP', $boxName, $id)) {
            throw new RuntimeException('LCP name already exists.');
        }

        if ($this->repo->boxCodeExistsByType('LCP', $boxCode, $id)) {
            throw new RuntimeException('LCP code already exists.');
        }

        if ($this->repo->isOdfPortAlreadyAssignedToLcp($parentOdfPortId, $id)) {
            throw new RuntimeException('Selected ODF port is already used by another LCP.');
        }

        return [
            'box_type' => 'LCP',
            'box_code' => $boxCode,
            'box_name' => $boxName,
            'location' => $dto->location,
            'address' => $data['address'] ?? ($current['address'] ?? null),
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'pole_code' => $data['pole_code'] ?? ($current['pole_code'] ?? null),
            'olt_id' => null,
            'olt_port_id' => null,
            'parent_odf_id' => $parentOdfId,
            'parent_odf_port_id' => $parentOdfPortId,
            'input_port_number' => $inputPortNumber,
            'status' => $this->normalizeStatus($data['status'] ?? ($current['status'] ?? 'ACTIVE')),
            'remarks' => $dto->description,
            'splitter_ports' => $totalPorts,
        ];
    }

    private function assertOdfNotInMaintenance(int $odfId): void
    {
        if ($odfId > 0 && $this->repo->isOdfInMaintenance($odfId)) {
            throw new RuntimeException('Cannot use this ODF while it is in maintenance mode.');
        }
    }

    private function assertBoxNotInMaintenance(int $boxId): void
    {
        if ($boxId > 0 && $this->repo->isBoxInMaintenance($boxId)) {
            throw new RuntimeException('Cannot use this parent box while it is in maintenance mode.');
        }
    }

    private function assertPortOwnerNotInMaintenance(int $portId): void
    {
        if ($portId <= 0) {
            return;
        }

        $status = strtoupper((string)$this->repo->getPortOwnerBoxStatus($portId));
        if ($status === 'MAINTENANCE') {
            throw new RuntimeException('Cannot use a port from a box that is in maintenance mode.');
        }
    }

    private function cascadeBoxMaintenanceDownstream(
        int $boxId,
        bool $enabled,
        ?string $reason,
        ?string $resolution
    ): void {
        $children = $this->repo->getNapChildrenBySourceBoxId($boxId);

        foreach ($children as $child) {
            $childId = (int)($child['id'] ?? 0);
            if ($childId <= 0) {
                continue;
            }

            $this->repo->setBoxMaintenance($childId, $enabled, $reason, $resolution, 'PARENT');
            $this->repo->syncPlannerNodeFromBoxId($childId);

            $this->cascadeBoxMaintenanceDownstream($childId, $enabled, $reason, $resolution);
        }
    }

    private function buildNapCreatePayload(array $data): array
    {
        $dto = CreateNapManagementDTO::fromArray([
            'box_type' => 'NAP',
            'box_name' => trim((string)($data['nap_name'] ?? $data['box_name'] ?? '')),
            'splitter_ports' => $data['splitter_ports'] ?? null,
            'parent_lcp_id' => $data['parent_lcp_id'] ?? null,
            'parent_nap_id' => $data['parent_nap_id'] ?? null,
            'parent_port_id' => $data['parent_port_id'] ?? null,
            'location' => $data['location'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'description' => $data['remarks'] ?? $data['description'] ?? null,
        ]);

        $parentType = strtoupper(trim((string)($data['parent_type'] ?? 'LCP')));

        $validation = $this->createValidator->validateNap([
            'nap_name' => $dto->box_name,
            'parent_type' => $parentType,
            'parent_lcp_id' => $dto->parent_lcp_id,
            'parent_nap_id' => $dto->parent_nap_id,
            'parent_port_id' => $dto->parent_port_id,
            'splitter_ports' => $dto->splitter_ports,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'status' => $data['status'] ?? 'active',
        ]);

        if (!empty($validation)) {
            throw new RuntimeException((string)reset($validation));
        }

        $splitterPorts = (int)($dto->splitter_ports ?? 0);
        if ($splitterPorts <= 0) {
            throw new RuntimeException('Splitter ports must be greater than 0.');
        }

        $sourceBoxId = null;
        if ($parentType === 'LCP') {
            $sourceBoxId = $this->normalizeInt($dto->parent_lcp_id);
        } elseif ($parentType === 'NAP') {
            $sourceBoxId = $this->normalizeInt($dto->parent_nap_id);
        }

        $sourcePortId = $this->normalizeInt($dto->parent_port_id);

        if (!$sourceBoxId) {
            throw new RuntimeException('Parent box is required.');
        }

        if (!$sourcePortId) {
            throw new RuntimeException('Parent port is required.');
        }

        $this->assertBoxNotInMaintenance($sourceBoxId);
        $this->assertPortOwnerNotInMaintenance($sourcePortId);

        if ($parentType === 'LCP') {
            $sourcePort = $this->repo->findLcpPortById($sourcePortId);

            if (!$sourcePort || (int)($sourcePort['lcp_id'] ?? 0) !== (int)$sourceBoxId) {
                throw new RuntimeException('Selected LCP uplink port is invalid.');
            }

            if (strtoupper((string)($sourcePort['status'] ?? 'AVAILABLE')) !== 'AVAILABLE') {
                throw new RuntimeException('Selected LCP uplink port is already in use.');
            }
        } else {
            $sourcePort = $this->repo->findPortById($sourcePortId);

            if (!$sourcePort) {
                throw new RuntimeException('Selected NAP parent port is invalid.');
            }

            if ($this->repo->isBoxPortAlreadyAssignedAsUplink($sourcePortId)) {
                throw new RuntimeException('Selected parent port is already used by another NAP.');
            }
        }

        return [
            'box' => [
                'box_type' => 'NAP',
                'box_code' => trim((string)($data['box_code'] ?? '')),
                'box_name' => $dto->box_name,
                'location' => $dto->location,
                'address' => $data['address'] ?? null,
                'latitude' => $dto->latitude,
                'longitude' => $dto->longitude,
                'pole_code' => $data['pole_code'] ?? null,
                'status' => $this->normalizeStatus($data['status'] ?? 'ACTIVE'),
                'remarks' => $dto->description,
                'input_port_number' => 1,
            ],
            'splitter_ports' => $splitterPorts,
            'uplink' => [
                'feed_mode' => $this->normalizeFeedMode($data['feed_mode'] ?? '', $parentType),
                'source_box_id' => $sourceBoxId,
                'source_port_id' => $sourcePortId,
                'source_port_kind' => $parentType === 'LCP' ? 'LCP_UPLINK' : 'SPLITTER',
                'source_cable_id' => $this->normalizeInt($data['source_cable_id'] ?? null),
                'source_fiber_core' => $this->normalizeInt($data['source_fiber_core'] ?? null),
                'splice_point' => $data['splice_point'] ?? null,
                'splice_note' => $data['splice_note'] ?? null,
            ],
        ];
    }

    private function buildNapUpdatePayload(int $id, array $data, array $current): array
    {
        $dto = UpdateNapManagementDTO::fromArray([
            'box_id' => $id,
            'box_type' => 'NAP',
            'box_name' => trim((string)($data['nap_name'] ?? $data['box_name'] ?? $current['box_name'] ?? '')),
            'splitter_ports' => $data['splitter_ports'] ?? $current['splitter_ratio'] ?? null,
            'parent_lcp_id' => $data['parent_lcp_id'] ?? null,
            'parent_nap_id' => $data['parent_nap_id'] ?? null,
            'parent_port_id' => $data['parent_port_id'] ?? null,
            'location' => $data['location'] ?? ($current['location'] ?? null),
            'latitude' => $data['latitude'] ?? ($current['latitude'] ?? null),
            'longitude' => $data['longitude'] ?? ($current['longitude'] ?? null),
            'description' => $data['remarks'] ?? $data['description'] ?? ($current['remarks'] ?? null),
        ]);

        $parentType = strtoupper(trim((string)($data['parent_type'] ?? (($current['feed_mode'] ?? '') === 'CASCADE_FROM_NAP' ? 'NAP' : 'LCP'))));

        $validation = $this->updateValidator->validateNap($id, [
            'nap_name' => $dto->box_name,
            'parent_type' => $parentType,
            'parent_lcp_id' => $dto->parent_lcp_id,
            'parent_nap_id' => $dto->parent_nap_id,
            'parent_port_id' => $dto->parent_port_id,
            'splitter_ports' => $dto->splitter_ports,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'status' => $data['status'] ?? 'active',
        ]);

        if (!empty($validation)) {
            throw new RuntimeException((string)reset($validation));
        }

        $splitterPorts = (int)($dto->splitter_ports ?? 0);
        if ($splitterPorts <= 0) {
            throw new RuntimeException('Splitter ports must be greater than 0.');
        }

        $sourceBoxId = null;
        if ($parentType === 'LCP') {
            $sourceBoxId = $this->normalizeInt($dto->parent_lcp_id);
        } elseif ($parentType === 'NAP') {
            $sourceBoxId = $this->normalizeInt($dto->parent_nap_id);
        }

        $sourcePortId = $this->normalizeInt($dto->parent_port_id);

        if (!$sourceBoxId) {
            throw new RuntimeException('Parent box is required.');
        }

        if (!$sourcePortId) {
            throw new RuntimeException('Parent port is required.');
        }

        $this->assertBoxNotInMaintenance($sourceBoxId);
        $this->assertPortOwnerNotInMaintenance($sourcePortId);

        if ($parentType === 'LCP') {
            $sourcePort = $this->repo->findLcpPortById($sourcePortId);

            if (!$sourcePort || (int)($sourcePort['lcp_id'] ?? 0) !== (int)$sourceBoxId) {
                throw new RuntimeException('Selected LCP uplink port is invalid.');
            }

            $currentUsesSameLcpPort =
                strtoupper((string)($current['feed_mode'] ?? '')) === 'DIRECT_FROM_LCP' &&
                (int)($current['source_port_id'] ?? 0) === $sourcePortId;

            if (
                !$currentUsesSameLcpPort &&
                strtoupper((string)($sourcePort['status'] ?? 'AVAILABLE')) !== 'AVAILABLE'
            ) {
                throw new RuntimeException('Selected LCP uplink port is already in use.');
            }
        } else {
            $sourcePort = $this->repo->findPortById($sourcePortId);

            if (!$sourcePort) {
                throw new RuntimeException('Selected NAP parent port is invalid.');
            }

            if ($this->repo->isBoxPortAlreadyAssignedAsUplink($sourcePortId, $id)) {
                throw new RuntimeException('Selected parent port is already used by another NAP.');
            }
        }

        return [
            'box' => [
                'box_type' => 'NAP',
                'box_code' => trim((string)($data['box_code'] ?? ($current['box_code'] ?? ''))),
                'box_name' => $dto->box_name,
                'location' => $dto->location,
                'address' => $data['address'] ?? ($current['address'] ?? null),
                'latitude' => $dto->latitude,
                'longitude' => $dto->longitude,
                'pole_code' => $data['pole_code'] ?? ($current['pole_code'] ?? null),
                'status' => $this->normalizeStatus($data['status'] ?? ($current['status'] ?? 'ACTIVE')),
                'remarks' => $dto->description,
                'input_port_number' => 1,
            ],
            'splitter_ports' => $splitterPorts,
            'uplink' => [
                'feed_mode' => $this->normalizeFeedMode($data['feed_mode'] ?? ($current['feed_mode'] ?? ''), $parentType),
                'source_box_id' => $sourceBoxId,
                'source_port_id' => $sourcePortId,
                'source_port_kind' => $parentType === 'LCP' ? 'LCP_UPLINK' : 'SPLITTER',
                'source_cable_id' => $this->normalizeInt($data['source_cable_id'] ?? ($current['source_cable_id'] ?? null)),
                'source_fiber_core' => $this->normalizeInt($data['source_fiber_core'] ?? ($current['source_fiber_core'] ?? null)),
                'splice_point' => $data['splice_point'] ?? ($current['splice_point'] ?? null),
                'splice_note' => $data['splice_note'] ?? ($current['splice_note'] ?? null),
            ],
        ];
    }

    public function getLcpAll(): array
    {
        return $this->repo->getBoxesByType('LCP');
    }

    public function getLcpById(int $id): ?array
    {
        $box = $this->repo->getBoxByIdWithStats($id);
        if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'LCP') {
            return null;
        }

        return $box;
    }

    public function getNapAll(): array
    {
        return $this->repo->getBoxesByType('NAP');
    }

    public function getNapById(int $id): ?array
    {
        $box = $this->repo->getBoxByIdWithStats($id);
        if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'NAP') {
            return null;
        }

        return $box;
    }

    public function getAvailablePortsByLcp(int $lcpId, ?int $includeCurrentPortId = null): array
    {
        $box = $this->repo->findBoxById($lcpId);
        if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'LCP') {
            return [];
        }

        return $this->repo->getAvailableLcpPorts($lcpId, $includeCurrentPortId);
    }

    public function getAvailablePortsByNap(int $napId, ?int $includeCurrentPortId = null): array
    {
        $box = $this->repo->findBoxById($napId);
        if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'NAP') {
            return [];
        }

        return $this->repo->getAvailablePortsByBoxId($napId, $includeCurrentPortId);
    }

    public function getLcpPortGrid(): array
    {
        return $this->repo->getLcpPortGrid();
    }

    public function getNapPortGrid(): array
    {
        return $this->repo->getNapPortGrid();
    }

    public function getLcpCandidates(): array
    {
        return $this->repo->getLcpCandidates();
    }

    public function getNapCandidates(?int $excludeBoxId = null): array
    {
        return $this->repo->getNapCandidates($excludeBoxId);
    }

    public function getTopology(): array
    {
        return $this->repo->buildTopologyForest();
    }

    public function createLcp(array $data): array
    {
        try {
            $payload = $this->buildLcpCreatePayload($data);

            $this->repo->beginTransaction();

            $boxId = $this->repo->createBoxWithSplitterAndPorts(
                $payload,
                (int)$payload['splitter_ports']
            );

            $this->repo->syncPlannerProjectionFromBoxId($boxId);

            $this->repo->commit();

            $this->auditLog(
                'CREATE_LCP',
                sprintf(
                    'Created LCP %s Code=%s Ports=%d Location=%s',
                    $payload['box_name'],
                    $payload['box_code'],
                    (int)$payload['splitter_ports'],
                    $payload['location'] ?? ''
                )
            );

            return $this->ok('LCP created successfully.', [
                'id' => $boxId,
            ]);
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function updateLcp(int $id, array $data): array
    {
        try {
            $current = $this->repo->getBoxByIdWithStats($id);
            if (!$current || strtoupper((string)($current['box_type'] ?? '')) !== 'LCP') {
                return $this->fail('LCP not found.');
            }

            $payload = $this->buildLcpUpdatePayload($id, $data, $current);

            $currentPorts = (int)($current['splitter_ratio'] ?? 0);
            $newPorts = (int)($payload['splitter_ports'] ?? 0);

            if ($newPorts < $currentPorts) {
                $this->repo->validateSplitterPortReduction($id, $newPorts);
            }

            $this->repo->beginTransaction();

            $this->repo->updateBoxWithSplitter(
                $id,
                $payload,
                $newPorts
            );

            $this->repo->syncPlannerProjectionFromBoxId($id);

            $this->repo->commit();

            $this->auditLog(
                'UPDATE_LCP',
                sprintf(
                    'Updated LCP %s Code=%s Ports=%d Status=%s',
                    $payload['box_name'],
                    $payload['box_code'],
                    $newPorts,
                    $payload['status']
                )
            );

            return $this->ok('LCP updated successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function deleteLcp(int $id): array
    {
        try {
            if ($id <= 0) {
                return $this->fail('Invalid LCP ID.');
            }

            $box = $this->repo->findBoxById($id);
            if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'LCP') {
                return $this->fail('LCP not found.');
            }

            $status = strtoupper((string)($box['status'] ?? ''));
            if ($status === 'MAINTENANCE') {
                return $this->fail('Cannot delete LCP while it is in maintenance mode.');
            }

            $this->repo->beginTransaction();

            $this->repo->deletePlannerNodeByBoxId($id);
            $this->repo->deleteBox($id);

            $this->repo->commit();

            $this->auditLog(
                'DELETE_LCP',
                sprintf(
                    'Deleted LCP %s Code=%s',
                    $box['box_name'] ?? ('#' . $id),
                    $box['box_code'] ?? ''
                )
            );

            return $this->ok('LCP deleted successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function createNap(array $data): array
    {
        try {
            $payload = $this->buildNapCreatePayload($data);

            $this->repo->beginTransaction();

            $boxId = $this->repo->createBoxWithSplitterAndPorts(
                $payload['box'],
                (int)$payload['splitter_ports']
            );

            $this->repo->replaceUplink($boxId, array_merge(
                $payload['uplink'],
                ['box_id' => $boxId]
            ));

            $sourcePortId = (int)($payload['uplink']['source_port_id'] ?? 0);
            $sourcePortKind = strtoupper((string)($payload['uplink']['source_port_kind'] ?? 'SPLITTER'));

            if ($sourcePortId > 0) {
                if ($sourcePortKind === 'LCP_UPLINK') {
                    $this->repo->markLcpPortUsed($sourcePortId, $boxId);
                } else {
                    $this->repo->attachNapToSourcePort($sourcePortId, $boxId);
                }
            }

            $this->repo->syncPlannerProjectionFromBoxId($boxId);

            $sourceBoxId = (int)($payload['uplink']['source_box_id'] ?? 0);
            if ($sourceBoxId > 0) {
                $this->repo->syncPlannerProjectionFromBoxId($sourceBoxId);
            }

            $this->repo->commit();

            $this->auditLog(
                'CREATE_NAP',
                sprintf(
                    'Created NAP %s Code=%s Ports=%d FeedMode=%s SourceBox=%d SourcePort=%d',
                    $payload['box']['box_name'],
                    $payload['box']['box_code'],
                    (int)$payload['splitter_ports'],
                    $payload['uplink']['feed_mode'],
                    (int)$payload['uplink']['source_box_id'],
                    (int)$payload['uplink']['source_port_id']
                )
            );

            return $this->ok('NAP created successfully.', [
                'id' => $boxId,
            ]);
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function updateNap(array $data): array
    {
        try {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                return $this->fail('Invalid NAP ID.');
            }

            $current = $this->repo->getBoxByIdWithStats($id);
            if (!$current || strtoupper((string)($current['box_type'] ?? '')) !== 'NAP') {
                return $this->fail('NAP not found.');
            }

            $payload = $this->buildNapUpdatePayload($id, $data, $current);

            $currentPorts = (int)($current['splitter_ratio'] ?? 0);
            $newPorts = (int)($payload['splitter_ports'] ?? 0);

            if ($newPorts < $currentPorts) {
                $this->repo->validateSplitterPortReduction($id, $newPorts);
            }

            $this->repo->beginTransaction();

            $oldSourcePortId = (int)($current['source_port_id'] ?? 0);
            $oldFeedMode = strtoupper((string)($current['feed_mode'] ?? ''));
            $oldSourcePortKind = $oldFeedMode === 'DIRECT_FROM_LCP' ? 'LCP_UPLINK' : 'SPLITTER';

            if ($oldSourcePortId > 0) {
                if ($oldSourcePortKind === 'LCP_UPLINK') {
                    $this->repo->markLcpPortAvailable($oldSourcePortId);
                } else {
                    $this->repo->detachNapFromSourcePort($id);
                }
            }

            $this->repo->updateBoxWithSplitter(
                $id,
                $payload['box'],
                $newPorts
            );

            $this->repo->replaceUplink($id, array_merge(
                $payload['uplink'],
                ['box_id' => $id]
            ));

            $newSourcePortId = (int)($payload['uplink']['source_port_id'] ?? 0);
            $newSourcePortKind = strtoupper((string)($payload['uplink']['source_port_kind'] ?? 'SPLITTER'));

            if ($newSourcePortId > 0) {
                if ($newSourcePortKind === 'LCP_UPLINK') {
                    $this->repo->markLcpPortUsed($newSourcePortId, $id);
                } else {
                    $this->repo->attachNapToSourcePort($newSourcePortId, $id);
                }
            }

            $this->repo->syncPlannerProjectionFromBoxId($id);

            $sourceBoxId = (int)($payload['uplink']['source_box_id'] ?? 0);
            if ($sourceBoxId > 0) {
                $this->repo->syncPlannerProjectionFromBoxId($sourceBoxId);
            }

            $this->repo->commit();

            $this->auditLog(
                'UPDATE_NAP',
                sprintf(
                    'Updated NAP %s Code=%s Ports=%d FeedMode=%s SourceBox=%d SourcePort=%d',
                    $payload['box']['box_name'],
                    $payload['box']['box_code'],
                    $newPorts,
                    $payload['uplink']['feed_mode'],
                    (int)$payload['uplink']['source_box_id'],
                    (int)$payload['uplink']['source_port_id']
                )
            );

            return $this->ok('NAP updated successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function deleteNap(int $id): array
    {
        try {
            if ($id <= 0) {
                return $this->fail('Invalid NAP ID.');
            }

            $box = $this->repo->findBoxById($id);
            if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'NAP') {
                return $this->fail('NAP not found.');
            }

            $status = strtoupper((string)($box['status'] ?? ''));
            if ($status === 'MAINTENANCE') {
                return $this->fail('Cannot delete NAP while it is in maintenance mode.');
            }

            $this->repo->beginTransaction();

            $this->repo->deletePlannerNodeByBoxId($id);
            $this->repo->deleteBox($id);

            $this->repo->commit();

            $this->auditLog(
                'DELETE_NAP',
                sprintf(
                    'Deleted NAP %s Code=%s',
                    $box['box_name'] ?? ('#' . $id),
                    $box['box_code'] ?? ''
                )
            );

            return $this->ok('NAP deleted successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function setLcpMaintenance(int $id, array $data): array
    {
        try {
            if ($id <= 0) {
                return $this->fail('Invalid LCP ID.');
            }

            $box = $this->repo->findBoxById($id);
            if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'LCP') {
                return $this->fail('LCP not found.');
            }

            $enabled = $this->resolveMaintenanceEnabled($data);
            $reason = trim((string)($data['maintenance_reason'] ?? $data['reason'] ?? ''));
            $resolution = trim((string)($data['maintenance_resolution'] ?? $data['resolution'] ?? ''));

            if ($enabled && $reason === '') {
                return $this->fail('Maintenance reason is required.');
            }

            if (!$enabled) {
                $this->assertNotInheritedMaintenance($box, 'LCP');

                if ($resolution === '') {
                    return $this->fail('Maintenance resolution is required.');
                }
            }

            $this->repo->beginTransaction();

            $this->repo->setBoxMaintenance($id, $enabled, $reason, $resolution, 'SELF');
            $this->repo->syncPlannerNodeFromBoxId($id);

            $this->cascadeBoxMaintenanceDownstream($id, $enabled, $reason, $resolution);

            $this->repo->commit();

            $this->auditLog(
                $enabled ? 'ENABLE_LCP_MAINTENANCE' : 'DISABLE_LCP_MAINTENANCE',
                sprintf(
                    '%s LCP maintenance for %s. Reason=%s Resolution=%s',
                    $enabled ? 'Enabled' : 'Disabled',
                    $box['box_name'] ?? ('#' . $id),
                    $reason,
                    $resolution
                )
            );

            return $this->ok('LCP maintenance updated successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function setNapMaintenance(int $id, array $data): array
    {
        try {
            if ($id <= 0) {
                return $this->fail('Invalid NAP ID.');
            }

            $box = $this->repo->findBoxById($id);
            if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'NAP') {
                return $this->fail('NAP not found.');
            }

            $enabled = $this->resolveMaintenanceEnabled($data);
            $reason = trim((string)($data['maintenance_reason'] ?? $data['reason'] ?? ''));
            $resolution = trim((string)($data['maintenance_resolution'] ?? $data['resolution'] ?? ''));

            if ($enabled && $reason === '') {
                return $this->fail('Maintenance reason is required.');
            }

            if (!$enabled) {
                $this->assertNotInheritedMaintenance($box, 'NAP');

                if ($resolution === '') {
                    return $this->fail('Maintenance resolution is required.');
                }
            }

            $this->repo->beginTransaction();

            $this->repo->setBoxMaintenance($id, $enabled, $reason, $resolution, 'SELF');
            $this->repo->syncPlannerNodeFromBoxId($id);

            $this->cascadeBoxMaintenanceDownstream($id, $enabled, $reason, $resolution);

            $this->repo->commit();

            $this->auditLog(
                $enabled ? 'ENABLE_NAP_MAINTENANCE' : 'DISABLE_NAP_MAINTENANCE',
                sprintf(
                    '%s NAP maintenance for %s. Reason=%s Resolution=%s',
                    $enabled ? 'Enabled' : 'Disabled',
                    $box['box_name'] ?? ('#' . $id),
                    $reason,
                    $resolution
                )
            );

            return $this->ok('NAP maintenance updated successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function plannerConnect(array $input): array
    {
        try {
            $sourceType = strtoupper(trim((string)($input['source_type'] ?? '')));
            $targetType = strtoupper(trim((string)($input['target_type'] ?? '')));
            $sourceId = (int)($input['source_id'] ?? 0);
            $targetId = (int)($input['target_id'] ?? 0);
            $parentPortId = (int)($input['parent_port_id'] ?? 0);
            $feedMode = strtoupper(trim((string)($input['feed_mode'] ?? '')));
            $cableId = $this->normalizeInt($input['cable_id'] ?? null);
            $fiberCoreId = $this->normalizeInt($input['fiber_core_id'] ?? null);

            if (!in_array($sourceType, ['LCP', 'NAP'], true)) {
                return $this->fail('Source type must be LCP or NAP.');
            }

            if ($targetType !== 'NAP') {
                return $this->fail('Target type must be NAP.');
            }

            if ($sourceId <= 0 || $targetId <= 0) {
                return $this->fail('Source and target IDs are required.');
            }

            if ($sourceId === $targetId && $sourceType === $targetType) {
                return $this->fail('Source and target cannot be the same box.');
            }

            if ($parentPortId <= 0) {
                return $this->fail('Distribution source port is required.');
            }

            $sourceBox = $this->repo->findBoxById($sourceId);
            if (!$sourceBox || strtoupper((string)($sourceBox['box_type'] ?? '')) !== $sourceType) {
                return $this->fail('Source box not found.');
            }

            $targetBox = $this->repo->findBoxById($targetId);
            if (!$targetBox || strtoupper((string)($targetBox['box_type'] ?? '')) !== 'NAP') {
                return $this->fail('Target NAP not found.');
            }

            if (strtoupper((string)($sourceBox['status'] ?? 'ACTIVE')) === 'MAINTENANCE') {
                return $this->fail('Cannot use this source box while it is in maintenance mode.');
            }

            if (strtoupper((string)($targetBox['status'] ?? 'ACTIVE')) === 'MAINTENANCE') {
                return $this->fail('Cannot modify uplink while the target NAP is in maintenance mode.');
            }

            $this->assertPortOwnerNotInMaintenance($parentPortId);

            if ($sourceType === 'LCP' && $feedMode !== 'DIRECT_FROM_LCP') {
                return $this->fail('LCP sources must use Direct from LCP mode.');
            }

            if ($sourceType === 'NAP' && $feedMode !== 'CASCADE_FROM_NAP') {
                return $this->fail('NAP sources must use Cascade from NAP mode.');
            }

            if ($this->wouldCreateTopologyLoop($sourceId, $targetId)) {
                return $this->fail('Loop detected. This connection would create an invalid topology.');
            }

            if ($sourceType === 'LCP') {
                $port = $this->repo->findLcpPortById($parentPortId);

                if (!$port || (int)($port['lcp_id'] ?? 0) !== $sourceId) {
                    return $this->fail('Selected LCP uplink port is invalid.');
                }

                $currentTargetUsesSamePort =
                    strtoupper((string)($targetBox['feed_mode'] ?? '')) === 'DIRECT_FROM_LCP' &&
                    (int)($targetBox['source_port_id'] ?? 0) === $parentPortId;

                if (
                    !$currentTargetUsesSamePort &&
                    strtoupper((string)($port['status'] ?? 'AVAILABLE')) !== 'AVAILABLE'
                ) {
                    return $this->fail('Selected LCP uplink port is already in use.');
                }
            } else {
                $availablePorts = $this->repo->getAvailablePortsByBoxId($sourceId, $parentPortId);
                $portIds = array_map(static fn(array $row) => (int)($row['id'] ?? 0), $availablePorts);

                if (!in_array($parentPortId, $portIds, true)) {
                    return $this->fail('Selected distribution source port is already in use.');
                }
            }

            $normalizedFeedMode = $this->normalizeFeedMode(
                $feedMode,
                $sourceType === 'NAP' ? 'NAP' : 'LCP'
            );

            $this->repo->beginTransaction();

            $oldSourcePortId = (int)($targetBox['source_port_id'] ?? 0);
            $oldFeedMode = strtoupper((string)($targetBox['feed_mode'] ?? ''));
            $oldSourcePortKind = $oldFeedMode === 'DIRECT_FROM_LCP' ? 'LCP_UPLINK' : 'SPLITTER';

            if ($oldSourcePortId > 0) {
                if ($oldSourcePortKind === 'LCP_UPLINK') {
                    $this->repo->markLcpPortAvailable($oldSourcePortId);
                } else {
                    $this->repo->detachNapFromSourcePort($targetId);
                }
            }

            $this->repo->replaceUplink($targetId, [
                'box_id' => $targetId,
                'feed_mode' => $normalizedFeedMode,
                'source_box_id' => $sourceId,
                'source_port_id' => $parentPortId,
                'source_port_kind' => $sourceType === 'LCP' ? 'LCP_UPLINK' : 'SPLITTER',
                'source_cable_id' => $cableId,
                'source_fiber_core' => $fiberCoreId,
                'splice_point' => $input['splice_point'] ?? null,
                'splice_note' => $input['splice_note'] ?? null,
            ]);

            if ($sourceType === 'LCP') {
                $this->repo->markLcpPortUsed($parentPortId, $targetId);
            } else {
                $this->repo->attachNapToSourcePort($parentPortId, $targetId);
            }

            $this->repo->syncPlannerProjectionFromBoxId($targetId);
            $this->repo->syncPlannerProjectionFromBoxId($sourceId);

            $this->repo->commit();

            $this->auditLog(
                'PLANNER_CONNECT',
                sprintf(
                    'Connected %s #%d to %s #%d using port %d FeedMode=%s',
                    $sourceType,
                    $sourceId,
                    $targetType,
                    $targetId,
                    $parentPortId,
                    $normalizedFeedMode
                )
            );

            return $this->ok('Distribution uplink saved successfully.', [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'parent_port_id' => $parentPortId,
                'feed_mode' => $normalizedFeedMode,
            ]);
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function plannerDeleteLink(array $input): array
    {
        try {
            $sourceType = strtoupper(trim((string)($input['source_type'] ?? '')));
            $targetType = strtoupper(trim((string)($input['target_type'] ?? '')));
            $sourceId = (int)($input['source_id'] ?? 0);
            $targetId = (int)($input['target_id'] ?? 0);

            if (!in_array($sourceType, ['LCP', 'NAP'], true)) {
                return $this->fail('Source type must be LCP or NAP.');
            }

            if ($targetType !== 'NAP') {
                return $this->fail('Target type must be NAP.');
            }

            if ($sourceId <= 0 || $targetId <= 0) {
                return $this->fail('Source and target IDs are required.');
            }

            $sourceBox = $this->repo->findBoxById($sourceId);
            if (!$sourceBox || strtoupper((string)($sourceBox['box_type'] ?? '')) !== $sourceType) {
                return $this->fail('Source box not found.');
            }

            $targetBox = $this->repo->findBoxById($targetId);
            if (!$targetBox || strtoupper((string)($targetBox['box_type'] ?? '')) !== 'NAP') {
                return $this->fail('Target NAP not found.');
            }

            $this->repo->beginTransaction();

            $oldSourcePortId = (int)($targetBox['source_port_id'] ?? 0);
            $oldFeedMode = strtoupper((string)($targetBox['feed_mode'] ?? ''));
            $oldSourcePortKind = $oldFeedMode === 'DIRECT_FROM_LCP' ? 'LCP_UPLINK' : 'SPLITTER';

            if ($oldSourcePortId > 0) {
                if ($oldSourcePortKind === 'LCP_UPLINK') {
                    $this->repo->markLcpPortAvailable($oldSourcePortId);
                } else {
                    $this->repo->detachNapFromSourcePort($targetId);
                }
            }

            if (method_exists($this->repo, 'deleteUplinkByBoxId')) {
                $this->repo->deleteUplinkByBoxId($targetId);
            }

            $this->repo->syncPlannerProjectionFromBoxId($targetId);
            $this->repo->syncPlannerProjectionFromBoxId($sourceId);

            $this->repo->commit();

            $this->auditLog(
                'PLANNER_DELETE_LINK',
                sprintf(
                    'Deleted planner link %s #%d to %s #%d',
                    $sourceType,
                    $sourceId,
                    $targetType,
                    $targetId
                )
            );

            return $this->ok('Planner link deleted successfully.', [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ]);
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function getTopologyObjects(): array
    {
        return [
            'objects' => $this->repo->getNetworkObjects(),
            'links' => $this->repo->getNetworkLinks(),
        ];
    }

    public function createTopologyObject(array $input): array
    {
        $objectType = strtoupper(trim((string)($input['object_type'] ?? '')));
        $objectName = trim((string)($input['object_name'] ?? ''));

        if (!in_array($objectType, ['OLT', 'ODF', 'FDT', 'NAP', 'ONT'], true)) {
            return $this->fail('Invalid object type.');
        }

        if ($objectName === '') {
            return $this->fail('Object name is required.');
        }

        try {
            $this->repo->beginTransaction();

            $id = $this->repo->createNetworkObject([
                'object_type' => $objectType,
                'object_name' => $objectName,
                'object_code' => trim((string)($input['object_code'] ?? '')) ?: null,
                'olt_device_id' => $this->normalizeInt($input['olt_device_id'] ?? null),
                'olt_port_id' => $this->normalizeInt($input['olt_port_id'] ?? null),
                'location' => trim((string)($input['location'] ?? '')) ?: null,
                'latitude' => $this->normalizeDecimal($input['latitude'] ?? null),
                'longitude' => $this->normalizeDecimal($input['longitude'] ?? null),
                'status' => strtoupper(trim((string)($input['status'] ?? 'ACTIVE'))),
                'notes' => trim((string)($input['notes'] ?? '')) ?: null,
            ]);

            $this->repo->commit();

            $this->auditLog(
                'CREATE_TOPOLOGY_OBJECT',
                sprintf(
                    'Created topology object %s #%d Name=%s',
                    $objectType,
                    $id,
                    $objectName
                )
            );

            return $this->ok('Network object created successfully.', [
                'id' => $id
            ]);
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    private function wouldCreateObjectLoop(int $sourceObjectId, int $targetObjectId): bool
    {
        if ($sourceObjectId === $targetObjectId) {
            return true;
        }

        $visited = [];
        $current = $sourceObjectId;

        while ($current > 0) {
            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;

            if ($current === $targetObjectId) {
                return true;
            }

            $parent = $this->repo->getActiveParentObjectId($current);
            if (!$parent || $parent <= 0) {
                break;
            }

            $current = $parent;
        }

        return false;
    }

    public function createTopologyLink(array $input): array
    {
        $linkType = strtoupper(trim((string)($input['link_type'] ?? '')));
        $sourceObjectId = (int)($input['source_node_id'] ?? 0);
        $targetObjectId = (int)($input['target_node_id'] ?? 0);

        if (!in_array($linkType, ['FEEDER', 'DISTRIBUTION', 'DROP'], true)) {
            return $this->fail('Invalid link type.');
        }

        if ($sourceObjectId <= 0 || $targetObjectId <= 0) {
            return $this->fail('Source and target objects are required.');
        }

        $source = $this->repo->getNetworkObjectById($sourceObjectId);
        $target = $this->repo->getNetworkObjectById($targetObjectId);

        if (!$source || !$target) {
            return $this->fail('Source or target object not found.');
        }

        $sourceType = strtoupper((string)($source['object_type'] ?? ''));
        $targetType = strtoupper((string)($target['object_type'] ?? ''));

        $valid =
            ($linkType === 'FEEDER' && $sourceType === 'ODF' && $targetType === 'LCP') ||
            ($linkType === 'DISTRIBUTION' && $sourceType === 'LCP' && $targetType === 'NAP') ||
            ($linkType === 'DISTRIBUTION' && $sourceType === 'NAP' && $targetType === 'NAP') ||
            ($linkType === 'DROP' && $sourceType === 'NAP' && $targetType === 'ONT');

        if (!$valid) {
            return $this->fail("Invalid {$linkType} topology for {$sourceType} -> {$targetType}.");
        }

        if ($this->wouldCreateObjectLoop($sourceObjectId, $targetObjectId)) {
            return $this->fail('Loop detected. Invalid topology.');
        }

        try {
            $this->repo->beginTransaction();

            $this->repo->replaceNetworkLinkByTarget($targetObjectId, [
                'link_type' => $linkType,
                'source_node_id' => $sourceObjectId,
                'target_node_id' => $targetObjectId,
                'cable_id' => $this->normalizeInt($input['cable_id'] ?? null),
                'fiber_core_id' => $this->normalizeInt($input['fiber_core_id'] ?? null),
                'source_port_id' => $this->normalizeInt($input['source_port_id'] ?? null),
                'target_port_id' => $this->normalizeInt($input['target_port_id'] ?? null),
                'notes' => trim((string)($input['notes'] ?? '')) ?: null,
            ]);

            $this->repo->commit();

            $this->auditLog(
                'CREATE_TOPOLOGY_LINK',
                sprintf(
                    'Created topology link %s %s #%d to %s #%d',
                    $linkType,
                    $sourceType,
                    $sourceObjectId,
                    $targetType,
                    $targetObjectId
                )
            );

            return $this->ok('Network link created successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function deleteTopologyLink(int $id): array
    {
        if ($id <= 0) {
            return $this->fail('Invalid link ID.');
        }

        $row = $this->repo->getNetworkLinkById($id);
        if (!$row) {
            return $this->fail('Network link not found.');
        }

        try {
            $this->repo->beginTransaction();
            $this->repo->deleteNetworkLinkById($id);
            $this->repo->commit();

            $this->auditLog(
                'DELETE_TOPOLOGY_LINK',
                sprintf('Deleted topology link #%d', $id)
            );

            return $this->ok('Network link deleted successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function getPlannerTopologyObjects(): array
    {
        $nodes = $this->repo->getPlannerNodes();
        $links = $this->repo->getPlannerLinks();

        foreach ($links as &$link) {
            $link['source_port_label'] = $this->repo->getPortLabelById(
                isset($link['source_port_id']) ? (int)$link['source_port_id'] : null
            ) ?? '';

            $link['target_port_label'] = $this->repo->getPortLabelById(
                isset($link['target_port_id']) ? (int)$link['target_port_id'] : null
            ) ?? '';
        }
        unset($link);

        return [
            'nodes' => $nodes,
            'links' => $links,
        ];
    }

    public function plannerConnectObject(array $input): array
    {
        try {
            $sourceNodeId = (int)($input['source_node_id'] ?? 0);
            $targetNodeId = (int)($input['target_node_id'] ?? 0);
            $linkType = strtoupper(trim((string)($input['link_type'] ?? '')));
            $sourcePortId = $this->normalizeInt($input['source_port_id'] ?? null);
            $targetPortId = $this->normalizeInt($input['target_port_id'] ?? null);
            $cableId = $this->normalizeInt($input['cable_id'] ?? null);
            $fiberCoreId = $this->normalizeInt($input['fiber_core_id'] ?? null);

            if ($sourceNodeId <= 0 || $targetNodeId <= 0) {
                return $this->fail('Source node and target node are required.');
            }

            if ($sourceNodeId === $targetNodeId) {
                return $this->fail('Source and target cannot be the same node.');
            }

            if (!in_array($linkType, ['FEEDER', 'DISTRIBUTION', 'DROP'], true)) {
                return $this->fail('Invalid link type.');
            }

            $nodes = $this->repo->getPlannerNodes();

            $sourceNode = null;
            $targetNode = null;

            foreach ($nodes as $node) {
                $nodeId = (int)($node['id'] ?? 0);

                if ($nodeId === $sourceNodeId) {
                    $sourceNode = $node;
                }

                if ($nodeId === $targetNodeId) {
                    $targetNode = $node;
                }
            }

            if (!$sourceNode || !$targetNode) {
                return $this->fail('Source or target planner node not found.');
            }

            $sourceType = strtoupper(trim((string)($sourceNode['node_type'] ?? '')));
            $targetType = strtoupper(trim((string)($targetNode['node_type'] ?? '')));

            $valid =
                ($linkType === 'FEEDER' && $sourceType === 'OLT' && $targetType === 'ODF') ||
                ($linkType === 'DISTRIBUTION' && $sourceType === 'ODF' && $targetType === 'LCP') ||
                ($linkType === 'DISTRIBUTION' && $sourceType === 'LCP' && $targetType === 'NAP') ||
                ($linkType === 'DISTRIBUTION' && $sourceType === 'NAP' && $targetType === 'NAP');

            if (!$valid) {
                return $this->fail("Invalid planner topology for {$linkType}: {$sourceType} -> {$targetType}.");
            }

            if ($sourceType === 'NAP' && $targetType !== 'NAP') {
                return $this->fail('NAP can only connect downstream to another NAP in planner mode.');
            }

            if ($targetType === 'ODF') {
                return $this->fail('ODF cannot be a downstream target except from OLT via FEEDER.');
            }

            if ($targetType === 'LCP' && !($sourceType === 'ODF' && $linkType === 'DISTRIBUTION')) {
                return $this->fail('LCP can only be connected from ODF using DISTRIBUTION.');
            }

            if ($targetType === 'NAP' && !(
                    ($sourceType === 'LCP' && $linkType === 'DISTRIBUTION') ||
                    ($sourceType === 'NAP' && $linkType === 'DISTRIBUTION')
                )) {
                return $this->fail('NAP can only be connected from LCP or NAP using DISTRIBUTION.');
            }

            if ($this->wouldCreatePlannerLoop($sourceNodeId, $targetNodeId)) {
                return $this->fail('Loop detected. This link would create an invalid topology.');
            }

            $this->repo->beginTransaction();

            $this->repo->replacePlannerLink($targetNodeId, [
                'link_type' => $linkType,
                'source_node_id' => $sourceNodeId,
                'target_node_id' => $targetNodeId,
                'source_port_id' => $sourcePortId,
                'target_port_id' => $targetPortId,
                'cable_id' => $cableId,
                'fiber_core_id' => $fiberCoreId,
                'splice_point' => $input['splice_point'] ?? null,
                'splice_note' => $input['splice_note'] ?? null,
            ]);

            $this->repo->commit();

            $this->auditLog(
                'PLANNER_CONNECT_OBJECT',
                sprintf(
                    'Connected planner node %s #%d to %s #%d LinkType=%s',
                    $sourceType,
                    $sourceNodeId,
                    $targetType,
                    $targetNodeId,
                    $linkType
                )
            );

            return $this->ok('Planner object link saved successfully.', [
                'source_node_id' => $sourceNodeId,
                'target_node_id' => $targetNodeId,
                'link_type' => $linkType,
                'source_type' => $sourceType,
                'target_type' => $targetType,
            ]);
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function plannerDeleteObjectLink(array $input): array
    {
        try {
            $targetNodeId = (int)($input['target_node_id'] ?? 0);

            if ($targetNodeId <= 0) {
                return $this->fail('Target node is required.');
            }

            $this->repo->beginTransaction();
            $this->repo->deletePlannerLinkByTargetNodeId($targetNodeId);
            $this->repo->commit();

            $this->auditLog(
                'PLANNER_DELETE_OBJECT_LINK',
                sprintf('Deleted planner object link for target node #%d', $targetNodeId)
            );

            return $this->ok('Planner object link deleted successfully.', [
                'target_node_id' => $targetNodeId,
            ]);
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    private function wouldCreatePlannerLoop(int $sourceNodeId, int $targetNodeId): bool
    {
        if ($sourceNodeId === $targetNodeId) {
            return true;
        }

        $visited = [];
        $current = $sourceNodeId;

        while ($current > 0) {
            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;

            if ($current === $targetNodeId) {
                return true;
            }

            $parent = $this->repo->getActiveParentNodeId($current);
            if (!$parent || $parent <= 0) {
                break;
            }

            $current = $parent;
        }

        return false;
    }

    private function wouldCreateTopologyLoop(int $sourceBoxId, int $targetBoxId): bool
    {
        if ($sourceBoxId === $targetBoxId) {
            return true;
        }

        $visited = [];
        $current = $sourceBoxId;

        while ($current > 0) {
            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;

            if ($current === $targetBoxId) {
                return true;
            }

            $parent = $this->repo->getActiveUplinkSourceBoxId($current);
            if (!$parent || $parent <= 0) {
                break;
            }

            $current = $parent;
        }

        return false;
    }

    public function rebuildPlannerProjection(): array
    {
        try {
            $this->repo->beginTransaction();
            $this->repo->rebuildPlannerProjection();
            $this->repo->commit();

            $this->auditLog(
                'REBUILD_PLANNER_PROJECTION',
                'Rebuilt NAP planner projection.'
            );

            return $this->ok('Planner projection rebuilt successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    private function buildOdfCreatePayload(array $data): array
    {
        $odfName = trim((string)($data['odf_name'] ?? $data['node_name'] ?? ''));
        if ($odfName === '') {
            throw new RuntimeException('ODF name is required.');
        }

        $portCount = (int)($data['port_count'] ?? 24);
        if ($portCount <= 0) {
            throw new RuntimeException('ODF port count must be greater than 0.');
        }

        $namingMode = strtoupper(trim((string)($data['port_naming_mode'] ?? 'NUMERIC')));
        if (!in_array($namingMode, ['NUMERIC', 'CUSTOM'], true)) {
            $namingMode = 'NUMERIC';
        }

        $oltId = $this->normalizeInt($data['olt_id'] ?? null);
        $oltPortId = $this->normalizeInt($data['olt_port_id'] ?? null);
        $inputPortNumber = $this->normalizeInt($data['input_port_number'] ?? null);

        if ($oltPortId && $this->repo->isOltPortAlreadyAssignedToOdf($oltPortId)) {
            throw new RuntimeException('Selected OLT port is already assigned to another ODF.');
        }

        if ($inputPortNumber !== null && ($inputPortNumber < 1 || $inputPortNumber > $portCount)) {
            throw new RuntimeException('Selected ODF input port is invalid.');
        }

        return [
            'odf_name' => $odfName,
            'node_code' => trim((string)($data['node_code'] ?? '')),
            'location' => $data['location'] ?? null,
            'latitude' => $this->normalizeDecimal($data['latitude'] ?? null),
            'longitude' => $this->normalizeDecimal($data['longitude'] ?? null),
            'olt_id' => $oltId,
            'olt_port_id' => $oltPortId,
            'input_port_number' => $inputPortNumber,
            'port_count' => $portCount,
            'port_naming_mode' => $namingMode,
            'status' => $this->normalizeStatus($data['status'] ?? 'ACTIVE'),
            'remarks' => $data['remarks'] ?? $data['description'] ?? null,
        ];
    }

    private function buildOdfUpdatePayload(int $id, array $data, array $current): array
    {
        $odfName = trim((string)($data['odf_name'] ?? $data['node_name'] ?? $current['odf_name'] ?? ''));
        if ($odfName === '') {
            throw new RuntimeException('ODF name is required.');
        }

        $portCount = (int)($data['port_count'] ?? $current['port_count'] ?? 24);
        if ($portCount <= 0) {
            throw new RuntimeException('ODF port count must be greater than 0.');
        }

        $namingMode = strtoupper(trim((string)($data['port_naming_mode'] ?? $current['port_naming_mode'] ?? 'NUMERIC')));
        if (!in_array($namingMode, ['NUMERIC', 'CUSTOM'], true)) {
            $namingMode = 'NUMERIC';
        }

        $oltId = $this->normalizeInt($data['olt_id'] ?? ($current['olt_id'] ?? null));
        $oltPortId = $this->normalizeInt($data['olt_port_id'] ?? ($current['olt_port_id'] ?? null));
        $inputPortNumber = $this->normalizeInt($data['input_port_number'] ?? ($current['input_port_number'] ?? null));

        if ($oltPortId && $this->repo->isOltPortAlreadyAssignedToOdf($oltPortId, $id)) {
            throw new RuntimeException('Selected OLT port is already assigned to another ODF.');
        }

        if ($inputPortNumber !== null && ($inputPortNumber < 1 || $inputPortNumber > $portCount)) {
            throw new RuntimeException('Selected ODF input port is invalid.');
        }

        return [
            'odf_name' => $odfName,
            'node_code' => trim((string)($data['node_code'] ?? ($current['node_code'] ?? ''))),
            'location' => $data['location'] ?? ($current['location'] ?? null),
            'latitude' => $this->normalizeDecimal($data['latitude'] ?? ($current['latitude'] ?? null)),
            'longitude' => $this->normalizeDecimal($data['longitude'] ?? ($current['longitude'] ?? null)),
            'olt_id' => $oltId,
            'olt_port_id' => $oltPortId,
            'input_port_number' => $inputPortNumber,
            'port_count' => $portCount,
            'port_naming_mode' => $namingMode,
            'status' => $this->normalizeStatus($data['status'] ?? ($current['status'] ?? 'ACTIVE')),
            'remarks' => $data['remarks'] ?? $data['description'] ?? ($current['remarks'] ?? null),
        ];
    }

    public function getOdfAll(): array
    {
        return $this->repo->getAllOdfs();
    }

    public function getOdfById(int $id): ?array
    {
        return $this->repo->findOdfById($id);
    }

    public function getOdfPorts(int $odfId): array
    {
        return $this->repo->getOdfPorts($odfId);
    }

    public function getOdfPortGrid(): array
    {
        return $this->repo->getOdfPortGrid();
    }

    public function getOdfCandidates(): array
    {
        return $this->repo->getOdfCandidates();
    }

    public function createOdf(array $data): array
    {
        try {
            $payload = $this->buildOdfCreatePayload($data);

            $this->repo->beginTransaction();

            $odfId = $this->repo->createOdf($payload);
            $this->repo->generateOdfPorts(
                $odfId,
                (int)$payload['port_count'],
                (string)$payload['port_naming_mode']
            );

            $this->repo->syncPlannerNodeFromOdfId($odfId);

            $this->repo->commit();

            $this->auditLog(
                'CREATE_ODF',
                sprintf(
                    'Created ODF %s Code=%s Ports=%d Location=%s',
                    $payload['odf_name'],
                    $payload['node_code'],
                    (int)$payload['port_count'],
                    $payload['location'] ?? ''
                )
            );

            return $this->ok('ODF created successfully.', [
                'id' => $odfId,
            ]);
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function updateOdf(int $id, array $data): array
    {
        try {
            $current = $this->repo->findOdfById($id);
            if (!$current) {
                return $this->fail('ODF not found.');
            }

            $payload = $this->buildOdfUpdatePayload($id, $data, $current);

            $currentPortCount = (int)($current['port_count'] ?? 0);
            $newPortCount = (int)($payload['port_count'] ?? 0);

            if ($newPortCount < $currentPortCount) {
                $this->repo->validateOdfPortReduction($id, $newPortCount);
            }

            $this->repo->beginTransaction();

            $this->repo->updateOdf($id, $payload);
            $this->repo->rebuildOdfPorts(
                $id,
                $newPortCount,
                (string)$payload['port_naming_mode']
            );
            $this->repo->syncPlannerNodeFromOdfId($id);

            $this->repo->commit();

            $this->auditLog(
                'UPDATE_ODF',
                sprintf(
                    'Updated ODF %s Code=%s Ports=%d Status=%s',
                    $payload['odf_name'],
                    $payload['node_code'],
                    $newPortCount,
                    $payload['status']
                )
            );

            return $this->ok('ODF updated successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function deleteOdf(int $id): array
    {
        try {
            if ($id <= 0) {
                return $this->fail('Invalid ODF ID.');
            }

            $current = $this->repo->findOdfById($id);
            if (!$current) {
                return $this->fail('ODF not found.');
            }

            $status = strtoupper((string)($current['status'] ?? ''));
            if ($status === 'MAINTENANCE') {
                return $this->fail('Cannot delete ODF while it is in maintenance mode.');
            }

            $this->repo->beginTransaction();

            $this->repo->deletePlannerNodeByOdfId($id);
            $this->repo->deleteOdf($id);

            $this->repo->commit();

            $this->auditLog(
                'DELETE_ODF',
                sprintf(
                    'Deleted ODF %s Code=%s',
                    $current['odf_name'] ?? ('#' . $id),
                    $current['node_code'] ?? ''
                )
            );

            return $this->ok('ODF deleted successfully.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    public function setOdfMaintenance(int $id, array $data): array
    {
        try {
            $current = $this->repo->findOdfById($id);
            if (!$current) {
                return $this->fail('ODF not found.');
            }

            $enabled = $this->resolveMaintenanceEnabled($data);
            $reason = trim((string)($data['maintenance_reason'] ?? $data['reason'] ?? ''));
            $resolution = trim((string)($data['maintenance_resolution'] ?? $data['resolution'] ?? ''));

            if ($enabled && $reason === '') {
                return $this->fail('Maintenance reason is required.');
            }

            if (!$enabled && $resolution === '') {
                return $this->fail('Maintenance resolution is required.');
            }

            $this->repo->beginTransaction();

            $this->repo->setOdfMaintenance($id, $enabled, $reason, $resolution);
            $this->repo->syncPlannerNodeFromOdfId($id);

            $lcps = $this->repo->getLcpsByOdfId($id);

            foreach ($lcps as $lcp) {
                $lcpId = (int)($lcp['id'] ?? 0);
                if ($lcpId <= 0) {
                    continue;
                }

                $this->repo->setBoxMaintenance($lcpId, $enabled, $reason, $resolution, 'PARENT');
                $this->repo->syncPlannerNodeFromBoxId($lcpId);

                $this->cascadeBoxMaintenanceDownstream($lcpId, $enabled, $reason, $resolution);
            }

            $this->repo->commit();

            $this->auditLog(
                $enabled ? 'ENABLE_ODF_MAINTENANCE' : 'DISABLE_ODF_MAINTENANCE',
                sprintf(
                    '%s ODF maintenance for %s. Cascaded LCP count=%d Reason=%s Resolution=%s',
                    $enabled ? 'Enabled' : 'Disabled',
                    $current['odf_name'] ?? ('#' . $id),
                    count($lcps),
                    $reason,
                    $resolution
                )
            );

            return $this->ok('ODF maintenance cascade applied.');
        } catch (Throwable $e) {
            $this->repo->rollBack();
            return $this->fail($e->getMessage());
        }
    }

    private function assertNotInheritedMaintenance(array $row, string $entityLabel = 'Box'): void
    {
        $status = strtoupper((string)($row['status'] ?? ''));
        $source = strtoupper((string)($row['maintenance_source'] ?? $row['status_source'] ?? ''));

        if ($status === 'MAINTENANCE' && in_array($source, ['PARENT', 'CASCADE', 'INHERITED'], true)) {
            throw new RuntimeException($entityLabel . ' is under inherited maintenance from its parent and cannot be changed directly.');
        }
    }

    public function savePlannerNodePosition(array $input): array
    {
        $nodeId = (int)($input['node_id'] ?? 0);
        $x = isset($input['x']) ? (float)$input['x'] : null;
        $y = isset($input['y']) ? (float)$input['y'] : null;

        $errors = [];

        if ($nodeId <= 0) {
            $errors['node_id'] = 'Valid planner node ID is required.';
        }

        if ($x === null || !is_numeric((string)($input['x'] ?? ''))) {
            $errors['x'] = 'Valid X position is required.';
        }

        if ($y === null || !is_numeric((string)($input['y'] ?? ''))) {
            $errors['y'] = 'Valid Y position is required.';
        }

        if (!empty($errors)) {
            return $this->fail('Invalid planner node position payload.', $errors);
        }

        $ok = $this->repo->savePlannerNodePosition($nodeId, (float)$x, (float)$y);

        if (!$ok) {
            return $this->fail('Failed to save planner node position.');
        }

        $this->auditLog(
            'SAVE_PLANNER_NODE_POSITION',
            sprintf(
                'Saved planner node position. NodeID=%d X=%s Y=%s',
                $nodeId,
                (string)$x,
                (string)$y
            )
        );

        return $this->ok('Planner node position saved.', [
            'node_id' => $nodeId,
            'x' => (float)$x,
            'y' => (float)$y,
        ]);
    }

    public function resetPlannerLayout(): array
    {
        $ok = $this->repo->resetAllPlannerNodePositions();

        if (!$ok) {
            return $this->fail('Failed to reset planner layout.');
        }

        $this->auditLog(
            'RESET_PLANNER_LAYOUT',
            'Reset all planner node positions.'
        );

        return $this->ok('Planner layout reset successfully.', []);
    }

    private function auditLog(string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log(
                'NAP_MANAGEMENT',
                $action,
                $description
            );
        } catch (Throwable $e) {
        }
    }
}