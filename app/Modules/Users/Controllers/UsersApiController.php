<?php

namespace App\Modules\Users\Controllers;

use App\Modules\Users\Services\UsersService;
use Framework\ApiController;

class UsersApiController extends ApiController
{
    private UsersService $service;

    public function __construct(UsersService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $this->success($this->service->list(), 'Users loaded.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function show($id): void
    {
        try {
            $user = $this->service->find((int)$id);

            if (!$user) {
                $this->error('User not found.', 404);
                return;
            }

            $this->success($user, 'User loaded.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function store(): void
    {
        try {
            $id = $this->service->create($this->request()->input());
            $this->success(['id' => $id], 'User created successfully.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function update($id): void
    {
        try {
            $this->service->update((int)$id, $this->request()->input());
            $this->success([], 'User updated successfully.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function delete(): void
    {
        try {
            $id = (int)$this->request()->value('id', 0);
            $this->service->delete($id);
            $this->success([], 'User disabled successfully.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function resetPassword($id): void
    {
        try {
            $this->service->resetPassword((int)$id, $this->request()->input());
            $this->success([], 'Password reset successfully.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function permissions($id): void
    {
        try {
            $this->success($this->service->permissionMatrix((int)$id), 'Permissions loaded.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function updatePermissions($id): void
    {
        try {
            $this->service->updatePermissions((int)$id, $this->request()->input());
            $this->success([], 'User permissions updated successfully.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }
}
