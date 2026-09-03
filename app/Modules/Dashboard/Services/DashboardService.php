<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Dashboard\Repositories\DashboardRepository;
use App\Modules\Dashboard\Entities\DashboardStats;
use App\Modules\Dashboard\DTOs\DashboardStatsDTO;
use App\Modules\Api\v1\Repositories\MonitoringRepository;

class DashboardService
{
    private DashboardRepository $repository;

    public function __construct(DashboardRepository $repository, private MonitoringRepository $monitoring)
    {
        $this->repository = $repository;
    }

    public function getStats(): array
    {
        $raw = $this->repository->getStats();

        $entity = (new DashboardStats())->fill($raw);

        $dto = new DashboardStatsDTO($entity->toArray());

        return $dto->toArray() + [
            'infrastructure' => [
                'latest' => $this->monitoring->latestInfrastructureSnapshot(),
                'history' => $this->monitoring->infrastructureDashboardHistory(24, 120),
                'delivery' => $this->monitoring->deliverySummary(),
            ],
        ];
    }
}
