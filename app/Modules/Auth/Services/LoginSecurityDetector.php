<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

final class LoginSecurityDetector
{
    public static function isSuspicious(string $username, string $password): bool
    {
        $payload = strtolower($username . "\n" . $password);

        foreach ([
            '/(?:\'|")\s*(?:or|and)\b/i',
            '/\b(?:union\s+select|sleep\s*\(|benchmark\s*\()/i',
            '/(?:--|#|\/\*|\*\/)/',
            '/<\s*script\b|javascript\s*:/i',
            '/;\s*(?:select|insert|update|delete|drop|alter|exec)\b/i',
        ] as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                return true;
            }
        }

        return false;
    }
}
