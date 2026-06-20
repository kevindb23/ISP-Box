<?php

namespace App\Modules\VlanManagement\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\VlanManagement\DTOs\CreateVlanDTO;
use App\Modules\VlanManagement\DTOs\UpdateVlanDTO;
use App\Modules\VlanManagement\Repositories\VlanManagementRepository;
use App\Modules\VlanManagement\Validators\CreateVlanValidator;
use App\Modules\VlanManagement\Validators\UpdateVlanValidator;
use RuntimeException;
use Throwable;

class VlanManagementService
{
    private VlanManagementRepository $repo;
    private CreateVlanValidator $createVlanValidator;
    private UpdateVlanValidator $updateVlanValidator;
    private ?AuditService $audit;

    public function __construct(
        VlanManagementRepository $repo,
        ?AuditService $audit = null
    ) {
        $this->repo = $repo;
        $this->audit = $audit;
        $this->createVlanValidator = new CreateVlanValidator();
        $this->updateVlanValidator = new UpdateVlanValidator();
    }

    public function getSummary(): array
    {
        return $this->repo->getSummary();
    }

    public function getVlans(): array
    {
        return $this->repo->getVlans();
    }

    public function getMgmtVlans(): array
    {
        return $this->repo->getMgmtVlans();
    }

    public function saveMgmtVlan(array $payload): array
    {
        $id = (int)($payload['id'] ?? 0);
        $oltId = (int)($payload['olt_id'] ?? 0);
        $mgmtVlan = (int)($payload['mgmt_vlan'] ?? $payload['vlan_id'] ?? 0);
        $description = isset($payload['description']) ? trim((string)$payload['description']) : null;

        if ($oltId <= 0) {
            throw new RuntimeException('OLT is required.');
        }

        if ($mgmtVlan < 1 || $mgmtVlan > 4094) {
            throw new RuntimeException('MGMT-VLAN must be between 1 and 4094.');
        }

        $olt = $this->repo->findOltById($oltId);
        if (!$olt) {
            throw new RuntimeException('Selected OLT not found.');
        }

        if ($this->repo->mgmtVlanExists($oltId, $mgmtVlan, $id > 0 ? $id : null)) {
            throw new RuntimeException('MGMT-VLAN already exists on the selected OLT.');
        }

        $action = $id > 0 ? 'UPDATE_MGMT_VLAN' : 'CREATE_MGMT_VLAN';

        if ($id > 0) {
            $existing = $this->repo->findMgmtVlanById($id);
            if (!$existing) {
                throw new RuntimeException('MGMT-VLAN record not found.');
            }

            $this->repo->updateMgmtVlan($id, $oltId, $mgmtVlan, $description);
            $row = $this->repo->findMgmtVlanById($id);
        } else {
            $id = $this->repo->createMgmtVlan($oltId, $mgmtVlan, $description);
            $row = $this->repo->findMgmtVlanById($id);
        }

        if (!$row) {
            throw new RuntimeException('Failed to load MGMT-VLAN record.');
        }

        $this->safeAudit(
            'VLAN',
            $action,
            sprintf(
                'MGMT-VLAN %d saved for OLT ID %d.',
                $mgmtVlan,
                $oltId
            )
        );

        try {
            $deployment = $this->deployMgmtVlanToOlt($oltId, $mgmtVlan);

            $this->safeAudit(
                'VLAN',
                'DEPLOY_MGMT_VLAN',
                sprintf(
                    'MGMT-VLAN %d deployed successfully to OLT ID %d.',
                    $mgmtVlan,
                    $oltId
                )
            );

            return [
                'record' => $row,
                'deployment' => $deployment,
                'auto_deployed' => true,
                'auto_deploy_success' => true,
                'message' => 'MGMT-VLAN saved and deployed successfully.',
            ];
        } catch (Throwable $e) {
            $this->safeAudit(
                'VLAN',
                'DEPLOY_MGMT_VLAN_FAILED',
                sprintf(
                    'MGMT-VLAN %d deployment failed for OLT ID %d. Error: %s',
                    $mgmtVlan,
                    $oltId,
                    $e->getMessage()
                )
            );

            return [
                'record' => $row,
                'deployment' => [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'commands' => [],
                    'output' => $e->getMessage(),
                ],
                'auto_deployed' => true,
                'auto_deploy_success' => false,
                'message' => 'MGMT-VLAN saved but deployment failed.',
            ];
        }
    }

