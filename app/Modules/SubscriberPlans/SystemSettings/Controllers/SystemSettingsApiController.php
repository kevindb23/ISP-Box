<?php

namespace App\Modules\SubscriberPlans\SystemSettings\Controllers;

use App\Modules\SubscriberPlans\SystemSettings\DTOs\UpdateGeneralSettingsDTO;
use App\Modules\SubscriberPlans\SystemSettings\Services\SystemSettingsService;
use App\Modules\SubscriberPlans\SystemSettings\Validators\GeneralSettingsValidator;
use Framework\ApiController;

class SystemSettingsApiController extends ApiController
{
    private SystemSettingsService $service;

    public function __construct(SystemSettingsService $service)
    {
        $this->service = $service;
    }

    public function getGeneral()
    {
        return $this->success($this->service->getGeneral());
    }

    public function updateGeneral()
    {
        $data = $this->request->all();

        $errors = GeneralSettingsValidator::validate($data);
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }

        $dto = new UpdateGeneralSettingsDTO($data);
        $this->service->updateGeneral($dto);

        return $this->success([], 'Settings updated');
    }
}