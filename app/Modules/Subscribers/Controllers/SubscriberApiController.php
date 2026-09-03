<?php

namespace App\Modules\Subscribers\Controllers;

use Framework\ApiController;
use App\Modules\Subscribers\Services\SubscriberService;

class SubscriberApiController extends ApiController
{
    private SubscriberService $service;

    public function __construct(SubscriberService $service)
    {
        $this->service = $service;
    }

    private function respond(array $payload, int $status = 200): void
    {
        $ok = (bool)($payload['ok'] ?? $payload['success'] ?? ($status < 400));
        $message = (string)($payload['message'] ?? ($ok ? 'OK' : 'Request failed.'));
        $data = $payload['data'] ?? null;
        if ($data === null) {
            $data = $payload;
            unset($data['ok'], $data['success'], $data['status'], $data['message'], $data['errors']);
            $data = $data ?: null;
        }
        $errors = is_array($payload['errors'] ?? null) ? $payload['errors'] : [];
        $ok ? $this->success($data, $message, $status) : $this->error($message, $status, $errors, $data);
        exit;
    }

    public function index()
    {
        $search = trim((string)($this->request()->query()['search'] ?? ''));

        $this->respond([
            'ok' => true,
            'success' => true,
            'status' => 'success',
            'data' => $this->service->getAll($search),
        ]);
    }

    public function sessions()
    {
        $search = trim((string)($this->request()->query()['search'] ?? ''));

        $this->respond([
            'ok' => true,
            'success' => true,
            'status' => 'success',
            'data' => $this->service->getSessionStates($search),
        ]);
    }

    public function plans(): void
    {
        $this->respond([
            'ok' => true,
            'success' => true,
            'status' => 'success',
            'data' => $this->service->getPlans(),
        ]);
    }

    public function create()
    {
        $res = $this->service->create($this->request()->input());

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
            'data' => $res,
        ], ($res['ok'] ?? false) ? 201 : 422);
    }

    public function update($id)
    {
        $res = $this->service->update((int)$id, $this->request()->input());

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
        ], ($res['ok'] ?? false) ? 200 : 422);
    }

    public function suspend()
    {
        $id = (int)$this->request()->value('id', 0);
        $res = $this->service->suspend($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
        ], ($res['ok'] ?? false) ? 200 : 422);
    }

    public function reactivate()
    {
        $id = (int)$this->request()->value('id', 0);
        $res = $this->service->reactivate($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
        ], ($res['ok'] ?? false) ? 200 : 422);
    }

    public function resetPassword()
    {
        $id = (int)$this->request()->value('id', 0);
        $res = $this->service->resetPassword($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
            'ppp_password' => $res['ppp_password'] ?? null,
        ], ($res['ok'] ?? false) ? 200 : 422);
    }

    public function resetPortalPassword()
    {
        $id = (int)$this->request()->value('id', 0);
        $res = $this->service->resetPortalPassword($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
            'portal_username' => $res['portal_username'] ?? null,
            'portal_password' => $res['portal_password'] ?? null,
            'data' => $res,
        ], ($res['ok'] ?? false) ? 200 : 422);
    }

    public function delete()
    {
        $id = (int)$this->request()->value('id', 0);
        $res = $this->service->delete($id);

        $this->respond([
            'success' => $res['ok'] ?? false,
            'ok' => $res['ok'] ?? false,
            'message' => $res['message'] ?? '',
        ], ($res['ok'] ?? false) ? 200 : 422);
    }

    public function show($id)
    {
        $row = $this->service->getById((int)$id);

        if (!$row) {
            $this->respond([
                'ok' => false,
                'success' => false,
                'status' => 'error',
                'message' => 'Subscriber not found.',
                'data' => null,
            ], 404);
            return;
        }

        $this->respond([
            'ok' => true,
            'success' => true,
            'status' => 'success',
            'data' => $row
        ]);
    }
}
