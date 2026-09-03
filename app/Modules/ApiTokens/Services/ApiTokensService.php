<?php

namespace App\Modules\ApiTokens\Services;

use App\Modules\ApiTokens\DTOs\CreateApiTokensDTO;
use App\Modules\ApiTokens\DTOs\MonitoringSettingsDTO;
use App\Modules\ApiTokens\Entities\ApiTokens;
use App\Modules\ApiTokens\Repositories\ApiTokensRepository;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Api\v1\Repositories\MonitoringRepository;
use App\Modules\Api\v1\Services\MonitoringDeliveryService;
use RuntimeException;

final class ApiTokensService
{
    public function __construct(
        private ApiTokensRepository $repository,
        private AuditService $audit,
        private MonitoringRepository $monitoringRepository,
        private MonitoringDeliveryService $delivery
    )
    {
    }

    public function forUser(int $userId): array
    {
        if ($userId <= 0) return [];
        return array_map(
            static fn(array $row): array => (new ApiTokens($row))->toArray(),
            $this->repository->forUser($userId)
        );
    }

    /** The raw token is deliberately returned once and is never stored. */
    public function create(CreateApiTokensDTO $dto): array
    {
        $userId = $dto->userId;
        $expiresAt = $dto->expiresAt;
        if ($userId <= 0) {
            throw new RuntimeException('Authentication required.');
        }

        if ($expiresAt !== null) {
            $timestamp = strtotime($expiresAt);
            if ($timestamp === false || $timestamp <= time()) {
                throw new RuntimeException('Expiration must be a future date and time.');
            }
            $expiresAt = date('Y-m-d H:i:s', $timestamp);
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenId = $this->repository->create(
            $userId,
            hash('sha256', $rawToken),
            $expiresAt,
            $dto->name,
            $dto->description,
            $dto->purpose,
            $dto->scopes,
            $dto->transportPolicy
        );
        $this->audit->log('API_TOKENS', 'CREATE_TOKEN', "Created {$dto->purpose} API token #{$tokenId} ({$dto->name}).");

        return [
            'id' => $tokenId,
            'token' => $rawToken,
            'name' => $dto->name,
            'purpose' => $dto->purpose,
            'scopes' => $dto->scopes,
            'transport_policy' => $dto->transportPolicy,
            'expires_at' => $expiresAt,
        ];
    }

    public function revoke(int $tokenId, int $userId): void
    {
        if ($tokenId <= 0 || $userId <= 0) {
            throw new RuntimeException('Invalid token.');
        }
        if (!$this->repository->revokeForUser($tokenId, $userId)) {
            throw new RuntimeException('Token was not found or is already revoked.');
        }

        $this->audit->log('API_TOKENS', 'REVOKE_TOKEN', "Revoked API token #{$tokenId}.");
    }

    public function monitoringIdentity(): array { return $this->repository->monitoringIdentity(); }

    public function updateMonitoringIdentity(array $input): array
    {
        $environment=strtoupper(trim((string)($input['monitoring_environment']??'PRODUCTION')));
        if(!in_array($environment,['PRODUCTION','STAGING','TEST','DR'],true))throw new RuntimeException('Monitoring environment is invalid.');
        $values=[];
        foreach(['monitoring_client_id','monitoring_client_name','monitoring_instance_name','monitoring_location'] as $key){$value=trim((string)($input[$key]??''));if($value===''||mb_strlen($value)>120)throw new RuntimeException('All monitoring identity fields are required and limited to 120 characters.');$values[$key]=$value;}
        $values['monitoring_environment']=$environment;$this->repository->saveMonitoringIdentity($values);
        $this->audit->log('API_TOKENS','UPDATE_MONITORING_IDENTITY','Updated privacy-safe HQ monitoring identity metadata.');
        return $this->repository->monitoringIdentity();
    }

    public function monitoringOperations(): array
    {
        $settings = $this->repository->monitoringSettings();
        return [
            'settings' => [
                'monitoring_hq_enabled' => ($settings['monitoring_hq_enabled'] ?? '0') === '1',
                'monitoring_hq_url' => (string) ($settings['monitoring_hq_url'] ?? ''),
                'monitoring_allow_insecure_http' => ($settings['monitoring_allow_insecure_http'] ?? '0') === '1',
                'monitoring_verify_tls' => ($settings['monitoring_verify_tls'] ?? '1') === '1',
                'monitoring_retention_days' => (int) ($settings['monitoring_retention_days'] ?? 30),
                'monitoring_disk_warning_percent' => (int) ($settings['monitoring_disk_warning_percent'] ?? 85),
                'monitoring_disk_critical_percent' => (int) ($settings['monitoring_disk_critical_percent'] ?? 95),
                'monitoring_memory_warning_percent' => (int) ($settings['monitoring_memory_warning_percent'] ?? 90),
                'monitoring_heartbeat_seconds' => (int) ($settings['monitoring_heartbeat_seconds'] ?? 60),
            ],
            'configuration' => $this->delivery->configurationStatus(),
            'delivery' => $this->monitoringRepository->deliverySummary(),
            'latest_snapshot' => $this->monitoringRepository->latestInfrastructureSnapshot(),
            'history' => $this->monitoringRepository->infrastructureHistory(24),
        ];
    }

    public function updateMonitoringSettings(MonitoringSettingsDTO $dto): array
    {
        if ($dto->enabled && !$this->delivery->configurationStatus()['token_configured']) {
            throw new RuntimeException('Configure the HQ token in the protected runtime file before enabling delivery.');
        }
        $this->repository->saveMonitoringSettings([
            'monitoring_hq_enabled' => $dto->enabled ? '1' : '0',
            'monitoring_hq_url' => rtrim($dto->hqUrl, '/'),
            'monitoring_allow_insecure_http' => $dto->allowInsecureHttp ? '1' : '0',
            'monitoring_verify_tls' => $dto->verifyTls ? '1' : '0',
            'monitoring_retention_days' => $dto->retentionDays,
            'monitoring_disk_warning_percent' => $dto->diskWarningPercent,
            'monitoring_disk_critical_percent' => $dto->diskCriticalPercent,
            'monitoring_memory_warning_percent' => $dto->memoryWarningPercent,
            'monitoring_heartbeat_seconds' => $dto->heartbeatSeconds,
        ]);
        $this->audit->log('API_TOKENS', 'UPDATE_MONITORING_SETTINGS', 'Updated infrastructure monitoring thresholds and HQ delivery configuration.');
        return $this->monitoringOperations();
    }

    public function testMonitoringConnectivity(): array
    {
        $result = $this->delivery->testConnectivity();
        $this->audit->log('API_TOKENS', 'TEST_HQ_CONNECTIVITY', 'Tested the configured HQ monitoring health endpoint; no snapshot was transmitted.');
        return $result;
    }

    public function retryMonitoringDeliveries(): array
    {
        $count = $this->monitoringRepository->retryFailedDeliveries();
        $this->audit->log('API_TOKENS', 'RETRY_MONITORING_DELIVERIES', "Queued {$count} failed infrastructure monitoring deliveries for retry.");
        return ['retried' => $count, 'delivery' => $this->monitoringRepository->deliverySummary()];
    }
}
