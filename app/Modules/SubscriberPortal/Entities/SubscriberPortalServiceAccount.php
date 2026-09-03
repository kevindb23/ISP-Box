<?php

namespace App\Modules\SubscriberPortal\Entities;

class SubscriberPortalServiceAccount
{
    public int $id;
    public int $subscriber_id;
    public ?int $service_number;
    public ?string $ppp_username;
    public ?int $plan_id;
    public ?string $plan_name;
    public ?float $price;
    public ?int $speed_down;
    public ?int $speed_up;
    public ?int $speed_mbps;
    public string $account_type;
    public string $status;
    public ?string $next_due_date;
    public ?string $expires_at;
    public ?string $created_at;
    public ?string $updated_at;

    public ?string $ont_serial;
    public ?int $cvlan;
    public ?int $svlan;
    public ?int $ont_assigned_id;
    public ?int $pppoe_service_port;
    public ?int $tr069_service_port;
    public ?string $activated_at;

    public ?int $network_box_id;
    public ?int $splitter_id;
    public ?int $splitter_output_port_id;
    public ?string $nap_box_code;
    public ?string $nap_name;
    public ?string $nap_location;
    public ?string $nap_address;
    public ?string $nap_box_port;
    public ?string $nap_box_port_status;

    public ?string $olt_name;
    public ?string $olt_port_label;
    public ?string $acs_status;
    public ?string $wan_ip;
    public ?string $acs_last_seen;
    public ?string $acs_firmware_version;

    public function __construct(array $data = [])
    {
        $this->id = (int)($data['id'] ?? $data['service_id'] ?? 0);
        $this->subscriber_id = (int)($data['subscriber_id'] ?? 0);

        $this->service_number = isset($data['service_number'])
            ? (int)$data['service_number']
            : null;

        $this->ppp_username = $data['ppp_username'] ?? null;

        $this->plan_id = isset($data['plan_id'])
            ? (int)$data['plan_id']
            : null;

        $this->plan_name = $data['plan_name'] ?? null;

        $this->price = isset($data['price'])
            ? (float)$data['price']
            : (isset($data['plan_price']) ? (float)$data['plan_price'] : null);

        $this->speed_down = isset($data['speed_down']) ? (int)$data['speed_down'] : null;
        $this->speed_up = isset($data['speed_up']) ? (int)$data['speed_up'] : null;
        $this->speed_mbps = isset($data['speed_mbps']) ? (int)$data['speed_mbps'] : null;

        $this->account_type = strtoupper((string)($data['account_type'] ?? 'POSTPAID'));
        $this->status = strtoupper((string)($data['status'] ?? $data['service_status'] ?? 'PENDING'));

        $this->next_due_date = $data['next_due_date'] ?? null;
        $this->expires_at = $data['expires_at'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;

        $this->ont_serial = $data['ont_serial'] ?? null;
        $this->cvlan = isset($data['cvlan']) ? (int)$data['cvlan'] : null;
        $this->svlan = isset($data['svlan']) ? (int)$data['svlan'] : null;
        $this->ont_assigned_id = isset($data['ont_assigned_id']) ? (int)$data['ont_assigned_id'] : null;
        $this->pppoe_service_port = isset($data['pppoe_service_port']) ? (int)$data['pppoe_service_port'] : null;
        $this->tr069_service_port = isset($data['tr069_service_port']) ? (int)$data['tr069_service_port'] : null;
        $this->activated_at = $data['activated_at'] ?? null;

        $this->network_box_id = isset($data['network_box_id']) ? (int)$data['network_box_id'] : null;
        $this->splitter_id = isset($data['splitter_id']) ? (int)$data['splitter_id'] : null;
        $this->splitter_output_port_id = isset($data['splitter_output_port_id']) ? (int)$data['splitter_output_port_id'] : null;

        $this->nap_box_code = $data['nap_box_code'] ?? null;
        $this->nap_name = $data['nap_name'] ?? null;
        $this->nap_location = $data['nap_location'] ?? null;
        $this->nap_address = $data['nap_address'] ?? null;

        $this->nap_box_port = isset($data['nap_box_port'])
            ? (string)$data['nap_box_port']
            : null;

        $this->nap_box_port_status = isset($data['nap_box_port_status'])
            ? strtoupper((string)$data['nap_box_port_status'])
            : null;

        $this->olt_name = $data['olt_name'] ?? null;
        $this->olt_port_label = $data['olt_port_label'] ?? null;
        $this->acs_status = isset($data['acs_status']) ? strtoupper((string)$data['acs_status']) : null;
        $this->wan_ip = $data['wan_ip'] ?? null;
        $this->acs_last_seen = $data['acs_last_seen'] ?? null;
        $this->acs_firmware_version = $data['acs_firmware_version'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->id,
            'subscriber_id' => $this->subscriber_id,

            'service_number' => $this->service_number,
            'ppp_username' => $this->ppp_username,

            'plan_id' => $this->plan_id,
            'plan_name' => $this->plan_name,
            'price' => $this->price,
            'speed_down' => $this->speed_down,
            'speed_up' => $this->speed_up,
            'speed_mbps' => $this->speed_mbps,

            'account_type' => $this->account_type,
            'status' => $this->status,
            'next_due_date' => $this->next_due_date,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'ont_serial' => $this->ont_serial,
            'cvlan' => $this->cvlan,
            'svlan' => $this->svlan,
            'ont_assigned_id' => $this->ont_assigned_id,
            'pppoe_service_port' => $this->pppoe_service_port,
            'tr069_service_port' => $this->tr069_service_port,
            'activated_at' => $this->activated_at,

            'network_box_id' => $this->network_box_id,
            'splitter_id' => $this->splitter_id,
            'splitter_output_port_id' => $this->splitter_output_port_id,

            'nap_box_code' => $this->nap_box_code,
            'nap_name' => $this->nap_name,
            'nap_location' => $this->nap_location,
            'nap_address' => $this->nap_address,
            'nap_box_port' => $this->nap_box_port,
            'nap_box_port_status' => $this->nap_box_port_status,

            'olt_name' => $this->olt_name,
            'olt_port_label' => $this->olt_port_label,
            'acs_status' => $this->acs_status,
            'wan_ip' => $this->wan_ip,
            'acs_last_seen' => $this->acs_last_seen,
            'acs_firmware_version' => $this->acs_firmware_version,
        ];
    }
}
