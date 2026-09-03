<?php

namespace App\Modules\OntDevices\Controllers;

use Framework\Controller;
use App\Modules\OntDevices\Services\AcsService;
use Framework\Request;
use Framework\Response;

class OntDevicesController extends Controller
{
    public function __construct(
        private AcsService $acsService,
        private Request $request,
        private Response $response
    )
    {
    }

    public function index()
    {
        $tab = trim((string)($this->request->query()['tab'] ?? 'inventory'));

        return $this->view('OntDevices/index', [
            'tab' => $tab
        ]);
    }

    public function acsDevice($id)
    {
        return $this->view('OntDevices/acs-device', [
            'deviceId' => $id
        ]);
    }

    public function acsPing(): void
    {
        $deviceId = trim((string)$this->request->value('deviceId', ''));

        if ($deviceId === '') {
            $this->response->error('Device ID is required.', 422);
            return;
        }

        try {
            $result = $this->acsService->pingDevice($deviceId);

            $this->response->success($result, 'ACS ping completed.');
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500);
        }
    }
}
