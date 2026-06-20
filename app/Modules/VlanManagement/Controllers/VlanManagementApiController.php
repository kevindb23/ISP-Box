<?php

namespace App\Modules\VlanManagement\Controllers;

use App\Infrastructure\Database\DatabaseConnection;
use App\Modules\VlanManagement\DTOs\CreateVlanDTO;
use App\Modules\VlanManagement\DTOs\UpdateVlanDTO;
use App\Modules\VlanManagement\Repositories\VlanManagementRepository;
use App\Modules\VlanManagement\Services\VlanManagementService;
use Framework\ApiController;
use Throwable;

class VlanManagementApiController extends ApiController
{
    private VlanManagementService $service;

    public function __construct()
    {
        $pdo = (new DatabaseConnection())->get();
        $repo = new VlanManagementRepository($pdo);
        $this->service = new VlanManagementService($repo);
    }

    public function summary()
    {
        try {
            $this->success($this->service->getSummary(), 'VLAN summary loaded successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function vlans()
    {
        try {
            $this->success($this->service->getVlans(), 'VLAN records loaded successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function createVlan()
    {
        try {
            $dto = CreateVlanDTO::fromArray($this->getInputData());
            $result = $this->service->createVlan($dto);

            $message = !empty($result['auto_deploy_success'])
                ? 'VLAN created and deployed successfully.'
                : 'VLAN created but deployment failed.';

            $this->success($result, $message);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function updateVlan($id)
    {
        try {
            $payload = $this->getInputData();
            $payload['id'] = (int)$id;

            $dto = UpdateVlanDTO::fromArray($payload);
            $row = $this->service->updateVlan($dto);

            $this->success($row, 'VLAN updated successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function deleteVlan($id)
    {
        try {
            $result = $this->service->deleteVlan((int)$id);
            $this->success($result, $result['message'] ?? 'VLAN deleted successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function mgmtVlans()
    {
        try {
            $this->success($this->service->getMgmtVlans(), 'MGMT-VLAN records loaded successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function saveMgmtVlan()
    {
        try {
            $payload = $this->getInputData();
            $result = $this->service->saveMgmtVlan($payload);

            $this->success($result, $result['message'] ?? 'MGMT-VLAN saved successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function deleteMgmtVlan($id)
    {
        try {
            $result = $this->service->deleteMgmtVlan((int)$id);
            $this->success($result, $result['message'] ?? 'MGMT-VLAN deleted successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    private function getInputData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST ?: [];
    }
}