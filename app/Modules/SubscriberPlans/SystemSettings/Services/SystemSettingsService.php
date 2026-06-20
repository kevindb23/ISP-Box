<?php

namespace App\Modules\SubscriberPlans\SystemSettings\Services;

use App\Modules\SubscriberPlans\SystemSettings\DTOs\UpdateGeneralSettingsDTO;
use App\Modules\SubscriberPlans\SystemSettings\Repositories\SystemConfigRepository;

class SystemSettingsService
{
    private SystemConfigRepository $repo;

    public function __construct(SystemConfigRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getGeneral(): array
    {
        return $this->repo->getAll();
    }

    public function updateGeneral(UpdateGeneralSettingsDTO $dto): void
    {
        $data = get_object_vars($dto);

        foreach ($data as $key => $value) {
            $this->repo->set($key, (string)$value);
        }
    }
}