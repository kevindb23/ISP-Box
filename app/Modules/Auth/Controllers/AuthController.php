<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\DTOs\LoginDTO;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Validators\LoginValidator;
use App\Core\Security\RateLimiter;
use App\Core\Security\Csrf;
use Framework\SessionManager;
use Framework\Request;
use Framework\Response;

class AuthController
{
    public function __construct(
        private AuthService $auth,
        private LoginValidator $validator,
        private Request $request,
        private Response $response
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Show Login Page
    |--------------------------------------------------------------------------
    */

    public function loginPage()
    {
        /*
        | If already logged in, redirect based on role.
        */

        if (SessionManager::check()) {
            header('Location: ' . $this->redirectPathForCurrentUser());
            exit;
        }

        /*
        | Prevent browser caching
        */

        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");

        require BASE_PATH . '/app/Modules/Auth/Views/login.php';
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Login Request
    |--------------------------------------------------------------------------
    */

    public function login(): void
    {
        try {
            $input = json_decode(file_get_contents("php://input"), true);

            if (!is_array($input)) {
                $input = [];
            }

            $credentials = new LoginDTO($input);

            $ip = $this->request->ip() ?? 'unknown';

            /*
            |--------------------------------------------------------------------------
            | Rate Limiting
            |--------------------------------------------------------------------------
            */

            if (!RateLimiter::check($ip)) {
                $this->response->error('Too many login attempts. Try again later.', 429);
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | CSRF Protection
            |--------------------------------------------------------------------------
            */

            if (!$credentials->csrfToken || !Csrf::validate($credentials->csrfToken)) {
                $this->response->error('Invalid request', 419);
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Input
            |--------------------------------------------------------------------------
            */

            if ($this->validator->validate($credentials) !== []) {
                $this->response->error('Invalid username or password', 422);
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Authentication
            |--------------------------------------------------------------------------
            */

            if ($this->auth->login($credentials)) {
                $this->response->success([
                    'redirect_url' => $this->redirectPathForCurrentUser(),
                ], 'Login successful.');
                return;
            }

            $this->response->error('Invalid username or password', 401);
        } catch (\Throwable $e) {
            error_log('[Auth] Login failed: ' . $e->getMessage());
            $this->response->error('Unable to complete login. Please try again.', 500);
        }
    }

    private function redirectPathForCurrentUser(): string
    {
        $user = SessionManager::user();
        $role = strtoupper((string)($user['role'] ?? ''));

        if ($role === 'SUBSCRIBER') {
            return '/subscriber-portal';
        }

        if (in_array($role, ['TECHNICIAN', 'BILLING', 'NOC', 'SUPPORT'], true)) {
            return '/staff-attendance';
        }

        return '/dashboard';
    }

}
