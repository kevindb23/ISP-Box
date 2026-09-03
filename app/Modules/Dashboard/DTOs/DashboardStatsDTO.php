<?php

namespace App\Modules\Dashboard\DTOs;

class DashboardStatsDTO
{
    public int $subscribers;
    public int $active_services;
    public int $suspended_services;
    public int $unpaid_invoices;
    public int $unpaid_subscribers;
    public int $paid_invoices;
    public float $today_revenue;
    public int $active_sessions;
    public int $online_onts;
    public int $offline_onts;
    public int $available_nap_ports;
    public int $used_nap_ports;
    public int $provisioning_success;
    public int $provisioning_failed;
    public int $provisioning_in_progress;

    public function __construct(array $data = [])
    {
        $this->subscribers = (int)($data['subscribers'] ?? 0);
        $this->active_services = (int)($data['active_services'] ?? 0);
        $this->suspended_services = (int)($data['suspended_services'] ?? 0);
        $this->unpaid_invoices = (int)($data['unpaid_invoices'] ?? 0);
        $this->unpaid_subscribers = (int)($data['unpaid_subscribers'] ?? 0);
        $this->paid_invoices = (int)($data['paid_invoices'] ?? 0);
        $this->today_revenue = (float)($data['today_revenue'] ?? 0);
        $this->active_sessions = (int)($data['active_sessions'] ?? 0);
        $this->online_onts = (int)($data['online_onts'] ?? 0);
        $this->offline_onts = (int)($data['offline_onts'] ?? 0);
        $this->available_nap_ports = (int)($data['available_nap_ports'] ?? 0);
        $this->used_nap_ports = (int)($data['used_nap_ports'] ?? 0);
        $this->provisioning_success = (int)($data['provisioning_success'] ?? 0);
        $this->provisioning_failed = (int)($data['provisioning_failed'] ?? 0);
        $this->provisioning_in_progress = (int)($data['provisioning_in_progress'] ?? 0);
    }

    public function toArray(): array
    {
        return [
            'subscribers' => $this->subscribers,
            'active_services' => $this->active_services,
            'suspended_services' => $this->suspended_services,
            'unpaid_invoices' => $this->unpaid_invoices,
            'unpaid_subscribers' => $this->unpaid_subscribers,
            'paid_invoices' => $this->paid_invoices,
            'today_revenue' => $this->today_revenue,
            'active_sessions' => $this->active_sessions,
            'online_onts' => $this->online_onts,
            'offline_onts' => $this->offline_onts,
            'available_nap_ports' => $this->available_nap_ports,
            'used_nap_ports' => $this->used_nap_ports,
            'provisioning_success' => $this->provisioning_success,
            'provisioning_failed' => $this->provisioning_failed,
            'provisioning_in_progress' => $this->provisioning_in_progress,
        ];
    }
}