    private function deployMgmtVlanToOlt(int $oltId, int $mgmtVlan): array
    {
        $olt = $this->repo->findOltById($oltId);
        if (!$olt) {
            throw new RuntimeException('Selected OLT not found.');
        }

        $script = BASE_PATH . '/app/Modules/VlanManagement/Scripts/deploy_vlan.py';
        if (!is_file($script)) {
            throw new RuntimeException('Deploy script not found: ' . $script);
        }

        $payload = [
            'host' => (string)($olt['ip_address'] ?? ''),
            'username' => (string)($olt['username'] ?? ''),
            'password' => (string)($olt['password'] ?? ''),
            'port' => 22,
            'vlan_id' => $mgmtVlan,
            'vlan_type' => 'MGMT_VLAN',
            'save_config' => true,
        ];

        if ($payload['host'] === '') {
            throw new RuntimeException('OLT IP address is missing.');
        }

        if ($payload['username'] === '') {
            throw new RuntimeException('OLT username is missing.');
        }

        if ($payload['password'] === '') {
            throw new RuntimeException('OLT password is missing.');
        }

        $command = 'python3 ' . escapeshellarg($script) . ' ' . escapeshellarg(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $rawOutput = shell_exec($command);

        if ($rawOutput === null || trim($rawOutput) === '') {
            throw new RuntimeException('No response from deploy script.');
        }

        $decoded = json_decode($rawOutput, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid deploy script response: ' . $rawOutput);
        }

        if (($decoded['success'] ?? false) !== true) {
            throw new RuntimeException($decoded['message'] ?? 'MGMT-VLAN deployment failed.');
        }

        return $decoded;
    }

    public function deleteMgmtVlan(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Invalid MGMT-VLAN record ID.');
        }

        $row = $this->repo->findMgmtVlanById($id);
        if (!$row) {
            throw new RuntimeException('MGMT-VLAN record not found.');
        }

        $oltId = (int)($row['olt_id'] ?? 0);
        $vlanId = (int)($row['mgmt_vlan'] ?? 0);

        if ($oltId <= 0) {
            throw new RuntimeException('This MGMT-VLAN has no assigned OLT.');
        }

        if ($vlanId <= 0) {
            throw new RuntimeException('Invalid MGMT-VLAN value.');
        }

        $olt = $this->repo->findOltById($oltId);
        if (!$olt) {
            throw new RuntimeException('Assigned OLT not found.');
        }

        $script = BASE_PATH . '/app/Modules/VlanManagement/Scripts/delete_vlan.py';
        if (!is_file($script)) {
            throw new RuntimeException('Delete script not found: ' . $script);
        }

        $payload = [
            'host' => (string)($olt['ip_address'] ?? ''),
            'username' => (string)($olt['username'] ?? ''),
            'password' => (string)($olt['password'] ?? ''),
            'port' => 22,
            'vlan_id' => $vlanId,
            'vlan_type' => 'MGMT_VLAN',
            'save_config' => true,
        ];

        if ($payload['host'] === '') {
            throw new RuntimeException('OLT IP address is missing.');
        }

        if ($payload['username'] === '') {
            throw new RuntimeException('OLT username is missing.');
        }

        if ($payload['password'] === '') {
            throw new RuntimeException('OLT password is missing.');
        }

        $command = 'python3 ' . escapeshellarg($script) . ' ' . escapeshellarg(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $rawOutput = shell_exec($command);

        if ($rawOutput === null || trim($rawOutput) === '') {
            throw new RuntimeException('No response from delete script.');
        }

        $decoded = json_decode($rawOutput, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid delete script response: ' . $rawOutput);
        }

        if (($decoded['success'] ?? false) !== true) {
            throw new RuntimeException($decoded['message'] ?? 'MGMT-VLAN delete failed.');
        }

        $this->repo->deleteMgmtVlan($id);

        $this->safeAudit(
            'VLAN',
            'DELETE_MGMT_VLAN',
            sprintf(
                'MGMT-VLAN %d deleted successfully from OLT ID %d.',
                $vlanId,
                $oltId
            )
        );

        return [
            'success' => true,
            'message' => 'MGMT-VLAN deleted successfully.',
            'record' => $row,
            'deployment' => $decoded,
        ];
    }

    public function createVlan(CreateVlanDTO $dto): array
    {
        $data = $dto->toArray();
        $errors = $this->createVlanValidator->validate($data);

        if (!empty($errors)) {
            throw new RuntimeException($this->flattenErrors($errors));
        }

        $oltId = (int)($data['olt_id'] ?? 0);
        if ($oltId <= 0) {
            throw new RuntimeException('OLT is required.');
        }

        $olt = $this->repo->findOltById($oltId);
        if (!$olt) {
            throw new RuntimeException('Selected OLT not found.');
        }

        $vlanType = strtoupper((string)($data['vlan_type'] ?? 'C_VLAN'));
        $vlanId = (int)($data['vlan_id'] ?? 0);
        $oltPortId = isset($data['olt_port_id']) && $data['olt_port_id'] !== '' ? (int)$data['olt_port_id'] : null;

        if ($this->repo->vlanNumberExists($vlanId, null, $oltId)) {
            throw new RuntimeException(
                'VLAN ID is already assigned on the selected OLT. A VLAN cannot exist as both C-VLAN and S-VLAN on the same OLT.'
            );
        }

        if ($vlanType === 'S_VLAN') {
            if ($oltPortId === null || $oltPortId <= 0) {
                throw new RuntimeException('OLT Port is required for S-VLAN.');
            }

            $port = $this->repo->findOltPortById($oltPortId);
            if (!$port) {
                throw new RuntimeException('Selected OLT Port not found.');
            }

            if ((int)($port['olt_id'] ?? 0) !== $oltId) {
                throw new RuntimeException('Selected OLT Port does not belong to the selected OLT.');
            }

            if ($this->repo->sVlanPortExists($oltId, $oltPortId)) {
                throw new RuntimeException('Selected OLT Port already has an assigned S-VLAN.');
            }
        } else {
            $oltPortId = null;
        }

        $data['olt_id'] = $oltId;
        $data['olt_port_id'] = $oltPortId;
        $data['vlan_type'] = $vlanType;
        $data['deployment_status'] = 'PENDING';
        $data['deployed_at'] = null;
        $data['deployment_output'] = null;

        $id = $this->repo->createVlan($data);

        $createdRow = $this->repo->findVlanById($id);
        if (!$createdRow) {
            throw new RuntimeException('Failed to load newly created VLAN.');
        }

        $this->safeAudit(
            'VLAN',
            'CREATE_VLAN',
            sprintf(
                'Created %s VLAN %d for OLT ID %d.',
                $vlanType,
                $vlanId,
                $oltId
            )
        );

        try {
            $deployment = $this->deployVlan($id);
            $freshRow = $this->repo->findVlanById($id);

            return [
                'record' => $freshRow ?: $createdRow,
                'deployment' => $deployment,
                'auto_deployed' => true,
                'auto_deploy_success' => true,
            ];
        } catch (Throwable $e) {
            $this->repo->updateDeploymentState(
                $id,
                'FAILED',
                null,
                $e->getMessage()
            );

            $this->safeAudit(
                'VLAN',
                'DEPLOY_VLAN_FAILED',
                sprintf(
                    'Deployment failed for %s VLAN %d on OLT ID %d. Error: %s',
                    $vlanType,
                    $vlanId,
                    $oltId,
                    $e->getMessage()
                )
            );

            $freshRow = $this->repo->findVlanById($id);

            return [
                'record' => $freshRow ?: $createdRow,
                'deployment' => [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'commands' => [],
                    'output' => $e->getMessage(),
                ],
                'auto_deployed' => true,
                'auto_deploy_success' => false,
            ];
        }
    }

    public function updateVlan(UpdateVlanDTO $dto): array
    {
        $data = $dto->toArray();
        $errors = $this->updateVlanValidator->validate($data);

        if (!empty($errors)) {
            throw new RuntimeException($this->flattenErrors($errors));
        }

        $existing = $this->repo->findVlanById($dto->id);
        if (!$existing) {
            throw new RuntimeException('VLAN record not found.');
        }

        $oltId = (int)($data['olt_id'] ?? $existing['olt_id'] ?? 0);
        if ($oltId <= 0) {
            throw new RuntimeException('OLT is required.');
        }

        $olt = $this->repo->findOltById($oltId);
        if (!$olt) {
            throw new RuntimeException('Selected OLT not found.');
        }

        $vlanId = (int)($data['vlan_id'] ?? $existing['vlan_id'] ?? 0);
        $vlanType = strtoupper((string)($data['vlan_type'] ?? $existing['vlan_type'] ?? 'C_VLAN'));
        $oltPortId = array_key_exists('olt_port_id', $data)
            ? ($data['olt_port_id'] !== '' ? (int)$data['olt_port_id'] : null)
            : (isset($existing['olt_port_id']) && $existing['olt_port_id'] !== '' ? (int)$existing['olt_port_id'] : null);

        if ($this->repo->vlanNumberExists($vlanId, (int)$dto->id, $oltId)) {
            throw new RuntimeException(
                'VLAN ID is already assigned on the selected OLT. A VLAN cannot exist as both C-VLAN and S-VLAN on the same OLT.'
            );
        }

        if ($vlanType === 'S_VLAN') {
            if ($oltPortId === null || $oltPortId <= 0) {
                throw new RuntimeException('OLT Port is required for S-VLAN.');
            }

            $port = $this->repo->findOltPortById($oltPortId);
            if (!$port) {
                throw new RuntimeException('Selected OLT Port not found.');
            }

            if ((int)($port['olt_id'] ?? 0) !== $oltId) {
                throw new RuntimeException('Selected OLT Port does not belong to the selected OLT.');
            }

            if ($this->repo->sVlanPortExists($oltId, $oltPortId, (int)$dto->id)) {
                throw new RuntimeException('Selected OLT Port already has an assigned S-VLAN.');
            }
        } else {
            $oltPortId = null;
        }

        $data['olt_id'] = $oltId;
        $data['olt_port_id'] = $oltPortId;
        $data['vlan_id'] = $vlanId;
        $data['vlan_type'] = $vlanType;
        $data['name'] = (string)($data['name'] ?? $existing['name'] ?? '');
        $data['description'] = (string)($data['description'] ?? $existing['description'] ?? '');

        $this->repo->updateVlan($dto->id, $data);

        $row = $this->repo->findVlanById($dto->id);
        if (!$row) {
            throw new RuntimeException('Failed to load updated VLAN.');
        }

        $this->safeAudit(
            'VLAN',
            'UPDATE_VLAN',
            sprintf(
                'Updated VLAN record ID %d. VLAN: %d, Type: %s, OLT ID: %d.',
                (int)$dto->id,
                $vlanId,
                $vlanType,
                $oltId
            )
        );

        return $row;
    }

    public function deployVlan(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Invalid VLAN record ID.');
        }

        $row = $this->repo->findVlanById($id);
        if (!$row) {
            throw new RuntimeException('VLAN record not found.');
        }

        $oltId = (int)($row['olt_id'] ?? 0);
        if ($oltId <= 0) {
            throw new RuntimeException('This VLAN has no assigned OLT.');
        }

        $olt = $this->repo->findOltById($oltId);
        if (!$olt) {
            throw new RuntimeException('OLT not found for this VLAN.');
        }

        $script = BASE_PATH . '/app/Modules/VlanManagement/Scripts/deploy_vlan.py';
        if (!is_file($script)) {
            throw new RuntimeException('Deploy script not found: ' . $script);
        }

        $payload = [
            'host' => (string)($olt['ip_address'] ?? ''),
            'username' => (string)($olt['username'] ?? ''),
            'password' => (string)($olt['password'] ?? ''),
            'port' => 22,
            'vlan_id' => (int)($row['vlan_id'] ?? 0),
            'vlan_type' => (string)($row['vlan_type'] ?? ''),
            'save_config' => true,
        ];

        if ($payload['host'] === '') {
            throw new RuntimeException('OLT IP address is missing.');
        }

        if ($payload['username'] === '') {
            throw new RuntimeException('OLT username is missing.');
        }

        if ($payload['password'] === '') {
            throw new RuntimeException('OLT password is missing.');
        }

        $command = 'python3 ' . escapeshellarg($script) . ' ' . escapeshellarg(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $rawOutput = shell_exec($command);

        if ($rawOutput === null || trim($rawOutput) === '') {
            $this->repo->updateDeploymentState($id, 'FAILED', null, 'No response from deploy script.');
            throw new RuntimeException('No response from deploy script.');
        }

        $decoded = json_decode($rawOutput, true);

        if (!is_array($decoded)) {
            $this->repo->updateDeploymentState($id, 'FAILED', null, $rawOutput);
            throw new RuntimeException('Invalid deploy script response: ' . $rawOutput);
        }

        if (($decoded['success'] ?? false) !== true) {
            $this->repo->updateDeploymentState(
                $id,
                'FAILED',
                null,
                json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            throw new RuntimeException($decoded['message'] ?? 'Deployment failed.');
        }

        $this->repo->updateDeploymentState(
            $id,
            'DEPLOYED',
            date('Y-m-d H:i:s'),
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->safeAudit(
            'VLAN',
            'DEPLOY_VLAN',
            sprintf(
                'Deployed VLAN %d Type %s to OLT ID %d.',
                (int)($row['vlan_id'] ?? 0),
                (string)($row['vlan_type'] ?? ''),
                $oltId
            )
        );

        return $decoded;
    }

    public function deleteVlan(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Invalid VLAN record ID.');
        }

        $row = $this->repo->findVlanById($id);
        if (!$row) {
            throw new RuntimeException('VLAN not found.');
        }

        $oltId = (int)($row['olt_id'] ?? 0);
        if ($oltId <= 0) {
            throw new RuntimeException('This VLAN has no assigned OLT.');
        }

        $olt = $this->repo->findOltById($oltId);
        if (!$olt) {
            throw new RuntimeException('Assigned OLT not found.');
        }

        $script = BASE_PATH . '/app/Modules/VlanManagement/Scripts/delete_vlan.py';
        if (!is_file($script)) {
            throw new RuntimeException('Delete script not found: ' . $script);
        }

        $payload = [
            'host' => (string)($olt['ip_address'] ?? ''),
            'username' => (string)($olt['username'] ?? ''),
            'password' => (string)($olt['password'] ?? ''),
            'port' => 22,
            'vlan_id' => (int)($row['vlan_id'] ?? 0),
            'vlan_type' => (string)($row['vlan_type'] ?? 'C_VLAN'),
            'save_config' => true,
        ];

        if ($payload['host'] === '') {
            throw new RuntimeException('OLT IP address is missing.');
        }

        if ($payload['username'] === '') {
            throw new RuntimeException('OLT username is missing.');
        }

        if ($payload['password'] === '') {
            throw new RuntimeException('OLT password is missing.');
        }

        $command = 'python3 ' . escapeshellarg($script) . ' ' . escapeshellarg(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $rawOutput = shell_exec($command);

        if ($rawOutput === null || trim($rawOutput) === '') {
            throw new RuntimeException('No response from delete script.');
        }

        $decoded = json_decode($rawOutput, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid delete script response: ' . $rawOutput);
        }

        if (($decoded['success'] ?? false) !== true) {
            throw new RuntimeException($decoded['message'] ?? 'Delete failed.');
        }

        $this->repo->deleteVlan($id);

        $this->safeAudit(
            'VLAN',
            'DELETE_VLAN',
            sprintf(
                'Deleted VLAN record ID %d. VLAN: %d, Type: %s, OLT ID: %d.',
                $id,
                (int)($row['vlan_id'] ?? 0),
                (string)($row['vlan_type'] ?? 'C_VLAN'),
                $oltId
            )
        );

        return $decoded;
    }

    private function flattenErrors(array $errors): string
    {
        $lines = [];

        foreach ($errors as $fieldErrors) {
            foreach ((array)$fieldErrors as $msg) {
                $lines[] = (string)$msg;
            }
        }

        return implode(' ', $lines);
    }

    private function safeAudit(string $module, string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log($module, $action, $description);
        } catch (Throwable $e) {
            // Audit must not break VLAN operations.
        }
    }
}