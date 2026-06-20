<?php

namespace App\Modules\CgnatManagement\Entities;

class SubscriberUsage
{
    public ?string $username;
    public ?string $framed_ip;
    public ?string $session_id;
    public ?string $nas_ip;
    public ?string $session_start;
    public ?string $session_stop;
    public ?int $session_time;
    public ?int $service_id;
    public ?string $subscriber_name;
    public ?string $pool_name;
    public ?string $pool_type;
    public ?int $pool_id;

    public function __construct(array $data = [])
    {
        $this->username = isset($data['username']) ? (string)$data['username'] : null;
        $this->framed_ip = isset($data['framed_ip']) ? (string)$data['framed_ip'] : null;
        $this->session_id = isset($data['session_id']) ? (string)$data['session_id'] : null;
        $this->nas_ip = isset($data['nas_ip']) ? (string)$data['nas_ip'] : null;
        $this->session_start = isset($data['session_start']) ? (string)$data['session_start'] : null;
        $this->session_stop = isset($data['session_stop']) ? (string)$data['session_stop'] : null;
        $this->session_time = isset($data['session_time']) ? (int)$data['session_time'] : null;
        $this->service_id = isset($data['service_id']) ? (int)$data['service_id'] : null;
        $this->subscriber_name = isset($data['subscriber_name']) ? (string)$data['subscriber_name'] : null;
        $this->pool_name = isset($data['pool_name']) ? (string)$data['pool_name'] : null;
        $this->pool_type = isset($data['pool_type']) ? (string)$data['pool_type'] : null;
        $this->pool_id = isset($data['pool_id']) ? (int)$data['pool_id'] : null;
    }

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'framed_ip' => $this->framed_ip,
            'session_id' => $this->session_id,
            'nas_ip' => $this->nas_ip,
            'session_start' => $this->session_start,
            'session_stop' => $this->session_stop,
            'session_time' => $this->session_time,
            'service_id' => $this->service_id,
            'subscriber_name' => $this->subscriber_name,
            'pool_name' => $this->pool_name,
            'pool_type' => $this->pool_type,
            'pool_id' => $this->pool_id,
        ];
    }
}