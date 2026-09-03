<?php

namespace App\Modules\Subscribers\Controllers;

use Framework\Controller;
use App\Modules\Subscribers\Services\SubscriberService;
use Framework\Request;

class SubscriberController extends Controller
{
    private SubscriberService $service;

    public function __construct(SubscriberService $service, private Request $request)
    {
        $this->service = $service;
    }

    private function json(array $payload, int $status = 200): void
    {
        $ok = (bool)($payload['ok'] ?? $payload['success'] ?? ($status < 400));
        $message = (string)($payload['message'] ?? ($ok ? 'OK' : 'Request failed.'));
        $data = $payload['data'] ?? null;
        $errors = is_array($payload['errors'] ?? null) ? $payload['errors'] : [];
        $ok ? $this->success($data, $message, $status) : $this->error($message, $status, $errors, $data);
        exit;
    }

    public function index()
    {
        $search = trim((string)($this->request->query()['search'] ?? ''));

        return $this->view('Subscribers/index', [
            'subscribers' => $this->service->getAll($search),
            'plans' => $this->service->getPlans(),
            'searchVal' => $search,
        ]);
    }

    public function create()
    {
        $res = $this->service->create($this->request->input());
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ], $ok ? 201 : 422);
    }

    public function update($id)
    {
        $res = $this->service->update((int)$id, $this->request->input());
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ], $ok ? 200 : 422);
    }

    public function suspend()
    {
        $id = (int)($this->request->input()['id'] ?? 0);
        $res = $this->service->suspend($id);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ], $ok ? 200 : 422);
    }

    public function reactivate()
    {
        $id = (int)($this->request->input()['id'] ?? 0);
        $res = $this->service->reactivate($id);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ], $ok ? 200 : 422);
    }

    public function resetPassword()
    {
        $id = (int)($this->request->input()['id'] ?? 0);
        $res = $this->service->resetPassword($id);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'ppp_password' => $res['ppp_password'] ?? null,
            'data' => $res,
        ], $ok ? 200 : 422);
    }

    public function resetPortalPassword()
    {
        $id = (int)($this->request->input()['id'] ?? 0);
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
        ], $ok ? 200 : 422);
    }

    public function delete()
    {
        $id = (int)($this->request->input()['id'] ?? 0);
        $res = $this->service->delete($id);
        $ok = (bool)($res['ok'] ?? false);

        $this->json([
            'ok' => $ok,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $res['message'] ?? '',
            'data' => $res,
        ], $ok ? 200 : 422);
    }
}
