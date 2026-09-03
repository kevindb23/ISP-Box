<?php

namespace App\Core\Authorization;

final class AuthorizationService
{
    public function __construct(
        private AuthorizationRepository $repository,
        private PermissionResolver $resolver
    ) {}

    public function authorizeRoute(int $userId, string $method, string $uri): bool
    {
        $identity = $this->repository->userIdentity($userId);
        if (!$identity || strtoupper((string)$identity['status']) !== 'ACTIVE') return false;
        if (strtoupper((string)$identity['role']) === 'SUPERADMIN') return true;
        $permission = $this->resolver->forRoute($method, $uri);
        return $permission === null || $this->can($userId, $permission, $identity);
    }

    public function identity(int $userId): ?array
    {
        return $this->repository->userIdentity($userId);
    }

    public function can(int $userId, string $permission, ?array $identity = null): bool
    {
        $identity ??= $this->repository->userIdentity($userId);
        if (!$identity || strtoupper((string)$identity['status']) !== 'ACTIVE') return false;
        if (strtoupper((string)$identity['role']) === 'SUPERADMIN') return true;
        return $this->repository->decision($userId, (string)$identity['role'], $permission) === true;
    }

    public function matrix(int $userId): array
    {
        $known = array_flip($this->resolver->knownPermissions());
        $rows = array_filter(
            $this->repository->matrixForUser($userId),
            static fn(array $row): bool => isset($known[(string)$row['permission_key']])
        );
        return array_values(array_map(static function (array $row): array {
            $override = strtoupper((string)$row['override_effect']);
            $roleAllowed = (bool)$row['role_allowed'];
            $row['role_allowed'] = $roleAllowed;
            $row['override_effect'] = $override;
            $row['effective_allowed'] = $override === 'ALLOW' || ($override === 'INHERIT' && $roleAllowed);
            $row['is_sensitive'] = (bool)$row['is_sensitive'];
            return $row;
        }, $rows));
    }

    public function replaceOverrides(int $userId, array $effects, int $actorId): void
    {
        $this->repository->replaceOverrides($userId, $effects, $actorId);
    }
}
