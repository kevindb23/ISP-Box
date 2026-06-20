<?php

namespace App\Modules\Api\v1\Controllers;

use Framework\ApiController;
use App\Modules\Auth\Services\AuthService;

class AuthController extends ApiController
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    public function login()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if ($this->auth->login($username, $password)) {

            $this->json([
                "success" => true
            ]);

        } else {

            $this->json([
                "success" => false,
                "message" => "Invalid credentials"
            ], 401);

        }
    }
}
