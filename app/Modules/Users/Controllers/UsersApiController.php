<?php

namespace App\Modules\Users\Controllers;

use App\Modules\Users\Services\UsersService;

class UsersApiController
{
    private UsersService $service;

    public function __construct(UsersService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        header('Content-Type: application/json');

        try {
            echo json_encode([
                'ok' => true,
                'message' => 'Users loaded.',
                'data' => $this->service->list()
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => []
            ]);
        }
    }

    public function show($id): void
    {
        header('Content-Type: application/json');

        try {
            $user = $this->service->find((int)$id);

            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
                return;
            }

            echo json_encode([
                'ok' => true,
                'message' => 'User loaded.',
                'data' => $user
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function store(): void
    {
        header('Content-Type: application/json');

        try {
            $id = $this->service->create($_POST);

            echo json_encode([
                'ok' => true,
                'message' => 'User created successfully.',
                'data' => ['id' => $id]
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function update($id): void
    {
        header('Content-Type: application/json');

        try {
            $this->service->update((int)$id, $_POST);

            echo json_encode([
                'ok' => true,
                'message' => 'User updated successfully.'
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function delete(): void
    {
        header('Content-Type: application/json');

        try {
            $id = (int)($_POST['id'] ?? 0);
            $this->service->delete($id);

            echo json_encode([
                'ok' => true,
                'message' => 'User disabled successfully.'
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function resetPassword($id): void
    {
        header('Content-Type: application/json');

        try {
            $this->service->resetPassword((int)$id, $_POST);

            echo json_encode([
                'ok' => true,
                'message' => 'Password reset successfully.'
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}