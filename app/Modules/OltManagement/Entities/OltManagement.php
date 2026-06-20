<?php

namespace App\Modules\OltManagement\Entities;

class OltManagement
{
    public ?int $id = null;

    public ?string $name = null;
    public ?string $ip_address = null;
    public ?string $username = null;
    public ?string $password = null;
    public ?string $vendor = null;
    public ?string $created_at = null;

    public ?int $olt_id = null;
    public ?string $olt_name = null;
    public ?int $frame = null;
    public ?int $slot = null;
    public ?int $port = null;
    public ?string $port_path = null;

    public ?string $board_name = null;
    public ?string $board_status = null;
    public ?string $board_type = null;
    public ?string $port_type = null;

    public ?int $svlan = null;
    public ?string $description = null;

    public ?string $link_status = null;
    public ?string $optic_status = null;
    public ?string $speed = null;
    public ?string $duplex = null;
    public ?string $active_state = null;

    public ?int $ont_count = null;
    public ?int $ont_online = null;

    public array $allowed_svlans = [];
    public ?string $allowed_svlans_csv = null;

    public function __construct(array $data = [])
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;

        $this->name = $data['name'] ?? null;
        $this->ip_address = $data['ip_address'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->password = $data['password'] ?? null;
        $this->vendor = $data['vendor'] ?? null;
        $this->created_at = $data['created_at'] ?? null;

        $this->olt_id = isset($data['olt_id']) ? (int)$data['olt_id'] : null;
        $this->olt_name = $data['olt_name'] ?? null;
        $this->frame = isset($data['frame']) ? (int)$data['frame'] : null;
        $this->slot = isset($data['slot']) ? (int)$data['slot'] : null;
        $this->port = isset($data['port']) ? (int)$data['port'] : null;
        $this->port_path = $data['port_path'] ?? null;

        $this->board_name = $data['board_name'] ?? null;
        $this->board_status = $data['board_status'] ?? null;
        $this->board_type = $data['board_type'] ?? null;
        $this->port_type = $data['port_type'] ?? null;

        $this->svlan = isset($data['svlan']) && $data['svlan'] !== null ? (int)$data['svlan'] : null;
        $this->description = $data['description'] ?? null;

        $this->link_status = $data['link_status'] ?? null;
        $this->optic_status = $data['optic_status'] ?? null;
        $this->speed = $data['speed'] ?? null;
        $this->duplex = $data['duplex'] ?? null;
        $this->active_state = $data['active_state'] ?? null;

        $this->ont_count = isset($data['ont_count']) && $data['ont_count'] !== null ? (int)$data['ont_count'] : null;
        $this->ont_online = isset($data['ont_online']) && $data['ont_online'] !== null ? (int)$data['ont_online'] : null;

        $this->allowed_svlans = isset($data['allowed_svlans']) && is_array($data['allowed_svlans'])
            ? array_map('intval', $data['allowed_svlans'])
            : [];

        $this->allowed_svlans_csv = $data['allowed_svlans_csv'] ?? (
            !empty($this->allowed_svlans) ? implode(',', $this->allowed_svlans) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'username' => $this->username,
            'password' => $this->password,
            'vendor' => $this->vendor,
            'created_at' => $this->created_at,

            'olt_id' => $this->olt_id,
            'olt_name' => $this->olt_name,
            'frame' => $this->frame,
            'slot' => $this->slot,
            'port' => $this->port,
            'port_path' => $this->port_path,

            'board_name' => $this->board_name,
            'board_status' => $this->board_status,
            'board_type' => $this->board_type,
            'port_type' => $this->port_type,

            'svlan' => $this->svlan,
            'description' => $this->description,

            'link_status' => $this->link_status,
            'optic_status' => $this->optic_status,
            'speed' => $this->speed,
            'duplex' => $this->duplex,
            'active_state' => $this->active_state,

            'ont_count' => $this->ont_count,
            'ont_online' => $this->ont_online,

            'allowed_svlans' => $this->allowed_svlans,
            'allowed_svlans_csv' => $this->allowed_svlans_csv,
        ];
    }
}
