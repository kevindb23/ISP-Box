<?php

namespace App\Modules\SystemSettings\Services;

use App\Modules\SystemSettings\DTOs\UpdateGeneralSettingsDTO;
use App\Modules\SystemSettings\Repositories\SystemConfigRepository;

class SystemSettingsService
{
    public function __construct(private SystemConfigRepository $repository)
    {
    }

    public function getGeneral(): array
    {
        $data = [
            'system_name' => 'NexusBox',
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'subscriber_prefix' => 'SUB',
            'service_prefix' => 'SVC',
            'job_prefix' => 'JOB',
            'invoice_due_days' => '7',
            'grace_period_days' => '3',
            'provisioning_mode' => 'FULL_AUTO',
            'installation_assignment_mode' => 'MANUAL',
            'session_timeout' => '3600',
            'max_login_attempts' => '5',
            'log_retention_days' => '30',
            'maintenance_mode' => '0',
        ];
        foreach ($this->repository->all() as $config) $data[$config->key] = $config->value;
        return $data;
    }

    public function updateGeneral(UpdateGeneralSettingsDTO $dto): array
    {
        $this->repository->saveMany($dto->toConfig());
        return $this->getGeneral();
    }
}
