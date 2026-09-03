<?php

namespace Framework;

class ApiController
{

    private ?Request $requestInstance = null;
    private ?Response $responseInstance = null;

    /*
    |--------------------------------------------------------------------------
    | JSON Response
    |--------------------------------------------------------------------------
    */

    protected function json($data, $status = 200)
    {
        $this->response()->json((array)$data, (int)$status);
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    protected function success($data = [], $message = "OK", int $status = 200): void
    {
        $this->response()->success($data, (string)$message, $status);
    }

    /*
    |--------------------------------------------------------------------------
    | Error Response
    |--------------------------------------------------------------------------
    */

    protected function error($message, $status = 400, array $errors = [], $data = null): void
    {
        $this->response()->error((string)$message, (int)$status, $errors, $data);
    }

    /**
     * Convert the legacy service-result convention into the single public API
     * envelope without making individual controllers reproduce response logic.
     */
    protected function serviceResult(array $result, int $successStatus = 200, int $errorStatus = 422): void
    {
        $ok = (bool)($result['ok'] ?? $result['success'] ?? false);
        $message = (string)($result['message'] ?? ($ok ? 'OK' : 'Request failed.'));
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $data = $result;
        unset($data['ok'], $data['success'], $data['status'], $data['message'], $data['error'], $data['errors']);

        if ($ok) {
            $this->success($data, $message, $successStatus);
            return;
        }

        $this->error($message, $errorStatus, $errors, $data ?: null);
    }

    protected function request(): Request
    {
        return $this->requestInstance ??= new Request();
    }

    protected function response(): Response
    {
        return $this->responseInstance ??= new Response();
    }

}
