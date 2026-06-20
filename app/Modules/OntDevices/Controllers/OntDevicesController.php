<?php

namespace App\Modules\OntDevices\Controllers;

use Framework\Controller;
use App\Modules\OntDevices\Services\AcsService;

class OntDevicesController extends Controller
{
    public function index()
    {
        $tab = trim((string)($_GET['tab'] ?? 'inventory'));

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
        header('Content-Type: application/json; charset=UTF-8');

        $deviceId = trim($_POST['deviceId'] ?? '');

        if ($deviceId === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Device ID is required.',
                'data' => null
            ]);
            return;
        }

        try {
            $acsService = new AcsService();
            $result = $acsService->pingDevice($deviceId);

            http_response_code(200);
            echo json_encode([
                'ok' => true,
                'message' => 'ACS ping completed.',
                'data' => $result
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }
}