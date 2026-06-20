<?php

namespace App\Modules\CgnatManagement\Repositories;

use PDO;

class BngSettingRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function get(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM cgnat_bng_settings ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(array $data): int
    {
        $existing = $this->get();

        $payload = [
            'enabled' => (int)($data['enabled'] ?? 1),
            'host' => trim((string)($data['host'] ?? '')),
            'port' => (int)($data['port'] ?? 22),
            'username' => trim((string)($data['username'] ?? '')),
            'auth_type' => strtoupper(trim((string)($data['auth_type'] ?? 'PASSWORD'))),
            'password' => $data['password'] ?? null,
            'ssh_key_path' => $data['ssh_key_path'] ?? null,
            'preferred_interface' => $data['preferred_interface'] ?? null,
            'bng_parent_interface' => $data['bng_parent_interface'] ?? null,
            'auto_create_svlan_interface' => (int)($data['auto_create_svlan_interface'] ?? 1),
            'vlan_mode' => strtoupper(trim((string)($data['vlan_mode'] ?? 'QINQ'))),
            'remarks' => $data['remarks'] ?? null,
        ];

        if ($existing) {
            if ($payload['password'] === null || $payload['password'] === '') {
                $payload['password'] = $existing['password'] ?? null;
            }

            $payload['id'] = (int)$existing['id'];

            $stmt = $this->db->prepare('
                UPDATE cgnat_bng_settings
                SET
                    enabled = :enabled,
                    host = :host,
                    port = :port,
                    username = :username,
                    auth_type = :auth_type,
                    password = :password,
                    ssh_key_path = :ssh_key_path,
                    preferred_interface = :preferred_interface,
                    bng_parent_interface = :bng_parent_interface,
                    auto_create_svlan_interface = :auto_create_svlan_interface,
                    vlan_mode = :vlan_mode,
                    remarks = :remarks
                WHERE id = :id
            ');

            $stmt->execute($payload);

            return (int)$existing['id'];
        }

        $stmt = $this->db->prepare('
            INSERT INTO cgnat_bng_settings (
                enabled,
                host,
                port,
                username,
                auth_type,
                password,
                ssh_key_path,
                preferred_interface,
                bng_parent_interface,
                auto_create_svlan_interface,
                vlan_mode,
                remarks
            ) VALUES (
                :enabled,
                :host,
                :port,
                :username,
                :auth_type,
                :password,
                :ssh_key_path,
                :preferred_interface,
                :bng_parent_interface,
                :auto_create_svlan_interface,
                :vlan_mode,
                :remarks
            )
        ');

        $stmt->execute($payload);

        return (int)$this->db->lastInsertId();
    }
}