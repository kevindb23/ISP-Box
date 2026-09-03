<?php

namespace App\Modules\CgnatManagement\Controllers;

use App\Modules\CgnatManagement\DTOs\ApplyNatPoolDTO;
use App\Modules\CgnatManagement\DTOs\CreateNatPoolDTO;
use App\Modules\CgnatManagement\DTOs\UpdateNatPoolDTO;
use App\Modules\CgnatManagement\DTOs\UpdateCgnatDTO;
use App\Modules\CgnatManagement\DTOs\RemovePostroutingRulesDTO;
use App\Modules\CgnatManagement\Services\CgnatManagementService;
use App\Modules\CgnatManagement\Services\CgnatService;
use App\Modules\CgnatManagement\Validators\ApplyNatPoolValidator;
use App\Modules\CgnatManagement\Validators\CreateNatPoolValidator;
use App\Modules\CgnatManagement\Validators\UpdateCgnatValidator;
use App\Modules\CgnatManagement\Validators\UpdateNatPoolValidator;
use App\Modules\CgnatManagement\Validators\RemovePostroutingRulesValidator;
use Framework\ApiController;
use Throwable;

class CgnatManagementApiController extends ApiController
{
    private CgnatManagementService $service;
    private CgnatService $cgnatService;

    public function __construct(
        CgnatManagementService $service,
        CgnatService $cgnatService,
        private CreateNatPoolValidator $createPoolValidator,
        private UpdateNatPoolValidator $updatePoolValidator,
        private ApplyNatPoolValidator $applyPoolValidator,
        private UpdateCgnatValidator $cgnatValidator,
        private RemovePostroutingRulesValidator $removeRulesValidator
    )
    {
        $this->service = $service;
        $this->cgnatService = $cgnatService;
    }

    public function pools()
    {
        return $this->jsonOk($this->service->getPools());
    }

    public function createPool()
    {
        try {
            $payload=$this->getInputData(); $errors=$this->createPoolValidator->validate($payload);
            if($errors)return $this->jsonError((string)reset($errors),422);
            $dto = new CreateNatPoolDTO($payload);
            $row = $this->service->createPool($dto);

            return $this->jsonMessage($row, 'NAT pool created successfully.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function updatePool($id)
    {
        try {
            $payload = $this->getInputData();
            $payload['id'] = (int)$id;

            $errors=$this->updatePoolValidator->validate($payload);
            if($errors)return $this->jsonError((string)reset($errors),422);

            $dto = new UpdateNatPoolDTO($payload);
            $row = $this->service->updatePool($dto);

            return $this->jsonMessage($row, 'NAT pool updated successfully.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function deletePool($id)
    {
        try {
            $this->service->deletePool((int)$id);
            return $this->jsonMessage(true, 'NAT pool deleted successfully.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function previewPool($id)
    {
        try {
            return $this->jsonOk($this->service->previewPool((int)$id));
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function applyPool($id)
    {
        try {
            $payload = $this->getInputData();
            $payload['pool_id'] = (int)$id;

            $errors=$this->applyPoolValidator->validate($payload);
            if($errors)return $this->jsonError((string)reset($errors),422);

            $dto = new ApplyNatPoolDTO($payload);
            $result = $this->service->applyPool($dto);

            return $this->jsonMessage($result, 'NAT pool apply completed.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function deployments()
    {
        return $this->jsonOk($this->service->getDeployments());
    }

    public function usage()
    {
        $rows = array_map(
            static fn($item) => method_exists($item, 'toArray') ? $item->toArray() : (array)$item,
            $this->service->getUsage()
        );

        return $this->jsonOk($rows);
    }

    public function config()
    {
        try {
            return $this->jsonOk($this->cgnatService->get());
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    public function saveConfig()
    {
        try {
            $payload=$this->getInputData(); $this->cgnatValidator->validate($payload);
            $this->cgnatService->save((new UpdateCgnatDTO($payload))->toArray());
            return $this->jsonMessage([], 'CGNAT configuration saved successfully.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function applyConfig()
    {
        try {
            $result = $this->cgnatService->apply();
            return $this->jsonMessage($result, 'CGNAT configuration applied successfully.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function removePostroutingRules()
    {
        try {
            $dto=RemovePostroutingRulesDTO::fromArray($this->getInputData());
            $errors=$this->removeRulesValidator->validate($dto->ruleHashes);
            if($errors)return $this->jsonError((string)reset($errors),422);
            return $this->jsonMessage($this->cgnatService->removePostroutingRules($dto->ruleHashes),'Selected POSTROUTING rules removed and verified.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(),422);
        }
    }

    private function getInputData(): array
    {
        return $this->request()->input();
    }

    private function jsonOk($data)
    {
        return $this->respond([
            'ok' => true,
            'status' => 'success',
            'success' => true,
            'message' => '',
            'data' => $data,
        ], 200);
    }

    private function jsonMessage($data, string $message)
    {
        return $this->respond([
            'ok' => true,
            'status' => 'success',
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    private function jsonError(string $message, int $status = 500)
    {
        return $this->respond([
            'ok' => false,
            'status' => 'error',
            'success' => false,
            'message' => $message,
            'data' => null,
        ], $status);
    }

    private function respond(array $payload, int $status = 200)
    {
        $ok = (bool)($payload['ok'] ?? $payload['success'] ?? $status < 400);
        $message = (string)($payload['message'] ?? ($ok ? 'OK' : 'Request failed.'));
        $data = $payload['data'] ?? null;
        $ok ? $this->success($data, $message, $status) : $this->error($message, $status, [], $data);
        return null;
    }

}
