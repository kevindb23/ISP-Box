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

        $this->repo->syncRadiusProfile($payload['plan_name'], $payload['speed_mbps']);

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

        if ($oldPlanName !== $newPlanName) {
            $this->repo->deleteRadiusProfile($oldPlanName);
        }

        $this->repo->syncRadiusProfile($newPlanName, $payload['speed_mbps']);

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
            $ok = $this->repo->delete($id);

            if (!$ok) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete plan.',
                ];
            }

            $this->repo->deleteRadiusProfile($existing['plan_name']);

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
                'message' => 'Delete failed due to database constraint.',
            ];
        }
    }

    private function safeAudit(string $module, string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $userId = $this->getCurrentUserId();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            $method = new \ReflectionMethod($this->audit, 'log');
            $paramCount = $method->getNumberOfParameters();

            if ($paramCount >= 5) {
                $this->audit->log($module, $action, $description, $userId ?: null, $ipAddress);
                return;
            }

            if ($paramCount >= 4) {
                $this->audit->log($module, $action, $description, $userId ?: null);
                return;
            }

            $this->audit->log($module, $action, $description);
        } catch (Throwable $e) {
            // Audit must not break plan operations.
        }
    }

    private function getCurrentUserId(): ?int
    {
        $userId = $_SESSION['user']['id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['admin']['id']
            ?? null;

        $userId = (int)$userId;

        return $userId > 0 ? $userId : null;
    }
}