<?php

namespace App\Modules\Radius\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Radius\DTOs\RadiusSettingsDTO;
use App\Modules\Radius\Entities\RadiusSettings;
use App\Modules\Radius\Repositories\RadiusSettingsRepository;
use App\Modules\Radius\Validators\RadiusSettingsValidator;

class RadiusService
{
    public function __construct(
        private RadiusSettingsRepository $repository,
        private RadiusSettingsValidator $validator,
        private AuditService $audit
    )
    {
    }

    public function list(): array
    {
        return array_map(
            static fn(array $row): array => (new RadiusSettings($row))->toArray(),
            $this->repository->list()
        );
    }

    public function get(int $id): ?array
    {
        $row = $this->repository->find($id);
        return $row ? (new RadiusSettings($row))->toArray() : null;
    }

    public function create(array $input): array
    {
        $dto = new RadiusSettingsDTO($input);
        $errors = $this->validator->validate($dto);
        if ($errors !== []) {
            return ['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors];
        }

        $id = $this->repository->create($dto->toPersistenceArray());
        $this->audit->logEvent(new AuditEventDTO(
            module: 'RADIUS', action: 'CREATE_SETTINGS', description: 'Created RADIUS database settings.',
            objectType: 'RADIUS_SETTINGS', objectId: $id, newValues: $dto->toAuditArray()
        ));
        return ['ok' => true, 'message' => 'RADIUS settings created.', 'id' => $id];
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->repository->find($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'RADIUS settings not found.', 'errors' => []];
        }

        $dto = new RadiusSettingsDTO($input, $existing);
        $errors = $this->validator->validate($dto);
        if ($errors !== []) {
            return ['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors];
        }

        $this->repository->update($id, $dto->toPersistenceArray());
        $old = (new RadiusSettings($existing))->toArray();
        $this->audit->logEvent(new AuditEventDTO(
            module: 'RADIUS', action: 'UPDATE_SETTINGS', description: "Updated RADIUS settings {$id}.",
            objectType: 'RADIUS_SETTINGS', objectId: $id, oldValues: $old, newValues: $dto->toAuditArray()
        ));
        return ['ok' => true, 'message' => 'RADIUS settings updated.', 'id' => $id];
    }

    public function delete(int $id): array
    {
        $existing = $this->repository->find($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'RADIUS settings not found.', 'errors' => []];
        }

        $this->repository->delete($id);
        $this->audit->logEvent(new AuditEventDTO(
            module: 'RADIUS', action: 'DELETE_SETTINGS', description: "Deleted RADIUS settings {$id}.",
            objectType: 'RADIUS_SETTINGS', objectId: $id,
            oldValues: (new RadiusSettings($existing))->toArray()
        ));
        return ['ok' => true, 'message' => 'RADIUS settings deleted.'];
    }
}
