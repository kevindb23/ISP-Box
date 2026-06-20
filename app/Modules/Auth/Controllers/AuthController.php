<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Services\AuthService;
use App\Core\Security\RateLimiter;
use App\Core\Security\Csrf;
use Framework\SessionManager;

class AuthController
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
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

    public function login()
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents("php://input"), true);

            if (!is_array($input)) {
                $input = [];
            }

            $username = trim($input['username'] ?? '');
            $password = trim($input['password'] ?? '');
            $csrfToken = $input['csrf_token'] ?? null;

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            /*
            |--------------------------------------------------------------------------
            | Rate Limiting
            |--------------------------------------------------------------------------
            */

            if (!RateLimiter::check($ip)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Too many login attempts. Try again later.'
                ]);

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | CSRF Protection
            |--------------------------------------------------------------------------
            */

            if (!$csrfToken || !Csrf::validate($csrfToken)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid request'
                ]);

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Input
            |--------------------------------------------------------------------------
            */

            if (!$username || !$password) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid username or password'
                ]);

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Authentication
            |--------------------------------------------------------------------------
            */

            if ($this->auth->login($username, $password)) {
                echo json_encode([
                    'success' => true,
                    'redirect_url' => $this->redirectPathForCurrentUser(),
                ]);

                return;
            }

            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password'
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function redirectPathForCurrentUser(): string
    {
        $user = SessionManager::user();
        $role = strtoupper((string)($user['role'] ?? ''));

        if ($role === 'SUBSCRIBER') {
            return '/subscriber-portal';
        }

        if ($role === 'TECHNICIAN') {
            return '/technician-portal';
        }
        return '/dashboard';
    }

}