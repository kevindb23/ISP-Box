<?php

namespace App\Modules\Subscribers\Policies;

use Framework\SessionManager;

class SubscriberPolicy
{

    /*
    |--------------------------------------------------------------------------
    | Allow Any Logged User
    |--------------------------------------------------------------------------
    */

    private static function allow()
    {
        // AuthMiddleware already ensures login
        return true;
    }


    public static function view()
    {
        return self::allow();
    }

    public static function create()
    {
        return self::allow();
    }

    public static function update()
    {
        return self::allow();
    }

    public static function delete()
    {
        return self::allow();
    }

    public static function suspend()
    {
        return self::allow();
    }

    public static function activate()
    {
        return self::allow();
    }

    public static function resetPassword()
    {
        return self::allow();
    }

}
