<?php

namespace App\Core\Authorization;

final class AuthorizationContext
{
    private static ?AuthorizationService $service = null;
    private static int $userId = 0;

    public static function set(AuthorizationService $service, int $userId): void
    {
        self::$service = $service;
        self::$userId = $userId;
    }

    public static function can(string $permission): bool
    {
        return self::$service !== null && self::$userId > 0 && self::$service->can(self::$userId, $permission);
    }
}
