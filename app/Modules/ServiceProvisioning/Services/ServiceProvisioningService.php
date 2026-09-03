<?php

namespace App\Modules\ServiceProvisioning\Services;

use App\Infrastructure\NetworkAutomation\NetworkCommandRunner;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Services\BillingAutomationService;
use App\Modules\OntDevices\Services\AcsService;
use App\Modules\ServiceProvisioning\DTOs\CreateProvisioningDTO;
use App\Modules\ServiceProvisioning\Repositories\ServiceProvisioningRepository;
use App\Modules\ServiceProvisioning\Validators\CreateProvisioningValidator;
use App\Modules\WorkOrders\Services\WorkOrdersService;
use Exception;
use Throwable;

class ServiceProvisioningService
{
    private const MAX_ONTS_PER_FSP = 16;

    private ServiceProvisioningRepository $repo;
    private AcsService $acsService;
    private AuditService $audit;

    public function __construct(
        ServiceProvisioningRepository $repo,
        AcsService $acsService,
        AuditService $audit,
        private BillingAutomationService $billingAutomation,
        private WorkOrdersService $workOrders,
        private CreateProvisioningValidator $validator,
        private NetworkCommandRunner $networkRunner
    ) {
        $this->repo = $repo;
        $this->acsService = $acsService;
        $this->audit = $audit;
    }

    public function paginateJobs(
        int $page = 1,
        int $limit = 20,
        string $search = '',
        string $status = ''
    ): array {
        return $this->repo->paginateJobs($page, $limit, $search, $status);
    }

    public function getJobDetails(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $job = $this->repo->findJobById($id);

        if (!$job) {
            return null;
        }

        $job['request_payload_decoded'] = $this->decodeJsonField($job['request_payload'] ?? null);
        $job['result_payload_decoded'] = $this->decodeJsonField($job['result_payload'] ?? null);
        $job['job_logs'] = $this->repo->getJobLogs($id);

        $job['acs_device'] = null;

        if (!empty($job['acs_id'])) {
            $job['acs_device'] = [
                'id' => $job['acs_id'],
                'serial_number' => $job['acs_serial_number'] ?? null,
                'wan_ip' => $job['acs_wan_ip'] ?? null,
                'status' => $job['acs_status'] ?? null,
                'last_seen' => $job['acs_last_seen'] ?? null,
                'firmware_version' => $job['acs_firmware_version'] ?? null,
                'uptime' => $job['acs_uptime'] ?? null,
            ];
        }

        $job['binding_details'] = null;

        if (!empty($job['binding_id'])) {
            $job['binding_details'] = [
                'binding_id' => $job['binding_id'],
                'cvlan' => $job['binding_cvlan'] ?? null,
                'svlan' => $job['binding_svlan'] ?? null,
                'ont_assigned_id' => $job['binding_ont_assigned_id'] ?? null,
                'pppoe_service_port' => $job['binding_pppoe_service_port'] ?? null,
                'tr069_service_port' => $job['binding_tr069_service_port'] ?? null,
                'lineprofile_id' => $job['lineprofile_id'] ?? null,
                'srvprofile_id' => $job['srvprofile_id'] ?? null,
                'tr069_profile_id' => $job['tr069_profile_id'] ?? null,
                'internet_wan_profile_id' => $job['internet_wan_profile_id'] ?? null,
                'tr069_wan_profile_id' => $job['tr069_wan_profile_id'] ?? null,
                'assigned_at' => $job['assigned_at'] ?? null,
                'installed_at' => $job['installed_at'] ?? null,
                'activated_at' => $job['activated_at'] ?? null,
            ];
        }

        return $job;
    }

    public function getSupportSubscribers(string $search = ''): array
    {
        return $this->repo->getSupportSubscribers($search);
    }

    public function getSupportPlans(string $search = '', int $subscriberId = 0): array
    {
        return $this->repo->getSupportPlans($search, $subscriberId);
    }

    public function getSupportOnts(string $search = ''): array
    {
        return $this->repo->getSupportOnts($search);
    }

    public function getSupportOlts(string $search = ''): array
    {
        return $this->repo->getSupportOlts($search);
    }

    public function getSupportOltPorts(int $oltId): array
    {
        if ($oltId <= 0) {
            throw new Exception('OLT ID is required.');
        }

        return $this->repo->getSupportOltPorts($oltId);
    }

    public function getSupportNetworkBoxes(
        int $oltId = 0,
        int $oltPortId = 0,
        string $boxType = 'NAP'
    ): array {
        return $this->repo->getSupportNetworkBoxes($oltId, $oltPortId, $boxType);
    }

    public function getSupportSplitters(int $networkBoxId): array
    {
        if ($networkBoxId <= 0) {
            throw new Exception('Network box ID is required.');
        }

        return $this->repo->getSupportSplitters($networkBoxId);
    }

    public function getSupportSplitterOutputPorts(int $splitterId): array
    {
        if ($splitterId <= 0) {
            throw new Exception('Splitter ID is required.');
        }

        return $this->repo->getSupportSplitterOutputPorts($splitterId);
    }

