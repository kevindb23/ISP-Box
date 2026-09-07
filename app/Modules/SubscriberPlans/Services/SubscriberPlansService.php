<?php

namespace App\Modules\SubscriberPlans\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\SubscriberPlans\DTOs\CreateSubscriberPlansDTO;
use App\Modules\SubscriberPlans\Entities\SubscriberPlans;
use App\Modules\SubscriberPlans\Repositories\SubscriberPlansRepository;
use App\Modules\SubscriberPlans\Validators\CreateSubscriberPlansValidator;
use Throwable;

class SubscriberPlansService
{
    private SubscriberPlansRepository $repo;
    private ?AuditService $audit;

    public function __construct(
        SubscriberPlansRepository $repo,
        ?AuditService $audit = null
    ) {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    public function getAll(): array
    {
        $rows = $this->repo->getAll();

        return array_map(function ($row) {
            return (new SubscriberPlans($row))->toArray();
        }, $rows);
    }

    public function create(array $data): array
    {
        $dto = new CreateSubscriberPlansDTO($data);
        $payload = $dto->toArray();

        $errors = CreateSubscriberPlansValidator::validate($payload);

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => $errors[0],
            ];
        }

        $duplicate = $this->repo->findByPlanName($payload['plan_name']);

        if ($duplicate) {
            return [
                'success' => false,
                'message' => 'Plan name already exists.',
            ];
        }

        $result = $this->repo->create($payload);

        if (!$result['success']) {
            return $result;
        }

        try {
            $this->repo->syncRadiusProfile($payload['plan_name'], $payload['speed_mbps']);
        } catch (Throwable $e) {
            // A plan without its RADIUS authorization profile must never be
            // left persisted as if provisioning were complete.
            try {
                $this->repo->delete((int)$result['id']);
            } catch (Throwable $rollback) {
                error_log('[SubscriberPlans] create rollback failed: ' . $rollback->getMessage());
            }
            error_log('[SubscriberPlans] create RADIUS sync failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error_status' => 503,
                'message' => 'Plan was not saved because RADIUS synchronization failed. Check the active RADIUS database settings and try again.',
            ];
        }

        $this->safeAudit(
            'PLANS',
            'CREATE_PLAN',
            sprintf(
                'Created subscriber plan: %s, Speed: %s Mbps, Price: %s',
                (string)$payload['plan_name'],
                (string)($payload['speed_mbps'] ?? '0'),
                (string)($payload['price'] ?? '0')
            )
        );

        return [
            'success' => true,
            'message' => 'Plan created successfully.',
        ];
    }

    public function update($id, array $data): array
    {
        $id = (int)$id;

        if ($id <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid plan id.',
            ];
        }

        $existing = $this->repo->findById($id);

        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Plan not found.',
            ];
        }

        $merged = array_merge($existing, $data);
        $dto = new CreateSubscriberPlansDTO($merged);
        $payload = $dto->toArray();

        $errors = CreateSubscriberPlansValidator::validate($payload);

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => $errors[0],
            ];
        }

        $duplicate = $this->repo->findByPlanNameExceptId($payload['plan_name'], $id);

        if ($duplicate) {
            return [
                'success' => false,
                'message' => 'Plan name already exists.',
            ];
        }

        $oldPlanName = (string)$existing['plan_name'];
        $newPlanName = (string)$payload['plan_name'];

        $result = $this->repo->update($id, $payload);

        if (!$result['success']) {
            return $result;
        }

        try {
            if ($oldPlanName !== $newPlanName) {
                $this->repo->deleteRadiusProfile($oldPlanName);
            }
            $this->repo->syncRadiusProfile($newPlanName, $payload['speed_mbps']);
        } catch (Throwable $e) {
            try {
                $this->repo->update($id, $existing);
            } catch (Throwable $rollback) {
                error_log('[SubscriberPlans] update rollback failed: ' . $rollback->getMessage());
            }
            error_log('[SubscriberPlans] update RADIUS sync failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error_status' => 503,
                'message' => 'Plan was not updated because RADIUS synchronization failed. Check the active RADIUS database settings and try again.',
            ];
        }

        $this->safeAudit(
            'PLANS',
            'UPDATE_PLAN',
            sprintf(
                'Updated subscriber plan ID %d: %s to %s, Speed: %s Mbps, Price: %s',
                $id,
                $oldPlanName,
                $newPlanName,
                (string)($payload['speed_mbps'] ?? '0'),
                (string)($payload['price'] ?? '0')
            )
        );

        return [
            'success' => true,
            'message' => 'Plan updated successfully.',
        ];
    }

    public function delete($id): array
    {
        $id = (int)$id;

        if ($id <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid plan id.',
            ];
        }

        $existing = $this->repo->findById($id);

        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Plan not found.',
            ];
        }

        $usage = $this->repo->countActiveUsageByPlanId($id);

        if ($usage > 0) {
            return [
                'success' => false,
                'message' => 'Plan is currently used by active subscribers.',
            ];
        }

        try {
            // Keep the portal plan if the authorization database is down.
            $this->repo->deleteRadiusProfile($existing['plan_name']);

            $ok = $this->repo->delete($id);

            if (!$ok) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete plan.',
                ];
            }

            $this->safeAudit(
                'PLANS',
                'DELETE_PLAN',
                sprintf(
                    'Deleted subscriber plan ID %d: %s',
                    $id,
                    (string)$existing['plan_name']
                )
            );

            return [
                'success' => true,
                'message' => 'Plan deleted successfully.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error_status' => 503,
                'message' => 'Plan was not deleted because RADIUS synchronization failed. Check the active RADIUS database settings and try again.',
            ];
        }
    }

    private function safeAudit(string $module, string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log($module, $action, $description);
        } catch (Throwable $e) {
            error_log('[Audit][' . $module . '] ' . $e->getMessage());
        }
    }
}
