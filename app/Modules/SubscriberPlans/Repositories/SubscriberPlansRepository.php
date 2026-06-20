<?php

namespace App\Modules\SubscriberPlans\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PDOException;

class SubscriberPlansRepository
{
    private PDO $db;
    private PDO $radiusDb;

    public function __construct(DatabaseConnection $database)
    {
        $this->db = $database->get();

        $config = require __DIR__ . '/../../../../config/database.php';
        $radius = $config['radius_db'];

        $this->radiusDb = new PDO(
            "mysql:host={$radius['host']};dbname={$radius['name']};charset=utf8mb4",
            $radius['user'],
            $radius['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function getAll(): array
    {
        return $this->db->query("
            SELECT *
            FROM plans
            ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plans
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByPlanName(string $planName): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plans
            WHERE plan_name = ?
            LIMIT 1
        ");

        $stmt->execute([$planName]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByPlanNameExceptId(string $planName, int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plans
            WHERE plan_name = ?
              AND id <> ?
            LIMIT 1
        ");

        $stmt->execute([$planName, $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO plans (
                    plan_name,
                    price,
                    description,
                    plan_type,
                    validity_days,
                    speed_down,
                    speed_up,
                    is_active,
                    speed_mbps
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['plan_name'],
                $data['price'],
                $data['description'],
                $data['plan_type'],
                $data['validity_days'],
                $data['speed_mbps'],
                $data['speed_mbps'],
                $data['is_active'],
                $data['speed_mbps'],
            ]);

            return [
                'success' => true,
                'id' => (int)$this->db->lastInsertId(),
            ];
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                return [
                    'success' => false,
                    'message' => 'Plan name already exists.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Database error while creating plan.',
            ];
        }
    }

    public function update($id, array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE plans
                SET
                    plan_name = ?,
                    price = ?,
                    description = ?,
                    plan_type = ?,
                    validity_days = ?,
                    speed_down = ?,
                    speed_up = ?,
                    is_active = ?,
                    speed_mbps = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $data['plan_name'],
                $data['price'],
                $data['description'],
                $data['plan_type'],
                $data['validity_days'],
                $data['speed_mbps'],
                $data['speed_mbps'],
                $data['is_active'],
                $data['speed_mbps'],
                $id,
            ]);

            return [
                'success' => true,
            ];
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                return [
                    'success' => false,
                    'message' => 'Plan name already exists.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Database error while updating plan.',
            ];
        }
    }

    public function delete($id): bool
    {
        $cleanup = $this->db->prepare("
            DELETE ss
            FROM subscriber_services ss
            INNER JOIN subscribers s ON s.id = ss.subscriber_id
            WHERE ss.plan_id = ?
              AND ss.status = 'TERMINATED'
              AND s.deleted_at IS NOT NULL
        ");

        $cleanup->execute([$id]);

        $stmt = $this->db->prepare("
            DELETE FROM plans
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public function countActiveUsageByPlanId(int $planId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM subscriber_services ss
            INNER JOIN subscribers s
                ON s.id = ss.subscriber_id
            WHERE ss.plan_id = ?
              AND ss.status IN ('PENDING', 'ACTIVE', 'SUSPENDED')
              AND s.deleted_at IS NULL
        ");

        $stmt->execute([$planId]);

        return (int)$stmt->fetchColumn();
    }

    public function syncRadiusProfile(string $planName, int $speedMbps): bool
    {
        $speedKbps = $speedMbps * 1024;
        $value = $speedKbps . '/' . $speedKbps;

        $delete = $this->radiusDb->prepare("
            DELETE FROM radgroupreply
            WHERE groupname = ?
              AND attribute = 'Filter-Id'
        ");
        $delete->execute([$planName]);

        $insert = $this->radiusDb->prepare("
            INSERT INTO radgroupreply (groupname, attribute, op, value)
            VALUES (?, 'Filter-Id', ':=', ?)
        ");

        return $insert->execute([$planName, $value]);
    }

    public function deleteRadiusProfile(string $planName): bool
    {
        $stmt = $this->radiusDb->prepare("
            DELETE FROM radgroupreply
            WHERE groupname = ?
        ");

        return $stmt->execute([$planName]);
    }
}