<?php

namespace App\Modules\ScheduledDowntime\Repositories;

use Framework\DatabaseConnection;
use PDO;

class ScheduledDowntimeRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function all(): array
    {
        return $this->db->query(
            'SELECT id, title, message, starts_at, ends_at, enabled, created_by, created_at, updated_at
             FROM scheduled_downtime
             ORDER BY starts_at DESC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, message, starts_at, ends_at, enabled, created_by, created_at, updated_at
             FROM scheduled_downtime WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data, int $createdBy): ?array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO scheduled_downtime (title, message, starts_at, ends_at, enabled, created_by)
             VALUES (:title, :message, :starts_at, :ends_at, :enabled, :created_by)'
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':starts_at' => $data['starts_at'],
            ':ends_at' => $data['ends_at'],
            ':enabled' => $data['enabled'],
            ':created_by' => $createdBy,
        ]);

        return $this->find((int)$this->db->lastInsertId());
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE scheduled_downtime
             SET title = :title, message = :message, starts_at = :starts_at,
                 ends_at = :ends_at, enabled = :enabled, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':starts_at' => $data['starts_at'],
            ':ends_at' => $data['ends_at'],
            ':enabled' => $data['enabled'],
        ]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM scheduled_downtime WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function toggle(int $id, bool $enabled): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE scheduled_downtime SET enabled = :enabled, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([':id' => $id, ':enabled' => $enabled ? 1 : 0]);

        return $this->find($id);
    }

    public function activeWindow(string $now): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, message, starts_at, ends_at, enabled, created_by, created_at, updated_at
             FROM scheduled_downtime
             WHERE enabled = 1 AND starts_at <= :now AND ends_at > :now
             ORDER BY starts_at ASC, id ASC LIMIT 1'
        );
        $stmt->execute([':now' => $now]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
