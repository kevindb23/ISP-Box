<?php

namespace App\Modules\NapManagement\Controllers;

use Framework\Controller;
use App\Modules\NapManagement\Services\NapManagementService;

class NapManagementController extends Controller
{
    private NapManagementService $service;

    public function __construct(NapManagementService $service)
    {
        $this->service = $service;
    }

    private function jsonResponse(bool $ok, string $message = '', array $data = [], array $errors = []): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'ok' => $ok,
            'status' => $ok ? 'success' : 'error',
            'success' => $ok,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ]);

        exit;
    }

    private function respondServiceResult(array $res): void
    {
        $ok = (bool)($res['ok'] ?? false);
        $message = (string)($res['message'] ?? '');
        $errors = is_array($res['errors'] ?? null) ? array_values($res['errors']) : [];

        $data = $res;
        unset($data['ok'], $data['message'], $data['errors']);

        $this->jsonResponse($ok, $message, $data, $errors);
    }

    public function index()
    {
        $tab = trim((string)($_GET['tab'] ?? 'lcp'));

        return $this->view('NapManagement/index', [
            'tab' => $tab
        ]);
    }

    public function storeNap()
    {
        $this->respondServiceResult($this->service->createNap($_POST));
    }

    public function updateNap($id = null)
    {
        $payload = $_POST;
        if ($id !== null) {
            $payload['id'] = (int)$id;
        }

        $this->respondServiceResult($this->service->updateNap($payload));
    }

    public function deleteNap($id = null)
    {
        $this->respondServiceResult(
            $this->service->deleteNap((int)($id ?? $_POST['id'] ?? 0))
        );
    }

    public function getAvailablePorts($lcpId)
    {
        header('Content-Type: application/json');
        echo json_encode($this->service->getAvailablePortsByLcp((int)$lcpId));
        exit;
    }

    public function storeLcp()
    {
        $this->respondServiceResult($this->service->createLcp($_POST));
    }

    public function updateLcp($id)
    {
        $this->respondServiceResult($this->service->updateLcp((int)$id, $_POST));
    }

    public function deleteLcp($id = null)
    {
        $this->respondServiceResult(
            $this->service->deleteLcp((int)($id ?? $_POST['id'] ?? 0))
        );
    }
}