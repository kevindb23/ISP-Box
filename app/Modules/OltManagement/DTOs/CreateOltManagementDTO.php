<?php

namespace App\Modules\OltManagement\DTOs;

class CreateOltManagementDTO
{
    public ?string $name = null;
    public ?string $ip_address = null;
    public ?string $username = null;
    public ?string $password = null;
    public ?string $vendor = null;

    public ?int $olt_id = null;
    public ?int $frame = null;
    public ?int $slot = null;
    public ?int $port = null;

    public ?string $board_name = null;
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

    public static function fromDevice(array $data): self
    {
        $dto = new self();
        $dto->name = trim((string)($data['name'] ?? ''));
        $dto->ip_address = trim((string)($data['ip_address'] ?? ''));
        $dto->username = trim((string)($data['username'] ?? ''));
        $dto->password = trim((string)($data['password'] ?? ''));
        $dto->vendor = trim((string)($data['vendor'] ?? ''));

        return $dto;
    }

    public static function fromPort(array $data): self
    {
        $dto = new self();
        $dto->olt_id = (int)($data['olt_id'] ?? 0);
        $dto->frame = isset($data['frame']) && $data['frame'] !== '' ? (int)$data['frame'] : null;
        $dto->slot = isset($data['slot']) && $data['slot'] !== '' ? (int)$data['slot'] : null;
        $dto->port = isset($data['port']) && $data['port'] !== '' ? (int)$data['port'] : null;

        $dto->board_name = trim((string)($data['board_name'] ?? '')) ?: null;
        $dto->board_type = trim((string)($data['board_type'] ?? '')) ?: null;
        $dto->port_type = trim((string)($data['port_type'] ?? '')) ?: null;

        $dto->svlan = (isset($data['svlan']) && $data['svlan'] !== '') ? (int)$data['svlan'] : null;
        $dto->description = trim((string)($data['description'] ?? '')) ?: null;
        $dto->link_status = trim((string)($data['link_status'] ?? '')) ?: null;
        $dto->optic_status = trim((string)($data['optic_status'] ?? '')) ?: null;
        $dto->speed = trim((string)($data['speed'] ?? '')) ?: null;
        $dto->duplex = trim((string)($data['duplex'] ?? '')) ?: null;
        $dto->active_state = trim((string)($data['active_state'] ?? '')) ?: null;
        $dto->ont_count = (isset($data['ont_count']) && $data['ont_count'] !== '') ? (int)$data['ont_count'] : null;

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'username' => $this->username,
            'password' => $this->password,
            'vendor' => $this->vendor,

            'olt_id' => $this->olt_id,
            'frame' => $this->frame,
            'slot' => $this->slot,
            'port' => $this->port,

            'board_name' => $this->board_name,
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
        ];
    }
}
