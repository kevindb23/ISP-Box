<?php

namespace App\Modules\SubscriberPlans\Controllers;

use Framework\Controller;
use App\Modules\SubscriberPlans\Services\SubscriberPlansService;
use Framework\Request;
use Framework\Response;

class SubscriberPlansController extends Controller
{
    private SubscriberPlansService $service;

    public function __construct(
        SubscriberPlansService $service,
        private Request $request,
        private Response $response
    )
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->view('SubscriberPlans/index', [
            'plans' => $this->service->getAll()
        ]);
    }

    public function store()
    {
        $result = $this->service->create($this->request->input());

        $this->respond($result, 201);
    }

    public function update($id)
    {
        $result = $this->service->update($id, $this->request->input());

        $this->respond($result);
    }

    public function delete()
    {
        $id = (int)($this->request->input()['id'] ?? 0);

        $result = $this->service->delete($id);

        $this->respond($result);
    }

    private function respond(array $result, int $successStatus = 200): void
    {
        $ok = (bool)($result['ok'] ?? $result['success'] ?? false);
        $message = (string)($result['message'] ?? ($ok ? 'OK' : 'Request failed.'));
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $data = $result;
        unset($data['ok'], $data['success'], $data['status'], $data['message'], $data['error'], $data['errors']);

        if ($ok) {
            $this->response->success($data, $message, $successStatus);
            return;
        }

        $this->response->error($message, 422, $errors, $data ?: null);
    }
}
