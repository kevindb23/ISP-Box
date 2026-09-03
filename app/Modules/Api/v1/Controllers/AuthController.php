<?php

namespace App\Modules\Api\v1\Controllers;

use Framework\ApiController;
use Framework\SessionManager;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\DTOs\LoginDTO;
use App\Modules\Auth\Validators\LoginValidator;
use App\Modules\ApiTokens\DTOs\CreateApiTokensDTO;
use App\Modules\ApiTokens\Services\ApiTokensService;

class AuthController extends ApiController
{
    private AuthService $auth;
    private ApiTokensService $tokens;

    public function __construct(
        AuthService $auth,
        ApiTokensService $tokens,
        private LoginValidator $validator
    )
    {
        $this->auth = $auth;
        $this->tokens = $tokens;
    }

    public function login()
    {
        $credentials = new LoginDTO($this->request()->input());
        $errors = $this->validator->validateCredentials($credentials);
        if ($errors !== []) {
            $this->error('Invalid username or password', 422, $errors);
            return;
        }

        if ($this->auth->login($credentials)) {

            $userId = (int)(SessionManager::id() ?? 0);

            if ($userId <= 0) {
                $this->json([
                    "success" => false,
                    "message" => "Unable to create API credentials"
                ], 500);
                return;
            }

            $created = $this->tokens->create(new CreateApiTokensDTO(
                userId: $userId,
                expiresAt: date('Y-m-d H:i:s', time() + 86400),
                name: 'API login ' . date('Y-m-d H:i'),
                description: 'Short-lived token issued by the legacy API login endpoint.',
                purpose: 'EXTERNAL_INTEGRATION',
                scopes: ['monitoring.read']
            ));

            $this->success([
                "token_type" => "Bearer",
                "access_token" => $created['token'],
                "expires_at" => $created['expires_at'],
                "scopes" => $created['scopes'],
            ], 'API access token created.');

        } else {

            $this->error('Invalid credentials', 401);

        }
    }
}
