<?php

namespace App\Presentation\Middleware;

use Core\SessionManager;

class AuthMiddleware
{

    public function handle()
    {

        if (!SessionManager::check()) {

            header("Location: /login");
            exit;

        }

        $timeout = 1800; // 30 minutes

        if (
            isset($_SESSION['LAST_ACTIVITY']) &&
            (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)
        ) {

            SessionManager::destroy();

            header("Location: /login");
            exit;

        }

        $_SESSION['LAST_ACTIVITY'] = time();

    }

}
