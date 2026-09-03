<?php

namespace Framework;

class Response
{
    public function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function success($data = [], string $message = 'OK', int $status = 200): void
    {
        $this->json([
            'ok' => true,
            'success' => true,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'errors' => [],
        ], $status);
    }

    public function error(
        string $message,
        int $status = 400,
        array $errors = [],
        $data = null
    ): void {
        $this->json([
            'ok' => false,
            'success' => false,
            'status' => 'error',
            'message' => $message,
            'error' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $status);
    }
}
