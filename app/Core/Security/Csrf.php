<?php

namespace App\Core\Security;

class Csrf
{
    public static function token()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validate($token)
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        if (!$token) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