    public function validateProvisioning($dto): array
    {
        $normalized = $this->normalizeDto($dto);
        $this->validateRequiredPayload($normalized);

        if (!$this->repo->subscriberExists($normalized->subscriber_id)) {
            throw new Exception('Subscriber not found.');
        }

        if (!$this->repo->planExists($normalized->plan_id)) {
            throw new Exception('Plan not found.');
        }

        $resolvedServiceId = 0;

        if ($normalized->service_id > 0) {
            if (!$this->repo->serviceExists($normalized->service_id)) {
                throw new Exception('Service not found.');
            }

            if (!$this->repo->serviceBelongsToSubscriber($normalized->service_id, $normalized->subscriber_id)) {
                throw new Exception('Selected service does not belong to the selected subscriber.');
            }

            $resolvedServiceId = $normalized->service_id;
        } else {
            $existingService = $this->repo->findSubscriberServiceBySubscriberAndPlan(
                $normalized->subscriber_id,
                $normalized->plan_id
            );

            if ($existingService) {
                $resolvedServiceId = (int)($existingService['id'] ?? 0);
            }
        }

        if ($resolvedServiceId > 0) {
            $existingBinding = $this->repo->findBindingByServiceId($resolvedServiceId);

            if ($existingBinding && !empty($existingBinding['activated_at'])) {
                throw new Exception('This service already has an active provisioning binding.');
            }
        }

        if (!$this->repo->ontExists($normalized->ont_id)) {
            throw new Exception('ONT not found.');
        }

        if (!$this->repo->isOntKnown($normalized->ont_id)) {
            throw new Exception('ONT must exist in inventory before provisioning.');
        }

        $inventoryOnt = $this->repo->getOntById($normalized->ont_id);

        if (!$inventoryOnt) {
            throw new Exception('ONT inventory record not found.');
        }

        $inventorySerial = strtoupper(trim((string)($inventoryOnt['serial_number'] ?? '')));

        if ($inventorySerial === '' || $inventorySerial !== $normalized->ont_serial) {
            throw new Exception('Selected ONT serial does not match the inventory record.');
        }

        if (!$this->repo->oltExists($normalized->olt_id)) {
            throw new Exception('OLT not found.');
        }

        $oltPort = $this->repo->getOltPort($normalized->olt_port_id);

        if (!$oltPort) {
            throw new Exception('OLT port not found.');
        }

        if ((int)($oltPort['olt_id'] ?? 0) !== $normalized->olt_id) {
            throw new Exception('Selected OLT port does not belong to the selected OLT.');
        }

        if (!str_contains(strtoupper((string)($oltPort['port_type'] ?? '')), 'GPON')) {
            throw new Exception('Only GPON subscriber ports can be used for provisioning.');
        }

        $reportedCount = (int)($oltPort['reported_ont_count'] ?? $oltPort['ont_count'] ?? 0);
        $boundCount = $this->repo->getActiveBindingCountForOltPort($normalized->olt_port_id);
        $effectiveCount = max($reportedCount, $boundCount);

        if ($effectiveCount >= self::MAX_ONTS_PER_FSP) {
            throw new Exception(sprintf(
                'Selected OLT port is FULL (%d/%d ONTs).',
                $effectiveCount,
                self::MAX_ONTS_PER_FSP
            ));
        }

        if (!$this->repo->networkBoxExists($normalized->network_box_id)) {
            throw new Exception('NAP / network box not found.');
        }

        if (!$this->repo->splitterExists($normalized->splitter_id)) {
            throw new Exception('Splitter not found.');
        }

        if (!$this->repo->splitterOutputPortExists($normalized->splitter_output_port_id)) {
            throw new Exception('Splitter output port not found.');
        }

        $splitterPorts = $this->repo->getSupportSplitterOutputPorts($normalized->splitter_id);
        $selectedPort = null;

        foreach ($splitterPorts as $port) {
            if ((int)($port['id'] ?? 0) === $normalized->splitter_output_port_id) {
                $selectedPort = $port;
                break;
            }
        }

        if (!$selectedPort) {
            throw new Exception('Selected splitter output port does not belong to the selected splitter.');
        }

        if (strtoupper((string)($selectedPort['status'] ?? '')) !== 'AVAILABLE') {
            throw new Exception('Selected splitter output port is not AVAILABLE.');
        }

        $cvlanRow = $this->allocateCvlanForOlt($normalized->olt_id);

        $svlanRow = $this->resolveSvlanForOltPort(
            $normalized->olt_id,
            $normalized->olt_port_id
        );

        $mgmtVlanRow = $this->resolveMgmtVlanForOlt($normalized->olt_id);

        $profiles = $this->resolveOltProfiles(
            $normalized->olt_id,
            (int)($cvlanRow['vlan_id'] ?? 0)
        );

        return [
            'valid' => true,
            'subscriber_id' => $normalized->subscriber_id,
            'service_id' => $resolvedServiceId,
            'plan_id' => $normalized->plan_id,
            'ont_id' => $normalized->ont_id,
            'ont_serial' => $normalized->ont_serial,
            'olt_id' => $normalized->olt_id,
            'olt_port_id' => $normalized->olt_port_id,
            'olt_port_label' => $this->formatOltPortLabel($oltPort),
            'olt_port_occupancy' => sprintf('%d/%d', $effectiveCount, self::MAX_ONTS_PER_FSP),
            'network_box_id' => $normalized->network_box_id,
            'splitter_id' => $normalized->splitter_id,
            'splitter_output_port_id' => $normalized->splitter_output_port_id,
            'splitter_output_port_label' => 'Port ' . (int)($selectedPort['port_number'] ?? 0),
            'cvlan_network_vlan_id' => (int)($cvlanRow['id'] ?? 0),
            'cvlan' => (int)($cvlanRow['vlan_id'] ?? 0),
            'svlan_network_vlan_id' => (int)($svlanRow['id'] ?? 0),
            'svlan' => (int)($svlanRow['vlan_id'] ?? 0),
            'mgmt_vlan_id' => (int)($mgmtVlanRow['id'] ?? 0),
            'tr069_vlan' => (int)($mgmtVlanRow['mgmt_vlan'] ?? 0),
            'lineprofile_id' => (int)$profiles['lineprofile_id'],
            'srvprofile_id' => (int)$profiles['srvprofile_id'],
            'tr069_profile_id' => (int)$profiles['tr069_profile_id'],
            'internet_wan_profile_id' => (int)$profiles['internet_wan_profile_id'],
            'tr069_wan_profile_id' => (int)$profiles['tr069_wan_profile_id'],
            'profile_sources' => [
                'line_profile' => $profiles['line_profile'],
                'srv_profile' => $profiles['srv_profile'],
                'tr069_profile' => $profiles['tr069_profile'],
                'internet_wan_profile' => $profiles['internet_wan_profile'],
                'tr069_wan_profile' => $profiles['tr069_wan_profile'],
            ],
            'message' => 'Validation passed.',
        ];
    }

    public function createProvisioningJob($dto): array
    {
        $lockName = 'nexusbox:provisioning:allocation';
        $this->repo->acquireLock($lockName);

        try {
            return $this->repo->transaction(fn() => $this->createProvisioningJobAtomic($dto));
        } catch (Throwable $e) {
            $this->auditSafe('CREATE_FAILED', 'Provisioning job creation failed: ' . $e->getMessage());
            throw $e;
        } finally {
            $this->repo->releaseLock($lockName);
        }
    }

