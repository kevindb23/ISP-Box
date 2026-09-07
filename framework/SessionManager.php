<?php

namespace Framework;

class SessionManager
{
    private const SESSION_TIMEOUT = 1800; // 30 minutes

    public static function timeoutSeconds(): int
    {
        return self::SESSION_TIMEOUT;
    }

    private static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }
    }

    public static function login($user)
    {
        self::start();

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'] ?? null;
        $_SESSION['username'] = $user['username'] ?? null;
        $_SESSION['full_name'] = $user['full_name'] ?? null;
        $_SESSION['email'] = $user['email'] ?? null;
        $_SESSION['role'] = $user['role'] ?? null;
        $_SESSION['status'] = $user['status'] ?? null;
        $_SESSION['identity_updated_at'] = $user['updated_at'] ?? null;

        $_SESSION['user'] = [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'status' => $_SESSION['status'],
        ];

        $_SESSION['last_activity'] = time();
    }

    public static function check()
    {
        self::start();

        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > self::SESSION_TIMEOUT) {
                self::destroy();
                return false;
            }
        }

        $_SESSION['last_activity'] = time();

        return true;
    }

    public static function id()
    {
        self::start();

        return $_SESSION['user_id'] ?? null;
    }

    public static function user()
    {
        self::start();

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'status' => $_SESSION['status'] ?? null,
        ];
    }

    public static function role()
    {
        self::start();

        return $_SESSION['role'] ?? null;
    }

    public static function refreshIdentity(array $user): void
    {
        self::start();
        foreach (['username', 'full_name', 'email', 'role', 'status'] as $field) {
            if (array_key_exists($field, $user)) $_SESSION[$field] = $user[$field];
        }
        if (array_key_exists('updated_at', $user)) $_SESSION['identity_updated_at'] = $user['updated_at'];
        $_SESSION['user'] = [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'status' => $_SESSION['status'] ?? null,
        ];
    }

    public static function is($role)
    {
        self::start();

        return strtoupper((string)($_SESSION['role'] ?? '')) === strtoupper((string)$role);
    }

    public static function identityUpdatedAt(): ?string
    {
        self::start();
        $value = $_SESSION['identity_updated_at'] ?? null;
        return $value !== null ? (string)$value : null;
    }

    public static function destroy()
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
