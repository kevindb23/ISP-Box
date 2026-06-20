<?php

namespace App\Modules\Api\v1\Middleware;

use App\Modules\Api\v1\Repositories\ApiTokenRepository;

class ApiAuthMiddleware
{
    private ApiTokenRepository $tokens;

    public function __construct(ApiTokenRepository $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle()
    {
        $headers = getallheaders();

        if (!isset($headers['Authorization'])) {

            http_response_code(401);

            echo json_encode([
                "success" => false,
                "error" => "Missing token"
            ]);

            exit;
        }

        $token = str_replace("Bearer ", "", $headers['Authorization']);

        if (!$this->tokens->validate($token)) {

            http_response_code(401);

            echo json_encode([
                "success" => false,
                "error" => "Invalid token"
            ]);

            exit;
        }
    }
}
