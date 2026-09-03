<?php

namespace App\Modules\Api\v1\Services;

use App\Modules\Api\v1\Repositories\MonitoringRepository;

final class MonitoringService
{
    public function __construct(private MonitoringRepository $repository, private ?InfrastructureProbeService $probes = null) {}

    public function systemInfo(): array
    {
        $info = $this->repository->systemInfo();
        return [
            'instance_id' => $info['instance_id'] ?? null,
            'system_name' => $info['system_name'] ?? 'NexusBox',
            'company_name' => $info['company_name'] ?? null,
            'version' => '1.0',
            'timezone' => $info['timezone'] ?? 'Asia/Manila',
        ];
    }

    public function summary(string $resource): array
    {
        $summary = match ($resource) {
            'subscribers' => $this->repository->subscriberSummary(),
            'sessions' => $this->repository->sessionSummary(),
            'provisioning' => $this->repository->provisioningSummary(),
            'onts' => $this->repository->ontSummary(),
            'olts' => $this->repository->oltSummary(),
            'billing' => $this->repository->billingSummary(),
            default => [],
        };

        foreach ($summary as $key => $value) {
            $summary[$key] = $key === 'outstanding_balance' ? (float)$value : (int)($value ?? 0);
        }
        if ($resource === 'olts') {
            $summary += ['online' => null, 'offline' => null, 'status_available' => false];
        }
        return $summary;
    }

    public function infrastructureSnapshot(): array
    {
        $snapshot=$this->repository->infrastructureSnapshot();
        if($this->probes!==null){$probe=$this->probes->collect();$snapshot['system']['failed_systemd_units']=$probe['failed_systemd_units'];$snapshot['services']['web']=$probe['local_services']['web'];$snapshot['services']['mariadb_daemon']=$probe['local_services']['mariadb'];$snapshot['services']['bng_runtime']=$probe['remote'];$critical=false;$degraded=false;foreach($snapshot['services'] as $service){$critical=$critical||(($service['status']??'')==='CRITICAL');$degraded=$degraded||(($service['status']??'')==='DEGRADED');}$snapshot['overall_status']=$critical?'CRITICAL':($degraded?'DEGRADED':$snapshot['overall_status']);}
        return $snapshot;
    }

    public function collectAndStore(int $retentionDays=30): array{$settings=$this->repository->monitoringSettings();$retention=(int)($settings['monitoring_retention_days']??$retentionDays);$snapshot=$this->infrastructureSnapshot();$snapshot['snapshot_id']=$this->repository->storeInfrastructureSnapshot($snapshot,$retention);if(($settings['monitoring_hq_enabled']??'0')==='1')$this->repository->enqueueSnapshot((int)$snapshot['snapshot_id'],$snapshot);return$snapshot;}
    public function infrastructureHistory(int $hours=24): array{return$this->repository->infrastructureHistory($hours)+['delivery'=>$this->repository->deliverySummary()];}
}
