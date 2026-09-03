<?php

namespace App\Modules\BngManagement\Entities;

class BngSetting
{
    public ?int $id = null;
    public int $enabled = 1;
    public string $host = '';
    public int $port = 22;
    public string $username = '';
    public string $auth_type = 'PASSWORD';
    public ?string $password = null;
    public ?string $known_host_key = null;
    public ?string $host_key_fingerprint = null;
    public ?string $ssh_key_path = null;
    public ?string $preferred_interface = null;

    // ✅ NEW FIELDS
    public ?string $bng_parent_interface = null;
    public int $auto_create_svlan_interface = 1;
    public string $vlan_mode = 'QINQ';

    public ?string $remarks = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function __construct(array $data = [])
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->enabled = isset($data['enabled']) ? (int)$data['enabled'] : 1;
        $this->host = trim((string)($data['host'] ?? ''));
        $this->port = isset($data['port']) ? (int)$data['port'] : 22;
        $this->username = trim((string)($data['username'] ?? ''));
        $this->auth_type = strtoupper(trim((string)($data['auth_type'] ?? 'PASSWORD')));
        $this->password = array_key_exists('password', $data) ? $data['password'] : null;
        $this->known_host_key = isset($data['known_host_key']) ? (string)$data['known_host_key'] : null;
        $this->host_key_fingerprint = isset($data['host_key_fingerprint']) ? (string)$data['host_key_fingerprint'] : null;
        $this->ssh_key_path = isset($data['ssh_key_path']) ? trim((string)$data['ssh_key_path']) : null;
        $this->preferred_interface = isset($data['preferred_interface']) ? trim((string)$data['preferred_interface']) : null;

        // ✅ NEW FIELDS INIT
        $this->bng_parent_interface = isset($data['bng_parent_interface'])
            ? trim((string)$data['bng_parent_interface'])
            : null;

        $this->auto_create_svlan_interface = isset($data['auto_create_svlan_interface'])
            ? (int)$data['auto_create_svlan_interface']
            : 1;

        $this->vlan_mode = strtoupper(trim((string)($data['vlan_mode'] ?? 'QINQ')));

        $this->remarks = isset($data['remarks']) ? trim((string)$data['remarks']) : null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    public function toArray(bool $includeSecrets = false): array
    {
        $row = [
            'id' => $this->id,
            'enabled' => $this->enabled,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'auth_type' => $this->auth_type,
            'ssh_key_path' => $this->ssh_key_path,
            'preferred_interface' => $this->preferred_interface,

            // ✅ NEW FIELDS OUTPUT
            'bng_parent_interface' => $this->bng_parent_interface,
            'auto_create_svlan_interface' => $this->auto_create_svlan_interface,
            'vlan_mode' => $this->vlan_mode,

            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'has_password' => $this->password !== null && $this->password !== '',
            'host_key_fingerprint' => $this->host_key_fingerprint,
            'host_key_trusted' => $this->known_host_key !== null && $this->known_host_key !== '',
        ];

        if ($includeSecrets) {
            $row['password'] = $this->password;
            $row['known_host_key'] = $this->known_host_key;
        }

        return $row;
    }
}
