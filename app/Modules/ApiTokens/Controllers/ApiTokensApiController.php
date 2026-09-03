<?php

namespace App\Modules\ApiTokens\Controllers;

use App\Modules\ApiTokens\DTOs\CreateApiTokensDTO;
use App\Modules\ApiTokens\DTOs\MonitoringSettingsDTO;
use App\Modules\ApiTokens\Services\ApiTokensService;
use App\Modules\ApiTokens\Validators\CreateApiTokensValidator;
use App\Modules\ApiTokens\Validators\MonitoringSettingsValidator;
use Framework\ApiController;
use Framework\SessionManager;
use RuntimeException;
use Throwable;

final class ApiTokensApiController extends ApiController
{
    public function __construct(
        private ApiTokensService $service,
        private CreateApiTokensValidator $validator,
        private MonitoringSettingsValidator $monitoringValidator
    ) {
    }

    public function create(): void
    {
        try {
            $dto = new CreateApiTokensDTO(
                (int)(SessionManager::id() ?? 0),
                $this->request()->value('expires_at'),
                (string)$this->request()->value('name', ''),
                (string)$this->request()->value('description', ''),
                (string)$this->request()->value('purpose', 'CUSTOM'),
                is_array($this->request()->value('scopes', [])) ? $this->request()->value('scopes', []) : [],
                (string)$this->request()->value('transport_policy', 'HTTPS')
            );
            $errors = $this->validator->validate($dto);
            if ($errors !== []) {
                throw new RuntimeException(implode(' ', $errors));
            }

            $this->success(
                $this->service->create($dto),
                'API token created. Copy it now; it will not be shown again.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function revoke($id): void
    {
        try {
            $this->service->revoke((int)$id, (int)(SessionManager::id() ?? 0));
            $this->success([], 'API token revoked.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function updateMonitoringIdentity(): void
    {
        try { $this->success($this->service->updateMonitoringIdentity($this->request()->all()), 'Monitoring identity saved.'); }
        catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function monitoringOperations(): void
    {
        try { $this->success($this->service->monitoringOperations(), 'Infrastructure monitoring operations retrieved.'); }
        catch (Throwable $e) { $this->error($e->getMessage(), 500); }
    }

    public function updateMonitoringSettings(): void
    {
        try {
            $input = $this->request()->all();
            $dto = new MonitoringSettingsDTO(
                filter_var($input['monitoring_hq_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                trim((string) ($input['monitoring_hq_url'] ?? '')),
                filter_var($input['monitoring_allow_insecure_http'] ?? false, FILTER_VALIDATE_BOOLEAN),
                filter_var($input['monitoring_verify_tls'] ?? true, FILTER_VALIDATE_BOOLEAN),
                (int) ($input['monitoring_retention_days'] ?? 30),
                (int) ($input['monitoring_disk_warning_percent'] ?? 85),
                (int) ($input['monitoring_disk_critical_percent'] ?? 95),
                (int) ($input['monitoring_memory_warning_percent'] ?? 90),
                (int) ($input['monitoring_heartbeat_seconds'] ?? 60),
            );
            $errors = $this->monitoringValidator->validate($dto);
            if ($errors !== []) throw new RuntimeException(implode(' ', $errors));
            $this->success($this->service->updateMonitoringSettings($dto), 'Monitoring settings saved.');
        } catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function testMonitoringConnectivity(): void
    {
        try { $this->success($this->service->testMonitoringConnectivity(), 'HQ connectivity test completed without transmitting a snapshot.'); }
        catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function retryMonitoringDeliveries(): void
    {
        try { $this->success($this->service->retryMonitoringDeliveries(), 'Failed monitoring deliveries queued for retry.'); }
        catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }
}
