<?php

namespace App\Core\Authorization;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

final class AuthorizationRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function userIdentity(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, role, status, updated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function decision(int $userId, string $role, string $permission): ?bool
    {
        $stmt = $this->db->prepare(
            'SELECT upo.effect
             FROM user_permission_overrides upo
             JOIN permissions p ON p.id = upo.permission_id
             WHERE upo.user_id = :user_id AND p.permission_key = :permission
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'permission' => $permission]);
        $effect = $stmt->fetchColumn();
        if ($effect !== false) return strtoupper((string)$effect) === 'ALLOW';

        $stmt = $this->db->prepare(
            'SELECT 1 FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.code = :role AND p.permission_key = :permission LIMIT 1'
        );
        $stmt->execute(['role' => strtoupper($role), 'permission' => $permission]);
        return $stmt->fetchColumn() !== false;
    }

    public function matrixForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.permission_key, p.module_key, p.action_key, p.label, p.description,
                    p.is_sensitive, IF(rp.permission_id IS NULL, 0, 1) role_allowed,
                    COALESCE(upo.effect, 'INHERIT') override_effect
             FROM users u
             CROSS JOIN permissions p
             LEFT JOIN roles r ON r.code = u.role
             LEFT JOIN role_permissions rp ON rp.role_id = r.id AND rp.permission_id = p.id
             LEFT JOIN user_permission_overrides upo ON upo.user_id = u.id AND upo.permission_id = p.id
             WHERE u.id = :user_id
             ORDER BY p.sort_order, p.id"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replaceOverrides(int $userId, array $effects, int $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare('DELETE FROM user_permission_overrides WHERE user_id = :user_id');
            $delete->execute(['user_id' => $userId]);
            $insert = $this->db->prepare(
                'INSERT INTO user_permission_overrides (user_id, permission_id, effect, created_by)
                 SELECT :user_id, id, :effect, :actor_id FROM permissions WHERE permission_key = :permission'
            );
            foreach ($effects as $permission => $effect) {
                if (!in_array($effect, ['ALLOW', 'DENY'], true)) continue;
                $insert->execute(['user_id' => $userId, 'effect' => $effect, 'actor_id' => $actorId, 'permission' => $permission]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function activeSuperadminCountExcluding(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = 'SUPERADMIN' AND status = 'ACTIVE' AND id <> :id");
        $stmt->execute(['id' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}
