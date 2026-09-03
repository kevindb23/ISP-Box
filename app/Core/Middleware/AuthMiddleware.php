<?php

namespace App\Core\Middleware;

use Framework\SessionManager;

class AuthMiddleware
{

    public function handle()
    {

        if (!SessionManager::check()) {

            header("Location: /login");
            exit;

        }

    }

}
