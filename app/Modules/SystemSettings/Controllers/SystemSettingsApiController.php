<?php

namespace App\Modules\SystemSettings\Controllers;

use App\Modules\SystemSettings\DTOs\UpdateGeneralSettingsDTO;
use App\Modules\SystemSettings\Services\SystemSettingsService;
use App\Modules\SystemSettings\Validators\GeneralSettingsValidator;
use Framework\ApiController;

class SystemSettingsApiController extends ApiController
{
    public function __construct(
        private SystemSettingsService $service,
        private GeneralSettingsValidator $validator
    ) {
    }

    public function getGeneral(): void
    {
        $this->success($this->service->getGeneral(), 'System settings loaded.');
    }

    public function updateGeneral(): void
    {
        $input = $this->request()->input();
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        $settings = $this->service->updateGeneral(UpdateGeneralSettingsDTO::fromArray($input));
        $this->success($settings, 'System settings updated.');
    }
}
