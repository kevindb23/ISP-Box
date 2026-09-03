<?php

namespace App\Modules\NapManagement\Controllers;

use Framework\Controller;
use App\Modules\NapManagement\Services\NapManagementService;
use Framework\Request;

class NapManagementController extends Controller
{
    private NapManagementService $service;

    public function __construct(
        NapManagementService $service,
        private Request $request
    )
    {
        $this->service = $service;
    }

    private function jsonResponse(bool $ok, string $message = '', array $data = [], array $errors = []): void
    {
        $ok ? $this->success($data, $message ?: 'OK') : $this->error($message ?: 'Request failed.', 422, $errors, $data);
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
        $tab = trim((string)($this->request->query()['tab'] ?? 'lcp'));

        return $this->view('NapManagement/index', [
            'tab' => $tab
        ]);
    }

    public function storeNap()
    {
        $this->respondServiceResult($this->service->createNap($this->request->input()));
    }

    public function updateNap($id = null)
    {
        $payload = $this->request->input();
        if ($id !== null) {
            $payload['id'] = (int)$id;
        }

        $this->respondServiceResult($this->service->updateNap($payload));
    }

    public function deleteNap($id = null)
    {
        $this->respondServiceResult(
            $this->service->deleteNap((int)($id ?? $this->request->input()['id'] ?? 0))
        );
    }

    public function getAvailablePorts($lcpId)
    {
        $this->response->success(
            $this->service->getAvailablePortsByLcp((int)$lcpId),
            'Available ports loaded.'
        );
    }

    public function storeLcp()
    {
        $this->respondServiceResult($this->service->createLcp($this->request->input()));
    }

    public function updateLcp($id)
    {
        $this->respondServiceResult($this->service->updateLcp((int)$id, $this->request->input()));
    }

    public function deleteLcp($id = null)
    {
        $this->respondServiceResult(
            $this->service->deleteLcp((int)($id ?? $this->request->input()['id'] ?? 0))
        );
    }
}
