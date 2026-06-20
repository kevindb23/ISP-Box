<?php

namespace App\Modules\Audit\Controllers;

use App\Modules\Audit\Services\AuditService;

class AuditApiController
{
    private AuditService $service;

    public function __construct(AuditService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'ok' => true,
            'data' => $this->service->latest()
        ]);
    }

    public function show($id): void
    {
        header('Content-Type: application/json');

        $item = $this->service->find((int)$id);

        if (!$item) {

            http_response_code(404);

            echo json_encode([
                'ok' => false,
                'message' => 'Audit log not found.'
            ]);

            return;
        }

        echo json_encode([
            'ok' => true,
            'data' => $item
        ]);
    }
}