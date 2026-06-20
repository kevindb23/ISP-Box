<?php

namespace Framework;

class ApiController
{

    /*
    |--------------------------------------------------------------------------
    | JSON Response
    |--------------------------------------------------------------------------
    */

    protected function json($data, $status = 200)
    {
        http_response_code($status);

        header('Content-Type: application/json');

        echo json_encode($data);
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    protected function success($data = [], $message = "OK")
    {
        $this->json([
            "success" => true,
            "message" => $message,
            "data" => $data
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Error Response
    |--------------------------------------------------------------------------
    */

    protected function error($message, $status = 400)
    {
        $this->json([
            "success" => false,
            "error" => $message
        ], $status);
    }

}