    private function createProvisioningJobAtomic($dto): array
    {
        $normalized = $this->normalizeDto($dto);
        $validation = $this->validateProvisioning($normalized);

        $service = $this->ensureSubscriberService(
            $normalized->subscriber_id,
            $normalized->plan_id,
            $normalized->service_id
        );

        $serviceId = (int)($service['id'] ?? $service['service_id'] ?? 0);

        if ($serviceId <= 0) {
            throw new Exception('Failed to resolve subscriber service.');
        }

        $existingBinding = $this->repo->findBindingByServiceId($serviceId);

        if ($existingBinding) {
            throw new Exception('This service already has a provisioning binding or reservation.');
        }

        $oltPort = $this->repo->getOltPort($normalized->olt_port_id);

        if (!$oltPort) {
            throw new Exception('OLT port not found.');
        }

        $pppoeServicePort = $this->repo->getNextServicePortId('PPPOE');
        $tr069ServicePort = $this->repo->getNextServicePortId('TR069');

        $requestPayload = array_merge($this->provisioningDtoToArray($normalized), [
            'service_id' => $serviceId,
            'plan_id' => $normalized->plan_id,
            'cvlan_network_vlan_id' => (int)($validation['cvlan_network_vlan_id'] ?? 0),
            'cvlan' => (int)($validation['cvlan'] ?? 0),
            'svlan_network_vlan_id' => (int)($validation['svlan_network_vlan_id'] ?? 0),
            'svlan' => (int)($validation['svlan'] ?? 0),
            'mgmt_vlan_id' => (int)($validation['mgmt_vlan_id'] ?? 0),
            'tr069_vlan' => (int)($validation['tr069_vlan'] ?? 0),
            'lineprofile_id' => (int)($validation['lineprofile_id'] ?? 0),
            'srvprofile_id' => (int)($validation['srvprofile_id'] ?? 0),
            'tr069_profile_id' => (int)($validation['tr069_profile_id'] ?? 0),
            'internet_wan_profile_id' => (int)($validation['internet_wan_profile_id'] ?? 0),
            'tr069_wan_profile_id' => (int)($validation['tr069_wan_profile_id'] ?? 0),
        ]);

        $jobId = $this->repo->createJob([
            'job_no' => $this->generateJobNo(),
            'subscriber_id' => $normalized->subscriber_id,
            'service_id' => $serviceId,
            'plan_id' => $normalized->plan_id,
            'ont_id' => $normalized->ont_id,
            'ont_serial' => $normalized->ont_serial,
            'olt_id' => $normalized->olt_id,
            'olt_port_id' => $normalized->olt_port_id,
            'network_box_id' => $normalized->network_box_id,
            'splitter_id' => $normalized->splitter_id,
            'splitter_output_port_id' => $normalized->splitter_output_port_id,
            'frame' => (int)($oltPort['frame'] ?? 0),
            'slot' => (int)($oltPort['slot'] ?? 0),
            'port' => (int)($oltPort['port'] ?? 0),
            'ont_assigned_id' => null,
            'global_id' => null,
            'cvlan' => (int)$validation['cvlan'],
            'svlan' => (int)$validation['svlan'],
            'pppoe_service_port' => $pppoeServicePort,
            'tr069_service_port' => $tr069ServicePort,
            'lineprofile_id' => (int)$validation['lineprofile_id'],
            'srvprofile_id' => (int)$validation['srvprofile_id'],
            'tr069_profile_id' => (int)$validation['tr069_profile_id'],
            'internet_wan_profile_id' => (int)$validation['internet_wan_profile_id'],
            'tr069_wan_profile_id' => (int)$validation['tr069_wan_profile_id'],
            'provision_mode' => $normalized->provision_mode,
            'job_status' => 'READY',
            'current_stage' => 'CREATED',
            'error_message' => null,
            'request_payload' => json_encode($requestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'result_payload' => null,
            'created_by' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        $this->repo->reserveSplitterOutputPort($normalized->splitter_output_port_id, $serviceId);
        $this->repo->upsertProvisioningBinding([
            'service_id' => $serviceId,
            'ont_id' => $normalized->ont_id,
            'ont_serial' => $normalized->ont_serial,
            'olt_id' => $normalized->olt_id,
            'olt_port_id' => $normalized->olt_port_id,
            'network_box_id' => $normalized->network_box_id,
            'splitter_id' => $normalized->splitter_id,
            'splitter_output_port_id' => $normalized->splitter_output_port_id,
            'parent_box_id' => null,
            'cvlan_network_vlan_id' => (int)$validation['cvlan_network_vlan_id'],
            'cvlan' => (int)$validation['cvlan'],
            'svlan' => (int)$validation['svlan'],
            'ont_assigned_id' => null,
            'global_id' => null,
            'pppoe_service_port' => $pppoeServicePort,
            'tr069_service_port' => $tr069ServicePort,
            'lineprofile_id' => (int)$validation['lineprofile_id'],
            'srvprofile_id' => (int)$validation['srvprofile_id'],
            'tr069_profile_id' => (int)$validation['tr069_profile_id'],
            'internet_wan_profile_id' => (int)$validation['internet_wan_profile_id'],
            'tr069_wan_profile_id' => (int)$validation['tr069_wan_profile_id'],
            'assigned_at' => null,
            'installed_at' => null,
            'activated_at' => null,
        ]);

        $this->repo->addJobLog([
            'job_id' => $jobId,
            'stage' => 'CREATE',
            'action' => 'CREATE_JOB',
            'status' => 'SUCCESS',
            'message' => sprintf(
                'Provisioning job created. Service=%d Plan=%d CVLAN=%d SVLAN=%d TR069-VLAN=%d.',
                $serviceId,
                $normalized->plan_id,
                (int)$validation['cvlan'],
                (int)$validation['svlan'],
                (int)$validation['tr069_vlan']
            ),
            'payload_json' => json_encode($validation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => null,
        ]);

        $this->audit->log(
            'PROVISIONING',
            'CREATE_JOB',
            sprintf(
                'Created provisioning job %d for subscriber %d, service %d, ONT %s, CVLAN %d, SVLAN %d, PPPoE service-port %d, TR069 service-port %d',
                $jobId,
                $normalized->subscriber_id,
                $serviceId,
                $normalized->ont_serial,
                (int)$validation['cvlan'],
                (int)$validation['svlan'],
                $pppoeServicePort,
                $tr069ServicePort
            )
        );

        return [
            'job_id' => $jobId,
            'service_id' => $serviceId,
            'plan_id' => $normalized->plan_id,
            'job' => $this->getJobDetails($jobId),
            'cvlan' => (int)$validation['cvlan'],
            'svlan' => (int)$validation['svlan'],
            'tr069_vlan' => (int)$validation['tr069_vlan'],
            'pppoe_service_port' => $pppoeServicePort,
            'tr069_service_port' => $tr069ServicePort,
            'message' => 'Provisioning job created successfully.',
        ];
    }

    public function runProvisioningJob(int $jobId, bool $isRetry = false): array
    {
        if ($jobId <= 0) {
            throw new Exception('Invalid job ID.');
        }

        $job = $this->repo->findJobById($jobId);

        if (!$job) {
            throw new Exception('Provisioning job not found.');
        }

        $jobStatus = strtoupper((string)($job['job_status'] ?? ''));
        $currentStage = strtoupper((string)($job['current_stage'] ?? ''));
        $canRun = $isRetry
            ? ($jobStatus === 'FAILED' && $currentStage === 'OLT_PROVISIONING')
            : ($jobStatus === 'READY' && $currentStage === 'CREATED');

        if (!$canRun) {
            throw new Exception('The provisioning job is not in a valid stage for OLT execution.');
        }

        $requestPayload = $this->decodeJsonField($job['request_payload'] ?? null) ?: [];

        $this->repo->updateJobExecutionState(
            $jobId,
            'PROVISIONING',
            'OLT_PROVISIONING',
            null,
            true
        );

        $pythonPayload = $this->buildPythonPayloadFromJob($job);
        $script = $this->provisioningScriptPath();
        $ponLock = sprintf(
            'nexusbox:provisioning:pon:%d:%d:%d:%d',
            (int)($job['olt_id'] ?? 0),
            (int)($job['frame'] ?? 0),
            (int)($job['slot'] ?? 0),
            (int)($job['port'] ?? 0)
        );
        $this->repo->acquireLock($ponLock, 30);
        try {
            $execution = $this->networkRunner->runPythonJson($script, $pythonPayload, 120);
        } finally {
            $this->repo->releaseLock($ponLock);
        }
        $exitCode = $execution->exitCode;
        $resultText = $execution->stdout !== '' ? $execution->stdout : $execution->stderr;
        $decoded = json_decode($resultText, true);
        $safeOutput = $this->networkRunner->redact($resultText, [
            $pythonPayload['password'] ?? '',
        ]);

        $this->repo->addJobLog([
            'job_id' => $jobId,
            'stage' => 'RUN',
            'action' => 'EXECUTE_PYTHON',
            'status' => $exitCode === 0 ? 'SUCCESS' : 'FAILED',
            'message' => $exitCode === 0
                ? 'OLT provisioning script executed successfully.'
                : 'OLT provisioning script failed.',
            'payload_json' => json_encode([
                'operation' => 'ztp_provision_ont',
                'exit_code' => $exitCode,
                'duration_ms' => $execution->durationMs,
                'timed_out' => $execution->timedOut,
                'output' => $safeOutput,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => null,
        ]);

        if ($exitCode !== 0) {
            $this->repo->updateJobExecutionState(
                $jobId,
                'FAILED',
                'OLT_PROVISIONING',
                $safeOutput ?: 'OLT provisioning failed.',
                false,
                true
            );

            $this->auditSafe('OLT_PROVISIONING_FAILED', sprintf('Provisioning job %d failed: %s', $jobId, $safeOutput ?: 'OLT provisioning failed.'));
            throw new Exception($safeOutput ?: 'OLT provisioning failed.');
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            $this->repo->updateJobExecutionState(
                $jobId,
                'FAILED',
                'OLT_PROVISIONING',
                $decoded['message'] ?? 'Invalid provisioning response.',
                false,
                true,
                $safeOutput
            );

            $failureMessage = $decoded['message'] ?? 'Invalid provisioning response.';
            $this->auditSafe('OLT_PROVISIONING_FAILED', sprintf('Provisioning job %d failed: %s', $jobId, $failureMessage));
            throw new Exception($failureMessage);
        }

        $responseData = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

        $ontAssignedId = isset($responseData['ont_id'])
            ? (int)$responseData['ont_id']
            : null;

        $globalId = isset($responseData['global_id'])
            ? (int)$responseData['global_id']
            : null;

        $pppoeServicePort = isset($responseData['internet_service_port'])
            ? (int)$responseData['internet_service_port']
            : (int)($job['pppoe_service_port'] ?? 0);

        $tr069ServicePort = isset($responseData['tr069_service_port'])
            ? (int)$responseData['tr069_service_port']
            : (int)($job['tr069_service_port'] ?? 0);

        $this->repo->updateJobProvisioningResult(
            $jobId,
            $ontAssignedId,
            $pppoeServicePort > 0 ? $pppoeServicePort : null,
            $tr069ServicePort > 0 ? $tr069ServicePort : null,
            $globalId
        );

        $networkVlanId = (int)($requestPayload['cvlan_network_vlan_id'] ?? 0);

        $this->repo->upsertProvisioningBinding([
            'service_id' => (int)($job['service_id'] ?? 0),
            'ont_id' => (int)($job['ont_id'] ?? 0),
            'ont_serial' => (string)($job['ont_serial'] ?? ''),
            'olt_id' => (int)($job['olt_id'] ?? 0),
            'olt_port_id' => (int)($job['olt_port_id'] ?? 0),
            'network_box_id' => (int)($job['network_box_id'] ?? 0),
            'splitter_id' => (int)($job['splitter_id'] ?? 0),
            'splitter_output_port_id' => (int)($job['splitter_output_port_id'] ?? 0),
            'parent_box_id' => null,
            'cvlan_network_vlan_id' => $networkVlanId > 0 ? $networkVlanId : null,
            'cvlan' => (int)($job['cvlan'] ?? $requestPayload['cvlan'] ?? 0),
            'svlan' => (int)($job['svlan'] ?? $requestPayload['svlan'] ?? 0),
            'ont_assigned_id' => $ontAssignedId,
            'global_id' => $globalId,
            'pppoe_service_port' => $pppoeServicePort > 0 ? $pppoeServicePort : null,
            'tr069_service_port' => $tr069ServicePort > 0 ? $tr069ServicePort : null,
            'lineprofile_id' => (int)($job['lineprofile_id'] ?? $requestPayload['lineprofile_id'] ?? 0),
            'srvprofile_id' => (int)($job['srvprofile_id'] ?? $requestPayload['srvprofile_id'] ?? 0),
            'tr069_profile_id' => (int)($job['tr069_profile_id'] ?? $requestPayload['tr069_profile_id'] ?? 0),
            'internet_wan_profile_id' => (int)($job['internet_wan_profile_id'] ?? $requestPayload['internet_wan_profile_id'] ?? 0),
            'tr069_wan_profile_id' => (int)($job['tr069_wan_profile_id'] ?? $requestPayload['tr069_wan_profile_id'] ?? 0),
            'assigned_at' => date('Y-m-d H:i:s'),
            'installed_at' => null,
            'activated_at' => null,
        ]);

        $splitterOutputPortId = (int)($job['splitter_output_port_id'] ?? 0);

        if ($splitterOutputPortId > 0) {
            $this->repo->markSplitterOutputPortUsed(
                $splitterOutputPortId,
                (int)($job['service_id'] ?? 0),
                'Provisioned'
            );
        }

        $this->repo->updateJobExecutionState(
            $jobId,
            'VERIFYING',
            'WAITING_FOR_ACS',
            null,
            false,
            false,
            json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->audit->log(
            'PROVISIONING',
            'OLT_PROVISIONED',
            sprintf(
                'OLT provisioning completed for job %d, ONT %s, assigned ONT ID %s, PPPoE service-port %d, TR069 service-port %d',
                $jobId,
                (string)($job['ont_serial'] ?? ''),
                $ontAssignedId !== null ? (string)$ontAssignedId : 'N/A',
                $pppoeServicePort,
                $tr069ServicePort
            )
        );

        return [
            'job_id' => $jobId,
            'status' => 'VERIFYING',
            'current_stage' => 'WAITING_FOR_ACS',
            'cvlan' => (int)($job['cvlan'] ?? $requestPayload['cvlan'] ?? 0),
            'svlan' => (int)($job['svlan'] ?? $requestPayload['svlan'] ?? 0),
            'tr069_vlan' => (int)($requestPayload['tr069_vlan'] ?? 0),
            'ont_assigned_id' => $ontAssignedId,
            'pppoe_service_port' => $pppoeServicePort,
            'tr069_service_port' => $tr069ServicePort,
            'output' => $decoded,
            'message' => 'OLT provisioning completed. Waiting for ACS appearance.',
        ];
    }

    public function checkAcs(int $jobId): array
    {
        if ($jobId <= 0) {
            throw new Exception('Invalid job ID.');
        }

        $job = $this->repo->findJobById($jobId);

        if (!$job) {
            throw new Exception('Provisioning job not found.');
        }

        $jobStatus = strtoupper((string)($job['job_status'] ?? ''));
        $currentStage = strtoupper((string)($job['current_stage'] ?? ''));

        if ($jobStatus === 'SUCCESS' && $currentStage === 'COMPLETE') {
            $resultPayload = $this->decodeJsonField($job['result_payload'] ?? null) ?: [];

            $billingResult = is_array($resultPayload['billing'] ?? null)
                ? $resultPayload['billing']
                : [
                    'created' => false,
                    'service_id' => (int)($job['service_id'] ?? 0),
                    'reason' => 'Provisioning job is already complete. Billing automation was not re-run.',
                ];

            return [
                'job_id' => $jobId,
                'acs_found' => true,
                'acs_device' => $resultPayload['acs'] ?? null,
                'acs_push' => $resultPayload['acs_push'] ?? null,
                'ppp_username' => $resultPayload['ppp_username'] ?? ($job['ppp_username'] ?? null),
                'billing' => $billingResult,
                'status' => 'SUCCESS',
                'current_stage' => 'COMPLETE',
                'already_completed' => true,
                'message' => 'Provisioning job is already complete. ACS push and billing automation were not re-run.',
            ];
        }

        if ($jobStatus !== 'VERIFYING' || !in_array($currentStage, ['WAITING_FOR_ACS', 'ACS_PUSH_FAILED'], true)) {
            throw new Exception('ACS verification is only allowed after successful OLT provisioning.');
        }

        $serial = strtoupper(trim((string)($job['ont_serial'] ?? '')));

        if ($serial === '') {
            throw new Exception('ONT serial is missing in the provisioning job.');
        }

        $acsDevice = $this->repo->findAcsOntBySerial($serial);

        if (!$acsDevice) {
            $acsDevice = $this->acsService->findDeviceBySerial($serial);
        }

        if (!$acsDevice) {
            $this->repo->addJobLog([
                'job_id' => $jobId,
                'stage' => 'ACS',
                'action' => 'CHECK_ACS',
                'status' => 'WARNING',
                'message' => 'ONT not yet visible in ACS.',
                'payload_json' => json_encode(['serial' => $serial], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_by' => null,
            ]);

            $this->auditSafe('ACS_WAITING', sprintf('Provisioning job %d is waiting for ONT %s to appear in ACS.', $jobId, $serial));

            return [
                'job_id' => $jobId,
                'acs_found' => false,
                'status' => (string)($job['job_status'] ?? 'VERIFYING'),
                'current_stage' => (string)($job['current_stage'] ?? 'WAITING_FOR_ACS'),
                'message' => 'ONT not yet visible in ACS.',
            ];
        }

        $this->repo->addJobLog([
            'job_id' => $jobId,
            'stage' => 'ACS',
            'action' => 'CHECK_ACS',
            'status' => 'SUCCESS',
            'message' => 'ONT found in ACS.',
            'payload_json' => json_encode($acsDevice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => null,
        ]);

        $deviceId = trim((string)($acsDevice['device_id'] ?? $acsDevice['id'] ?? ''));

        if ($deviceId === '') {
            throw new Exception('ACS device ID is missing.');
        }

        $serviceId = (int)($job['service_id'] ?? 0);

        if ($serviceId <= 0) {
            throw new Exception('Subscriber service is missing from provisioning job.');
        }

        $service = $this->repo->getServiceById($serviceId);

        if (!$service) {
            throw new Exception('Subscriber service record not found.');
        }

        $pppUsername = trim((string)($service['ppp_username'] ?? $job['ppp_username'] ?? ''));
        $pppPassword = trim((string)($service['ppp_password'] ?? ''));

        if ($pppUsername === '' || $pppPassword === '') {
            throw new Exception('PPP username/password is missing from subscriber service.');
        }

        try {
            $acsPush = $this->acsService->setPppCredentials(
                $deviceId,
                $pppUsername,
                $pppPassword
            );

            $this->repo->addJobLog([
                'job_id' => $jobId,
                'stage' => 'ACS_PUSH',
                'action' => 'SET_PPP_CREDENTIALS',
                'status' => 'SUCCESS',
                'message' => 'PPP credentials pushed to ACS successfully.',
                'payload_json' => json_encode([
                    'device_id' => $deviceId,
                    'serial' => $serial,
                    'ppp_username' => $pppUsername,
                    'acs_push' => $acsPush,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_by' => null,
            ]);
        } catch (Throwable $e) {
            $this->repo->addJobLog([
                'job_id' => $jobId,
                'stage' => 'ACS_PUSH',
                'action' => 'SET_PPP_CREDENTIALS',
                'status' => 'FAILED',
                'message' => $e->getMessage(),
                'payload_json' => json_encode([
                    'device_id' => $deviceId,
                    'serial' => $serial,
                    'ppp_username' => $pppUsername,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_by' => null,
            ]);

            $this->repo->updateJobExecutionState(
                $jobId,
                'VERIFYING',
                'ACS_PUSH_FAILED',
                $e->getMessage(),
                false,
                false
            );

            $this->auditSafe('ACS_PUSH_FAILED', sprintf('Provisioning job %d ACS credential push failed: %s', $jobId, $e->getMessage()));

            throw new Exception('ACS PPP credential push failed: ' . $e->getMessage());
        }

        $activatedAt = date('Y-m-d H:i:s');

        $this->repo->transaction(function () use ($serviceId, $activatedAt, $job): void {
            $this->repo->activateProvisioningBinding($serviceId, $activatedAt);
            $this->repo->updateServiceStatus($serviceId, 'ACTIVE');
            $this->repo->assignOntToSubscriber(
                (int)($job['ont_id'] ?? 0),
                (int)($job['subscriber_id'] ?? 0)
            );
        });

        $billingResult = [
            'created' => false,
            'reason' => 'Billing automation did not run.',
            'service_id' => $serviceId,
            'activated_at' => $activatedAt,
        ];

        try {
            $billingResult = $this->billingAutomation->createInvoiceAfterProvisioning(
                $serviceId,
                $activatedAt
            );

            $this->repo->addJobLog([
                'job_id' => $jobId,
                'stage' => 'BILLING',
                'action' => 'AUTO_CREATE_INVOICE',
                'status' => !empty($billingResult['created']) ? 'SUCCESS' : 'WARNING',
                'message' => !empty($billingResult['created'])
                    ? 'Initial invoice auto-created after provisioning.'
                    : 'Initial invoice was not created automatically.',
                'payload_json' => json_encode($billingResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_by' => null,
            ]);
        } catch (Throwable $e) {
            $billingResult = [
                'created' => false,
                'service_id' => $serviceId,
                'activated_at' => $activatedAt,
                'error' => $e->getMessage(),
            ];

            $this->repo->addJobLog([
                'job_id' => $jobId,
                'stage' => 'BILLING',
                'action' => 'AUTO_CREATE_INVOICE',
                'status' => 'WARNING',
                'message' => 'Service activated, but automatic invoice creation failed: ' . $e->getMessage(),
                'payload_json' => json_encode($billingResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_by' => null,
            ]);
            $this->auditSafe('BILLING_WARNING', sprintf('Provisioning job %d activated service %d but billing failed: %s', $jobId, $serviceId, $e->getMessage()));
        }

        $workOrderResult = [
            'created' => false,
            'reason' => 'Installation work order generation did not run.',
        ];
        try {
            $workOrderResult = $this->workOrders->createFromProvisioning($job);
            $this->repo->addJobLog([
                'job_id' => $jobId,
                'stage' => 'WORK_ORDER',
                'action' => 'AUTO_CREATE_INSTALLATION_WORK_ORDER',
                'status' => 'SUCCESS',
                'message' => !empty($workOrderResult['created'])
                    ? 'Installation confirmation work order created.'
                    : 'Existing installation confirmation work order reused.',
                'payload_json' => json_encode($workOrderResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_by' => null,
            ]);
        } catch (Throwable $e) {
            $workOrderResult = [
                'created' => false,
                'error' => $e->getMessage(),
            ];
            $this->repo->addJobLog([
                'job_id' => $jobId,
                'stage' => 'WORK_ORDER',
                'action' => 'AUTO_CREATE_INSTALLATION_WORK_ORDER',
                'status' => 'WARNING',
                'message' => 'Service activated, but installation work order creation failed: ' . $e->getMessage(),
                'payload_json' => json_encode($workOrderResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_by' => null,
            ]);
            $this->auditSafe('WORK_ORDER_WARNING', sprintf('Provisioning job %d completed but installation work order creation failed: %s', $jobId, $e->getMessage()));
        }

        $resultPayload = json_encode([
            'acs' => $acsDevice,
            'acs_push' => $acsPush,
            'ppp_username' => $pppUsername,
            'activated_at' => $activatedAt,
            'billing' => $billingResult,
            'installation_work_order' => $workOrderResult,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->repo->updateJobExecutionState(
            $jobId,
            'SUCCESS',
            'COMPLETE',
            null,
            false,
            true,
            $resultPayload
        );

        $this->audit->log(
            'PROVISIONING',
            'ACTIVATE_SERVICE',
            sprintf(
                'Service activated for provisioning job %d, ONT %s, PPP username %s',
                $jobId,
                $serial,
                $pppUsername
            )
        );

        return [
            'job_id' => $jobId,
            'acs_found' => true,
            'acs_device' => $acsDevice,
            'acs_push' => $acsPush,
            'ppp_username' => $pppUsername,
            'billing' => $billingResult,
            'installation_work_order' => $workOrderResult,
            'status' => 'SUCCESS',
            'current_stage' => 'COMPLETE',
            'message' => !empty($billingResult['created'])
                ? 'ACS found. PPP credentials pushed. Service is now active. Initial invoice was created.'
                : 'ACS found. PPP credentials pushed. Service is now active. Initial invoice was not created automatically.',
        ];
    }

    public function getJobLogs(int $jobId): array
    {
        if ($jobId <= 0) {
            throw new Exception('Invalid job ID.');
        }

        return $this->repo->getJobLogs($jobId);
    }

    public function provision($dto): array
    {
        $job = $this->createProvisioningJob($dto);
        $jobId = (int)($job['job_id'] ?? 0);

        if ($jobId <= 0) {
            throw new Exception('Failed to create provisioning job.');
        }

        $run = $this->runProvisioningJob($jobId);

        return [
            'job_id' => $jobId,
            'created' => $job,
            'run' => $run,
            'message' => 'Provisioning started successfully.',
        ];
    }

    public function retryJob(int $jobId): array
    {
        $job = $this->repo->findJobById($jobId);

        if (!$job) {
            throw new Exception('Provisioning job not found.');
        }

        $status = strtoupper((string)($job['job_status'] ?? ''));
        $stage = strtoupper((string)($job['current_stage'] ?? ''));

        if ($status === 'FAILED' && $stage === 'OLT_PROVISIONING') {
            $this->auditSafe('RETRY_OLT', sprintf('Retrying OLT provisioning for job %d.', $jobId));
            return $this->runProvisioningJob($jobId, true);
        }

        if ($status === 'VERIFYING' && in_array($stage, ['WAITING_FOR_ACS', 'ACS_PUSH_FAILED'], true)) {
            $this->auditSafe('RETRY_ACS', sprintf('Retrying ACS verification for job %d.', $jobId));
            return $this->checkAcs($jobId);
        }

        throw new Exception('This job is not in a retryable stage.');
    }

    public function cancelJob(int $jobId): array
    {
        $job = $this->repo->findJobById($jobId);

        if (!$job) {
            throw new Exception('Provisioning job not found.');
        }

        $status = strtoupper((string)($job['job_status'] ?? ''));

        $stage = strtoupper((string)($job['current_stage'] ?? ''));
        if ($status !== 'READY' || $stage !== 'CREATED') {
            throw new Exception('Only jobs that have not started OLT provisioning can be cancelled safely.');
        }

        $this->repo->transaction(function () use ($jobId, $job): void {
            $this->repo->releaseSplitterOutputPortReservation(
                (int)($job['splitter_output_port_id'] ?? 0),
                (int)($job['service_id'] ?? 0)
            );
            $this->repo->deleteUnactivatedBinding((int)($job['service_id'] ?? 0));
            $this->repo->updateJobExecutionState(
                $jobId,
                'CANCELLED',
                'CANCELLED',
                'Provisioning cancelled by user.',
                false,
                true
            );
        });

        $this->repo->addJobLog([
            'job_id' => $jobId,
            'stage' => 'CANCEL',
            'action' => 'CANCEL_JOB',
            'status' => 'WARNING',
            'message' => 'Provisioning job cancelled.',
            'payload_json' => null,
            'created_by' => null,
        ]);

        $this->audit->log(
            'PROVISIONING',
            'CANCEL_JOB',
            sprintf('Cancelled provisioning job %d', $jobId)
        );

        return [
            'job_id' => $jobId,
            'status' => 'CANCELLED',
            'message' => 'Provisioning job cancelled successfully.',
        ];
    }

    private function buildPythonPayloadFromJob(array $job): array
    {
        $olt = $this->repo->getOltById((int)($job['olt_id'] ?? 0));
        $oltPort = $this->repo->getOltPort((int)($job['olt_port_id'] ?? 0));
        $ont = $this->repo->getOntById((int)($job['ont_id'] ?? 0));
        $requestPayload = $this->decodeJsonField($job['request_payload'] ?? null) ?: [];

        if (!$olt) {
            throw new Exception('OLT details not found.');
        }

        if (!$oltPort) {
            throw new Exception('OLT port details not found.');
        }

        if (!$ont) {
            throw new Exception('ONT inventory details not found.');
        }

        $host = trim((string)($olt['ip_address'] ?? ''));
        $username = trim((string)($olt['username'] ?? ''));
        $password = trim((string)($olt['password'] ?? ''));

        if ($host === '' || $username === '' || $password === '') {
            throw new Exception('OLT connection details are incomplete.');
        }

        $serial = strtoupper(trim((string)($job['ont_serial'] ?? $ont['serial_number'] ?? '')));

        if ($serial === '') {
            throw new Exception('ONT serial is missing.');
        }

        $svlan = (int)($job['svlan'] ?? $requestPayload['svlan'] ?? 0);
        $cvlan = (int)($job['cvlan'] ?? $requestPayload['cvlan'] ?? 0);
        $tr069Vlan = (int)($requestPayload['tr069_vlan'] ?? 0);

        $lineProfileId = (int)($job['lineprofile_id'] ?? $requestPayload['lineprofile_id'] ?? 0);
        $srvProfileId = (int)($job['srvprofile_id'] ?? $requestPayload['srvprofile_id'] ?? 0);
        $tr069ProfileId = (int)($job['tr069_profile_id'] ?? $requestPayload['tr069_profile_id'] ?? 0);
        $internetWanProfileId = (int)($job['internet_wan_profile_id'] ?? $requestPayload['internet_wan_profile_id'] ?? 0);
        $tr069WanProfileId = (int)($job['tr069_wan_profile_id'] ?? $requestPayload['tr069_wan_profile_id'] ?? 0);

        $internetServicePort = (int)($job['pppoe_service_port'] ?? 0);
        $tr069ServicePort = (int)($job['tr069_service_port'] ?? 0);

        if ($svlan <= 0 || $cvlan <= 0 || $tr069Vlan <= 0) {
            throw new Exception('VLAN information is incomplete in provisioning job.');
        }

        if (
            $lineProfileId <= 0 ||
            $srvProfileId <= 0 ||
            $tr069ProfileId <= 0 ||
            $internetWanProfileId <= 0 ||
            $tr069WanProfileId <= 0
        ) {
            throw new Exception('OLT profile information is incomplete in provisioning job.');
        }

        if ($internetServicePort <= 0 || $tr069ServicePort <= 0) {
            throw new Exception('Service port IDs are missing on the provisioning job.');
        }

        return [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'ssh_port' => 22,
            'frame' => (int)($job['frame'] ?? $oltPort['frame'] ?? 0),
            'slot' => (int)($job['slot'] ?? $oltPort['slot'] ?? 0),
            'port' => (int)($job['port'] ?? $oltPort['port'] ?? 0),
            'sn' => $serial,
            'svlan' => $svlan,
            'cvlan' => $cvlan,
            'tr069_vlan' => $tr069Vlan,
            'lineprofile_id' => $lineProfileId,
            'srvprofile_id' => $srvProfileId,
            'tr069_profile_id' => $tr069ProfileId,
            'internet_wan_profile_id' => $internetWanProfileId,
            'tr069_wan_profile_id' => $tr069WanProfileId,
            'internet_service_port' => $internetServicePort,
            'tr069_service_port' => $tr069ServicePort,
        ];
    }

    private function provisioningScriptPath(): string
    {
        $script = BASE_PATH . '/app/Modules/ServiceProvisioning/Scripts/ztp_provision_ont.py';

        if (!file_exists($script)) {
            throw new Exception('Provisioning script not found: ' . $script);
        }

        return $script;
    }

    private function ensureSubscriberService(
        int $subscriberId,
        int $planId,
        int $serviceId = 0
    ): array {
        if ($serviceId > 0) {
            $service = $this->repo->getServiceById($serviceId);

            if (!$service) {
                throw new Exception('Selected subscriber service not found.');
            }

            return $service;
        }

        $existing = $this->repo->findSubscriberServiceBySubscriberAndPlan(
            $subscriberId,
            $planId
        );

        if ($existing) {
            return $existing;
        }

        $plan = $this->repo->getPlanById($planId);

        if (!$plan) {
            throw new Exception('Plan not found.');
        }

        $createdServiceId = $this->repo->createSubscriberServiceForProvisioning(
            $subscriberId,
            $planId,
            $plan
        );

        $createdService = $this->repo->getServiceById($createdServiceId);
        if (!$createdService) {
            throw new Exception('Subscriber service was created but could not be reloaded.');
        }

        return $createdService;
    }

    private function auditSafe(string $action, string $message): void
    {
        try {
            $this->audit->log('PROVISIONING', $action, $message);
        } catch (Throwable $auditError) {
            error_log(sprintf(
                '[ServiceProvisioning] Audit failure for %s: %s',
                $action,
                $auditError->getMessage()
            ));
        }
    }

    private function validateRequiredPayload(CreateProvisioningDTO $dto): void
    {
        $errors = $this->validator->validate($this->provisioningDtoToArray($dto));
        if ($errors === []) return;
        $messages = (array)reset($errors);
        throw new Exception((string)reset($messages));
    }

    private function resolveSvlanForOltPort(int $oltId, int $oltPortId): array
    {
        if ($oltId <= 0 || $oltPortId <= 0) {
            throw new Exception('OLT and OLT port are required before resolving S-VLAN.');
        }

        $svlan = $this->repo->findDeployedSvlanForOltPort($oltId, $oltPortId);

        if (!$svlan) {
            throw new Exception('No deployed S-VLAN found for the selected OLT PON port.');
        }

        return $svlan;
    }

    private function resolveMgmtVlanForOlt(int $oltId): array
    {
        if ($oltId <= 0) {
            throw new Exception('OLT is required before resolving MGMT-VLAN.');
        }

        $mgmt = $this->repo->findMgmtVlanForOlt($oltId);

        if (!$mgmt) {
            throw new Exception('No MGMT-VLAN found for the selected OLT.');
        }

        return $mgmt;
    }

    private function resolveOltProfiles(int $oltId, int $cvlan): array
    {
        $lineProfile = $this->repo->findLineProfileByCvlan($oltId, $cvlan);

        if (!$lineProfile) {
            throw new Exception("No ONT line profile found for C-VLAN {$cvlan} on selected OLT.");
        }

        $srvProfile = $this->repo->findDefaultSrvProfile($oltId);

        if (!$srvProfile) {
            throw new Exception('No ONT service profile found for selected OLT.');
        }

        $tr069Profile = $this->repo->findDefaultTr069Profile($oltId);

        if (!$tr069Profile) {
            throw new Exception('No TR069 profile found for selected OLT.');
        }

        $internetWanProfile = $this->repo->findWanProfileByType($oltId, 'PPPOE');

        if (!$internetWanProfile) {
            throw new Exception('No PPPoE WAN profile found for selected OLT.');
        }

        $tr069WanProfile = $this->repo->findWanProfileByType($oltId, 'DHCP');

        if (!$tr069WanProfile) {
            throw new Exception('No TR069 DHCP WAN profile found for selected OLT.');
        }

        return [
            'line_profile' => $lineProfile,
            'srv_profile' => $srvProfile,
            'tr069_profile' => $tr069Profile,
            'internet_wan_profile' => $internetWanProfile,
            'tr069_wan_profile' => $tr069WanProfile,
            'lineprofile_id' => (int)$lineProfile['profile_id'],
            'srvprofile_id' => (int)$srvProfile['profile_id'],
            'tr069_profile_id' => (int)$tr069Profile['profile_id'],
            'internet_wan_profile_id' => (int)$internetWanProfile['profile_id'],
            'tr069_wan_profile_id' => (int)$tr069WanProfile['profile_id'],
        ];
    }

    private function allocateCvlanForOlt(int $oltId): array
    {
        if ($oltId <= 0) {
            throw new Exception('OLT is required before allocating C-VLAN.');
        }

        $cvlan = $this->repo->findAvailableCvlanForOlt($oltId);

        if (!$cvlan) {
            throw new Exception(
                'No unallocated deployed C-VLAN is available for the selected OLT. '
                . 'Deploy an unused C-VLAN in VLAN Management, then retry provisioning.'
            );
        }

        return $cvlan;
    }

    private function provisioningDtoToArray(CreateProvisioningDTO $dto): array
    {
        return [
            'subscriber_id' => $dto->subscriber_id,
            'service_id' => $dto->service_id,
            'plan_id' => $dto->plan_id,
            'ont_id' => $dto->ont_id,
            'ont_serial' => $dto->ont_serial,
            'olt_id' => $dto->olt_id,
            'olt_port_id' => $dto->olt_port_id,
            'network_box_id' => $dto->network_box_id,
            'splitter_id' => $dto->splitter_id,
            'splitter_output_port_id' => $dto->splitter_output_port_id,
            'provision_mode' => $dto->provision_mode,
        ];
    }

    private function normalizeDto($dto): CreateProvisioningDTO
    {
        if ($dto instanceof CreateProvisioningDTO) {
            return $dto;
        }

        if (is_array($dto)) {
            return new CreateProvisioningDTO($dto);
        }

        throw new Exception('Invalid provisioning payload.');
    }

    private function generateJobNo(): string
    {
        return 'SPJ-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function formatOltPortLabel(array $oltPort): string
    {
        return implode('/', [
            (int)($oltPort['frame'] ?? 0),
            (int)($oltPort['slot'] ?? 0),
            (int)($oltPort['port'] ?? 0),
        ]);
    }

    private function decodeJsonField($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
