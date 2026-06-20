<?php

namespace App\Modules\OntDevices\Controllers;

use App\Modules\OntDevices\Services\OntDevicesService;
use Framework\Controller;

class OntDevicesApiController extends Controller
{
    private OntDevicesService $service;

    public function __construct(OntDevicesService $service)
    {
        $this->service = $service;
    }

    private function respond(
        bool $ok,
        string $message = '',
        $data = null,
        array $errors = [],
        int $httpCode = 200
    ): void {
        if (ob_get_length()) {
            ob_clean();
        }

        http_response_code($httpCode);
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

        $this->respond($ok, $message, $data, $errors, $ok ? 200 : 400);
    }

    public function inventory(): void
    {
        $this->respond(true, '', $this->service->getInventory(), []);
    }

    public function inventoryItem($id): void
    {
        $row = $this->service->getInventoryById((int)$id);

        if (!$row) {
            $this->respond(false, 'ONT record not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function discovery(): void
    {
        $this->respond(true, '', $this->service->getDiscovery(), []);
    }

    public function discover(): void
    {
        $this->respondServiceResult($this->service->discoverAndSave());
    }

    public function addToInventory(): void
    {
        $this->respondServiceResult($this->service->addToInventory($_POST));
    }

    public function acs(): void
    {
        $this->respond(true, '', $this->service->getAcsDevices(), []);
    }

    public function store(): void
    {
        $this->respondServiceResult($this->service->create($_POST));
    }

    public function update($id): void
    {
        $payload = $_POST;
        $payload['id'] = (int)$id;

        $this->respondServiceResult($this->service->update($payload));
    }

    public function delete(): void
    {
        $this->respondServiceResult($this->service->delete((int)($_POST['id'] ?? 0)));
    }
}