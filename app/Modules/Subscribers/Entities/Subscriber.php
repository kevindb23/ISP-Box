<?php

namespace App\Modules\Subscribers\Entities;

class Subscriber
{
    public ?int $id;
    public ?int $account_number;
    public ?string $full_name;
    public ?string $address;
    public ?string $contact_number;
    public ?string $email;
    public ?string $status;
    public ?string $created_at;
    public ?string $updated_at;
    public ?string $deleted_at;

    public ?int $service_id;
    public ?string $ppp_username;
    public ?int $plan_id;
    public ?string $plan_name;
    public ?string $account_type;
    public ?string $service_status;
    public ?string $next_due_date;
    public ?string $expires_at;
    public ?int $service_number;
    public int $service_count;
    public int $online;

    public ?int $provisioning_id;
    public ?int $nap_splitter_port;
    public ?string $ont_serial;
    public ?string $installed_at;
    public ?int $nap_port_id;
    public ?int $ont_id;
    public ?int $olt_port_id;
    public ?string $assigned_at;
    public ?int $cvlan;
    public ?int $svlan;

    public ?int $nap_port_number;
    public ?int $nap_box_id;
    public ?string $nap_name;
    public ?int $lcp_id;
    public ?string $lcp_name;
    public ?int $lcp_port_number;
    public ?string $olt_port_name;
    public ?string $olt_name;
    public ?string $olt_ip_address;
    public ?int $ont_assigned_id;
    public ?string $ont_status;
    public ?string $nap_code;
    public ?string $splitter_model;
    public ?int $splitter_ratio;
    public ?int $provisioning_job_id;
    public ?string $provisioning_job_no;
    public ?string $provisioning_status;
    public ?string $provisioning_date;
    public ?string $activated_at;
    public ?string $acs_status;
    public ?string $acs_wan_ip;
    public ?string $acs_last_seen;

