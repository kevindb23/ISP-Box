<?php

namespace App\Modules\Radius\Controllers;

use App\Modules\Radius\Services\RadiusService;
use Framework\ApiController;
use Throwable;

class RadiusApiController extends ApiController
{
    public function __construct(private RadiusService $service)
    {
    }

    public function index(): void
    {
        RadiusController::assertNetworkAccess();
        $this->respond(true, '', $this->service->list());
    }

    public function show($id): void
    {
        RadiusController::assertNetworkAccess();
        $row = $this->service->get((int)$id);
        if (!$row) {
            $this->respond(false, 'RADIUS settings not found.', null, [], 404);
        }
        $this->respond(true, '', $row);
    }

    public function store(): void
    {
        RadiusController::assertNetworkAccess();
        $this->run(fn() => $this->service->create($this->request()->input()));
    }

    public function update($id): void
    {
        RadiusController::assertNetworkAccess();
        $this->run(fn() => $this->service->update((int)$id, $this->request()->input()));
    }

    public function destroy(): void
    {
        RadiusController::assertNetworkAccess();
        $input = $this->request()->input();
        $id = (int)($input['id'] ?? 0);
        $this->run(fn() => $this->service->delete($id));
    }

    private function run(callable $callback): void
    {
        try {
            $result = $callback();
            $this->respond((bool)($result['ok'] ?? false), (string)($result['message'] ?? ''), $result, $result['errors'] ?? [], ($result['ok'] ?? false) ? 200 : 422);
        } catch (Throwable $e) {
            $this->respond(false, $e->getMessage(), null, [], 500);
        }
    }

    private function respond(bool $ok, string $message, $data = null, array $errors = [], int $status = 200): void
    {
        $ok ? $this->success($data, $message ?: 'OK', $status)
            : $this->error($message ?: 'Request failed.', $status, $errors, $data);
        exit;
    }
}
