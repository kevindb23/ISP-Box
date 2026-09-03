<?php

namespace App\Modules\Radius\Repositories;

use App\Infrastructure\Security\SecretCipher;
use Framework\DatabaseConnection;
use PDO;
use RuntimeException;

class RadiusSettingsRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection, private SecretCipher $secrets)
    {
        $this->db = $connection->get();
    }

    /**
     * Return the active Radius database connection settings stored in Portal.
     */
    public function getConnectionConfig(): array
    {
        $stmt = $this->db->query("
            SELECT host, db_user, db_password, db_name
            FROM radius_settings
            WHERE is_active = 1
            ORDER BY id ASC
            LIMIT 1
        ");

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('No active Radius database settings are configured.');
        }

        foreach (['host', 'db_user', 'db_password', 'db_name'] as $field) {
            if (trim((string)($row[$field] ?? '')) === '') {
                throw new RuntimeException('Radius database settings are incomplete.');
            }
        }

        return [
            'host' => trim((string)$row['host']),
            'user' => trim((string)$row['db_user']),
            'pass' => (string)$this->secrets->decrypt($row['db_password']),
            'name' => trim((string)$row['db_name']),
        ];
    }

    public function list(): array
    {
        $stmt = $this->db->query("
            SELECT id, host, db_user, db_name, is_active, created_at, updated_at
            FROM radius_settings
            ORDER BY id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, host, db_user, db_password, db_name, is_active, created_at, updated_at
            FROM radius_settings
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['db_password'] = $this->secrets->decrypt($row['db_password'] ?? null);
        return $row;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO radius_settings (host, db_user, db_password, db_name, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['host'],
            $data['db_user'],
            $this->secrets->encrypt((string)$data['db_password']),
            $data['db_name'],
            (int)$data['is_active'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE radius_settings
            SET host = ?, db_user = ?, db_password = ?, db_name = ?, is_active = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['host'],
            $data['db_user'],
            $this->secrets->encrypt((string)$data['db_password']),
            $data['db_name'],
            (int)$data['is_active'],
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM radius_settings WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
