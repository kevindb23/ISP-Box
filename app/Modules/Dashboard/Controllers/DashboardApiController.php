<?php

namespace App\Modules\Dashboard\Controllers;

use Framework\ApiController;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Validators\DashboardValidator;

class DashboardApiController extends ApiController
{
    private DashboardService $service;
    private DashboardValidator $validator;

    public function __construct(
        DashboardService $service,
        DashboardValidator $validator
    ) {
        $this->service = $service;
        $this->validator = $validator;
    }

    public function stats()
    {
        try {
            $validation = $this->validator->validateStatsRequest();

            if (!$validation['valid']) {
                return $this->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'data' => null,
                ], 422);
            }

            $data = $this->service->getStats();

            return $this->json([
                'ok' => true,
                'success' => true,
                'message' => '',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}