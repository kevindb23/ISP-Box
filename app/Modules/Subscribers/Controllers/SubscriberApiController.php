<?php

namespace App\Modules\Subscribers\Controllers;

use Framework\Controller;
use App\Modules\Subscribers\Services\SubscriberService;

class SubscriberApiController extends Controller
{
    private SubscriberService $service;

    public function __construct(SubscriberService $service)
    {
        $this->service = $service;
    }

    private function respond(array $payload): void
    {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    public function index()
    {
        $search = trim((string)($_GET['search'] ?? ''));

        $this->respond([
            'ok' => true,
            'success' => true,
            'status' => 'success',
            'data' => $this->service->getAll($search),
        ]);
    }

    public function sessions()
    {
        $search = trim((string)($_GET['search'] ?? ''));

        $this->respond([
            'ok' => true,
            'success' => true,
            'status' => 'success',
            'data' => $this->service->getSessionStates($search),
        ]);
    }

    public function create()
    {
        $res = $this->service->create($_POST);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
            'data' => $res,
        ]);
    }

    public function update($id)
    {
        $res = $this->service->update((int)$id, $_POST);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
        ]);
    }

    public function suspend()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->suspend($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
        ]);
    }

    public function reactivate()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->reactivate($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
        ]);
    }

    public function resetPassword()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->resetPassword($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
            'ppp_password' => $res['ppp_password'] ?? null,
        ]);
    }

    public function resetPortalPassword()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->resetPortalPassword($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
            'portal_username' => $res['portal_username'] ?? null,
            'portal_password' => $res['portal_password'] ?? null,
            'data' => $res,
        ]);
    }

    public function delete()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->delete($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
        ]);
    }

    public function show($id)
    {
        $row = $this->service->getById((int)$id);

        $this->respond([
            'ok' => true,
            'success' => true,
            'status' => 'success',
            'data' => $row
        ]);
    }
}