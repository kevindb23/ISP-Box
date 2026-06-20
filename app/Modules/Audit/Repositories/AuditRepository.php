<?php

namespace App\Modules\Audit\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AuditRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function latest(int $limit = 500): array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            user_id,
            username,
            module,
            action,
            description,
            ip_address,
            created_at
        FROM audit_logs
        ORDER BY created_at DESC, id DESC
        LIMIT :limit
    ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM audit_logs
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs
            (
                user_id,
                username,
                module,
                action,
                description,
                ip_address
            )
            VALUES
            (
                :user_id,
                :username,
                :module,
                :action,
                :description,
                :ip_address
            )
        ");

        $stmt->execute([
            'user_id'     => $data['user_id'],
            'username'    => $data['username'],
            'module'      => $data['module'],
            'action'      => $data['action'],
            'description' => $data['description'],
            'ip_address'  => $data['ip_address'],
        ]);
    }
}