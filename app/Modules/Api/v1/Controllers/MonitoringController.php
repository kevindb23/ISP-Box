<?php

namespace App\Modules\Api\v1\Controllers;

use App\Modules\Api\v1\Services\MonitoringService;
use Framework\ApiController;

final class MonitoringController extends ApiController
{
    public function __construct(private MonitoringService $service) {}

    public function systemInfo(): void
    {
        $this->success($this->service->systemInfo(), 'System information retrieved.');
    }

    public function summary(string $resource): void
    {
        $allowed = ['subscribers', 'sessions', 'provisioning', 'onts', 'olts', 'billing'];
        if (!in_array($resource, $allowed, true)) {
            $this->error('Monitoring resource not found.', 404);
            return;
        }
        $this->success($this->service->summary($resource), ucfirst($resource) . ' summary retrieved.');
    }

    public function infrastructureSnapshot(): void
    {
        $this->success($this->service->infrastructureSnapshot(), 'Infrastructure snapshot retrieved.');
    }

    public function infrastructureHistory(): void
    {
        $hours=max(1,min(720,(int)($this->request()->query()['hours']??24)));
        $this->success($this->service->infrastructureHistory($hours), 'Infrastructure history retrieved.');
    }
}
