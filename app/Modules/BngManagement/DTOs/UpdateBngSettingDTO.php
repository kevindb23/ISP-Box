<?php

namespace App\Modules\BngManagement\DTOs;

class UpdateBngSettingDTO
{
    public int $enabled;
    public string $host;
    public int $port;
    public string $username;
    public string $auth_type;
    public ?string $password;
    public ?string $ssh_key_path;
    public ?string $preferred_interface;

    public ?string $bng_parent_interface;
    public int $auto_create_svlan_interface;
    public string $vlan_mode;

    public ?string $remarks;

    public function __construct(array $data = [])
    {
        $this->enabled = isset($data['enabled']) ? (int)$data['enabled'] : 1;
        $this->host = trim((string)($data['host'] ?? ''));
        $this->port = isset($data['port']) ? (int)$data['port'] : 22;
        $this->username = trim((string)($data['username'] ?? ''));
        $this->auth_type = 'PASSWORD';
        $this->password = isset($data['password']) ? trim((string)$data['password']) : null;
        $this->ssh_key_path = null;
        $this->preferred_interface = isset($data['preferred_interface']) ? trim((string)$data['preferred_interface']) : null;

        $this->bng_parent_interface = isset($data['bng_parent_interface'])
            ? trim((string)$data['bng_parent_interface'])
            : null;

        $this->auto_create_svlan_interface = 1;

        $this->vlan_mode = 'QINQ';

        $this->remarks = isset($data['remarks']) ? trim((string)$data['remarks']) : null;
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'auth_type' => $this->auth_type,
            'password' => $this->password,
            'ssh_key_path' => $this->ssh_key_path,
            'preferred_interface' => $this->preferred_interface,

            'bng_parent_interface' => $this->bng_parent_interface,
            'auto_create_svlan_interface' => $this->auto_create_svlan_interface,
            'vlan_mode' => $this->vlan_mode,

            'remarks' => $this->remarks,
        ];
    }
}
