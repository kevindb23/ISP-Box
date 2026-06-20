<?php

namespace App\Modules\Subscribers\Controllers;

use Framework\Controller;
use App\Modules\Subscribers\Services\SubscriberService;

class SubscriberController extends Controller
{
    private SubscriberService $service;

    public function __construct(SubscriberService $service)
    {
        $this->service = $service;
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    public function index()
    {
        $search = trim((string)($_GET['search'] ?? ''));

        return $this->view('Subscribers/index', [
            'subscribers' => $this->service->getAll($search),
            'plans' => $this->service->getPlans(),
            'searchVal' => $search,
        ]);
    }

    public function create()
    {
        $res = $this->service->create($_POST);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ]);
    }

    public function update($id)
    {
        $res = $this->service->update((int)$id, $_POST);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ]);
    }

    public function suspend()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->suspend($id);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ]);
    }

    public function reactivate()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->reactivate($id);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ]);
    }

    public function resetPassword()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->resetPassword($id);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'ppp_password' => $res['ppp_password'] ?? null,
            'data' => $res,
        ]);
    }

    public function resetPortalPassword()
    {
        $id = (int)($_POST['id'] ?? 0);
        $res = $this->service->resetPortalPassword($id);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
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
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ]);
    }
}