    public function __construct(array $data = [])
    {
        $this->id             = isset($data['id']) ? (int)$data['id'] : null;
        $this->account_number = isset($data['account_number']) ? (int)$data['account_number'] : null;
        $this->full_name      = $data['full_name'] ?? null;
        $this->address        = $data['address'] ?? null;
        $this->contact_number = $data['contact_number'] ?? null;
        $this->email          = $data['email'] ?? null;
        $this->status         = $data['status'] ?? null;
        $this->created_at     = $data['created_at'] ?? null;
        $this->updated_at     = $data['updated_at'] ?? null;
        $this->deleted_at     = $data['deleted_at'] ?? null;

        $this->service_id     = isset($data['service_id']) ? (int)$data['service_id'] : null;
        $this->ppp_username   = $data['ppp_username'] ?? null;
        $this->plan_id        = isset($data['plan_id']) ? (int)$data['plan_id'] : null;
        $this->plan_name      = $data['plan_name'] ?? null;
        $this->account_type   = $data['account_type'] ?? null;
        $this->service_status = $data['service_status'] ?? null;
        $this->next_due_date  = $data['next_due_date'] ?? null;
        $this->expires_at     = $data['expires_at'] ?? null;
        $this->service_number = isset($data['service_number']) ? (int)$data['service_number'] : null;
        $this->service_count = isset($data['service_count']) ? (int)$data['service_count'] : ($this->service_id !== null ? 1 : 0);
        $this->online         = isset($data['online']) ? (int)$data['online'] : 0;

        $this->provisioning_id   = isset($data['provisioning_id']) ? (int)$data['provisioning_id'] : null;
        $this->nap_splitter_port = isset($data['nap_splitter_port']) ? (int)$data['nap_splitter_port'] : null;
        $this->ont_serial        = $data['ont_serial'] ?? null;
        $this->installed_at      = $data['installed_at'] ?? null;
        $this->nap_port_id       = isset($data['nap_port_id']) ? (int)$data['nap_port_id'] : null;
        $this->ont_id            = isset($data['ont_id']) ? (int)$data['ont_id'] : null;
        $this->olt_port_id       = isset($data['olt_port_id']) ? (int)$data['olt_port_id'] : null;
        $this->assigned_at       = $data['assigned_at'] ?? null;
        $this->cvlan             = isset($data['cvlan']) ? (int)$data['cvlan'] : null;
        $this->svlan             = isset($data['svlan']) ? (int)$data['svlan'] : null;

        $this->nap_port_number = isset($data['nap_port_number']) ? (int)$data['nap_port_number'] : null;
        $this->nap_box_id      = isset($data['nap_box_id']) ? (int)$data['nap_box_id'] : null;
        $this->nap_name        = $data['nap_name'] ?? null;
        $this->lcp_id          = isset($data['lcp_id']) ? (int)$data['lcp_id'] : null;
        $this->lcp_name        = $data['lcp_name'] ?? null;
        $this->lcp_port_number = isset($data['lcp_port_number']) ? (int)$data['lcp_port_number'] : null;
        $this->olt_port_name   = $data['olt_port_name'] ?? null;
        $this->olt_name = $data['olt_name'] ?? null;
        $this->olt_ip_address = $data['olt_ip_address'] ?? null;
        $this->ont_assigned_id = isset($data['ont_assigned_id']) ? (int)$data['ont_assigned_id'] : null;
        $this->ont_status = $data['ont_status'] ?? null;
        $this->nap_code = $data['nap_code'] ?? null;
        $this->splitter_model = $data['splitter_model'] ?? null;
        $this->splitter_ratio = isset($data['splitter_ratio']) ? (int)$data['splitter_ratio'] : null;
        $this->provisioning_job_id = isset($data['provisioning_job_id']) ? (int)$data['provisioning_job_id'] : null;
        $this->provisioning_job_no = $data['provisioning_job_no'] ?? null;
        $this->provisioning_status = $data['provisioning_status'] ?? null;
        $this->provisioning_date = $data['provisioning_date'] ?? null;
        $this->activated_at = $data['activated_at'] ?? null;
        $this->acs_status = $data['acs_status'] ?? null;
        $this->acs_wan_ip = $data['acs_wan_ip'] ?? null;
        $this->acs_last_seen = $data['acs_last_seen'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'account_number' => $this->account_number,
            'full_name'      => $this->full_name,
            'address'        => $this->address,
            'contact_number' => $this->contact_number,
            'email'          => $this->email,
            'status'         => $this->status,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'deleted_at'     => $this->deleted_at,

            'service_id'     => $this->service_id,
            'ppp_username'   => $this->ppp_username,
            'plan_id'        => $this->plan_id,
            'plan_name'      => $this->plan_name,
            'account_type'   => $this->account_type,
            'service_status' => $this->service_status,
            'next_due_date'  => $this->next_due_date,
            'expires_at'     => $this->expires_at,
            'service_number' => $this->service_number,
            'service_count'  => $this->service_count,
            'online'         => $this->online,

            'provisioning_id'   => $this->provisioning_id,
            'nap_splitter_port' => $this->nap_splitter_port,
            'ont_serial'        => $this->ont_serial,
            'installed_at'      => $this->installed_at,
            'nap_port_id'       => $this->nap_port_id,
            'ont_id'            => $this->ont_id,
            'olt_port_id'       => $this->olt_port_id,
            'assigned_at'       => $this->assigned_at,
            'cvlan'             => $this->cvlan,
            'svlan'             => $this->svlan,

            'nap_port_number' => $this->nap_port_number,
            'nap_box_id'      => $this->nap_box_id,
            'nap_name'        => $this->nap_name,
            'lcp_id'          => $this->lcp_id,
            'lcp_name'        => $this->lcp_name,
            'lcp_port_number' => $this->lcp_port_number,
            'olt_port_name'   => $this->olt_port_name,
            'olt_name' => $this->olt_name,
            'olt_ip_address' => $this->olt_ip_address,
            'ont_assigned_id' => $this->ont_assigned_id,
            'ont_status' => $this->ont_status,
            'nap_code' => $this->nap_code,
            'splitter_model' => $this->splitter_model,
            'splitter_ratio' => $this->splitter_ratio,
            'provisioning_job_id' => $this->provisioning_job_id,
            'provisioning_job_no' => $this->provisioning_job_no,
            'provisioning_status' => $this->provisioning_status,
            'provisioning_date' => $this->provisioning_date,
            'activated_at' => $this->activated_at,
            'acs_status' => $this->acs_status,
            'acs_wan_ip' => $this->acs_wan_ip,
            // Stable generic alias consumed by the modern subscriber UI.
            'wan_ip' => $this->acs_wan_ip,
            'acs_last_seen' => $this->acs_last_seen,
        ];
    }
}
