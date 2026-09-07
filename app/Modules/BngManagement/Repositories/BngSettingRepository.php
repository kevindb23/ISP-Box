<?php

namespace App\Modules\BngManagement\Repositories;

use App\Infrastructure\Security\SecretCipher;
use App\Modules\Radius\Repositories\RadiusSettingsRepository;
use PDO;

class BngSettingRepository
{
    private PDO $db;

    public function __construct(
        PDO $db,
        private SecretCipher $secrets,
        private RadiusSettingsRepository $radiusSettings
    )
    {
        $this->db = $db;
    }

    public function get(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM bng_settings ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;
        $row['password'] = $this->secrets->decrypt($row['password'] ?? null);
        return $row;
    }

    public function getMetadata(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM bng_settings ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['password'] = null;
        $row['host_key_trusted'] = !empty($row['known_host_key']);
        unset($row['known_host_key']);
        return $row;
    }

    public function list(): array
    {
        $rows = $this->db->query('SELECT * FROM bng_settings ORDER BY enabled DESC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['password'] = null;
            $row['host_key_trusted'] = !empty($row['known_host_key']);
        }
        unset($row);
        return $rows;
    }

    public function save(array $data): int
    {
        $existing = isset($data['id']) ? $this->findRaw((int)$data['id']) : null;

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

            $payload['password'] = $this->secrets->encrypt($payload['password']);

            $payload['id'] = (int)$existing['id'];

            $stmt = $this->db->prepare('
                UPDATE bng_settings
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
            if(trim((string)$existing['host'])!==$payload['host']||(int)$existing['port']!==$payload['port'])$this->clearTrustedHostKey((int)$existing['id']);

            return (int)$existing['id'];
        }

        $payload['password'] = $this->secrets->encrypt($payload['password']);

        $stmt = $this->db->prepare('
            INSERT INTO bng_settings (
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

    public function delete(): bool
    {
        return $this->db->exec('DELETE FROM bng_settings') !== false;
    }
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bng_settings WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['password'] = $this->secrets->decrypt($row['password'] ?? null);
        return $row;
    }

    private function findRaw(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bng_settings WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function deleteById(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM bng_settings WHERE id=:id');
        $stmt->execute([':id'=>$id]);
        return $stmt->rowCount() > 0;
    }
    public function trustHostKey(int $id,string $key,string $fingerprint): void { $stmt=$this->db->prepare('UPDATE bng_settings SET known_host_key=:key,host_key_fingerprint=:fingerprint WHERE id=:id');$stmt->execute([':id'=>$id,':key'=>$key,':fingerprint'=>$fingerprint]); }
    private function clearTrustedHostKey(int $id): void { $stmt=$this->db->prepare('UPDATE bng_settings SET known_host_key=NULL,host_key_fingerprint=NULL WHERE id=:id');$stmt->execute([':id'=>$id]); }

    public function recordDesiredInterface(int $vlanId, string $interface): void
    {
        $stmt=$this->db->prepare('SELECT id FROM bng_vlan_interfaces WHERE interface=:interface LIMIT 1');
        $stmt->execute([':interface'=>$interface]); $id=$stmt->fetchColumn();
        if($id){$update=$this->db->prepare("UPDATE bng_vlan_interfaces SET vlan_id=:vlan,status='UP' WHERE id=:id");$update->execute([':vlan'=>$vlanId,':id'=>$id]);return;}
        $insert=$this->db->prepare("INSERT INTO bng_vlan_interfaces(vlan_id,interface,status) VALUES(:vlan,:interface,'UP')");
        $insert->execute([':vlan'=>$vlanId,':interface'=>$interface]);
    }

    public function desiredInterfaces(): array
    {
        return $this->db->query("SELECT vlan_id,interface,status FROM bng_vlan_interfaces WHERE status='UP' ORDER BY LENGTH(interface),interface")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function removeDesiredInterface(string $interface): void { $stmt=$this->db->prepare('DELETE FROM bng_vlan_interfaces WHERE interface=:interface');$stmt->execute([':interface'=>$interface]); }

    public function activeSubscriberMap(): array
    {
        $radius = $this->radiusSettings->getConnectionConfig();
        $radiusDb = new PDO(
            "mysql:host={$radius['host']};dbname={$radius['name']};charset=utf8mb4",
            $radius['user'],
            $radius['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $stmt = $radiusDb->query("
            SELECT username, framedipaddress AS framed_ip
            FROM radacct
            WHERE framedipaddress IS NOT NULL
              AND framedipaddress <> ''
              AND acctstoptime IS NULL
            ORDER BY radacctid DESC
        ");
        $radiusRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $usernames = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['username'] ?? '')),
            $radiusRows
        ))));
        $subscriberByUsername = [];
        if ($usernames) {
            $placeholders = implode(',', array_fill(0, count($usernames), '?'));
            $portalStmt = $this->db->prepare("
                SELECT ss.ppp_username, ss.id AS service_id, s.full_name AS subscriber_name
                FROM subscriber_services ss
                INNER JOIN subscribers s ON s.id = ss.subscriber_id
                WHERE ss.ppp_username IN ({$placeholders})
            ");
            $portalStmt->execute($usernames);
            foreach ($portalStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $subscriber) {
                $subscriberByUsername[(string)$subscriber['ppp_username']] = $subscriber;
            }
        }

        $map = [];
        foreach ($radiusRows as $row) {
            $ip = trim((string)($row['framed_ip'] ?? ''));
            if ($ip === '') continue;
            $username = trim((string)($row['username'] ?? ''));
            $subscriber = $subscriberByUsername[$username] ?? [];
            if (isset($map[$ip])) continue;
            $map[$ip] = [
                'username' => $username ?: null,
                'service_id' => isset($subscriber['service_id']) ? (int)$subscriber['service_id'] : null,
                'subscriber_name' => $subscriber['subscriber_name'] ?? null,
            ];
        }
        return $map;
    }
}
