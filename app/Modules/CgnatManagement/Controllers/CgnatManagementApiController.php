<?php

namespace App\Modules\CgnatManagement\Controllers;

use App\Infrastructure\Database\DatabaseConnection;
use App\Modules\CgnatManagement\DTOs\ApplyNatPoolDTO;
use App\Modules\CgnatManagement\DTOs\CreateNatPoolDTO;
use App\Modules\CgnatManagement\DTOs\UpdateBngSettingDTO;
use App\Modules\CgnatManagement\DTOs\UpdateNatPoolDTO;
use App\Modules\CgnatManagement\Repositories\BngSettingRepository;
use App\Modules\CgnatManagement\Repositories\CgnatManagementRepository;
use App\Modules\CgnatManagement\Repositories\CgnatRepository;
use App\Modules\CgnatManagement\Services\BngConnectionService;
use App\Modules\CgnatManagement\Services\CgnatManagementService;
use App\Modules\CgnatManagement\Services\CgnatService;
use App\Modules\CgnatManagement\Validators\UpdateBngSettingValidator;
use Framework\Controller;
use PDO;
use Throwable;

class CgnatManagementApiController extends Controller
{
    private PDO $pdo;
    private CgnatManagementService $service;
    private CgnatService $cgnatService;
    private BngConnectionService $bngService;
    private UpdateBngSettingValidator $bngValidator;

    public function __construct()
    {
        $this->pdo = (new DatabaseConnection())->get();

        $repo = new CgnatManagementRepository($this->pdo);
        $bngRepo = new BngSettingRepository($this->pdo);
        $cgnatRepo = new CgnatRepository($this->pdo);

        $this->bngService = new BngConnectionService($bngRepo);
        $this->cgnatService = new CgnatService($cgnatRepo, $this->bngService);
        $this->service = new CgnatManagementService($repo, $this->bngService);
        $this->bngValidator = new UpdateBngSettingValidator();
    }

    public function pools()
    {
        return $this->jsonOk($this->service->getPools());
    }

    public function createPool()
    {
        try {
            $dto = new CreateNatPoolDTO($this->getInputData());
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
            $this->cgnatService->save($this->getInputData());
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

    public function bngSetting()
    {
        try {
            return $this->jsonOk($this->bngService->getSetting());
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    public function saveBngSetting()
    {
        $payload = $this->getInputData();
        $errors = $this->bngValidator->validate($payload);

        if (!empty($errors)) {
            return $this->respond([
                'ok' => false,
                'status' => 'validation_error',
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors,
                'data' => null,
            ], 422);
        }

        try {
            $dto = new UpdateBngSettingDTO($payload);
            $saved = $this->bngService->saveSetting($dto);

            return $this->jsonMessage($saved, 'BNG connection saved successfully.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    public function testBngConnection()
    {
        try {
            $result = $this->bngService->testConnection();
            return $this->jsonMessage($result, 'BNG test completed.');
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    public function bngRuntime()
    {
        try {
            return $this->jsonOk($this->bngService->getRuntimeStatus());
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    public function createSvlanInterface()
    {
        try {
            $payload = $this->getInputData();
            $vlanId = (int)($payload['vlan_id'] ?? $payload['svlan'] ?? 0);

            if ($vlanId < 1 || $vlanId > 4094) {
                return $this->jsonError('Valid S-VLAN ID is required.', 422);
            }

            $svlan = $this->findSvlanFromVlanManagement($vlanId);

            if (!$svlan) {
                return $this->jsonError(
                    "S-VLAN {$vlanId} does not exist in VLAN Management.",
                    422
                );
            }

            $result = $this->bngService->ensureSvlanInterface($vlanId);

            return $this->jsonMessage([
                'svlan' => $svlan,
                'bng_result' => $result,
            ], "BNG S-VLAN interface checked for VLAN {$vlanId}.");
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    private function findSvlanFromVlanManagement(int $vlanId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM network_vlans
            WHERE vlan_id = :vlan_id
              AND UPPER(vlan_type) = 'S_VLAN'
            LIMIT 1
        ");

        $stmt->execute([
            'vlan_id' => $vlanId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function getInputData(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (is_array($json)) {
            return $json;
        }

        return $_POST ?: [];
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
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return null;
    }

    public function availableSvlans()
    {
        try {
            $pdo = (new DatabaseConnection())->get();

            $stmt = $pdo->query("
            SELECT 
                v.id,
                v.vlan_id,
                v.name,
                CASE 
                    WHEN b.vlan_id IS NOT NULL THEN 'DEPLOYED'
                    ELSE 'AVAILABLE'
                END AS status
            FROM network_vlans v
            LEFT JOIN bng_vlan_interfaces b
                ON b.vlan_id = v.vlan_id
            WHERE v.vlan_type = 'S_VLAN'
            ORDER BY v.vlan_id ASC
        ");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonOk($rows);

        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}