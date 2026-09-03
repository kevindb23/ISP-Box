<?php

namespace Framework;

final class AuthenticatedApiIdentity
{
    private static ?array $identity = null;

    public static function set(array $identity): void
    {
        self::$identity = $identity;
    }

    public static function get(): ?array
    {
        return self::$identity;
    }

    public static function clear(): void
    {
        self::$identity = null;
    }

    public static function hasScope(string $scope): bool
    {
        $scopes = self::$identity['scopes'] ?? [];
        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
