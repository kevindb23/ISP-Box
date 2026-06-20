<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Dashboard\Repositories\DashboardRepository;
use App\Modules\Dashboard\Entities\DashboardStats;
use App\Modules\Dashboard\DTOs\DashboardStatsDTO;

class DashboardService
{
    private DashboardRepository $repository;

    public function __construct(DashboardRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getStats(): array
    {
        $raw = $this->repository->getStats();

        $entity = (new DashboardStats())->fill($raw);

        $dto = new DashboardStatsDTO($entity->toArray());

        return $dto->toArray();
    }
}