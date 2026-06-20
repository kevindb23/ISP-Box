<?php

namespace App\Modules\SubscriberPlans\SystemSettings\DTOs;

class UpdateGeneralSettingsDTO
{
    public string $system_name;
    public string $timezone;
    public string $currency;
    public string $date_format;
    public string $time_format;

    public string $subscriber_prefix;
    public string $service_prefix;
    public string $job_prefix;

    public int $invoice_due_days;
    public int $grace_period_days;

    public string $provisioning_mode;

    public int $session_timeout;
    public int $max_login_attempts;

    public int $log_retention_days;

    public bool $maintenance_mode;

    public function __construct(array $data)
    {
        $this->system_name = $data['system_name'] ?? 'NexusBox';
        $this->timezone = $data['timezone'] ?? 'Asia/Manila';
        $this->currency = $data['currency'] ?? 'PHP';
        $this->date_format = $data['date_format'] ?? 'Y-m-d';
        $this->time_format = $data['time_format'] ?? 'H:i:s';

        $this->subscriber_prefix = $data['subscriber_prefix'] ?? 'SUB';
        $this->service_prefix = $data['service_prefix'] ?? 'SVC';
        $this->job_prefix = $data['job_prefix'] ?? 'JOB';

        $this->invoice_due_days = (int)($data['invoice_due_days'] ?? 7);
        $this->grace_period_days = (int)($data['grace_period_days'] ?? 3);

        $this->provisioning_mode = $data['provisioning_mode'] ?? 'FULL_AUTO';

        $this->session_timeout = (int)($data['session_timeout'] ?? 3600);
        $this->max_login_attempts = (int)($data['max_login_attempts'] ?? 5);

        $this->log_retention_days = (int)($data['log_retention_days'] ?? 30);

        $this->maintenance_mode = (bool)($data['maintenance_mode'] ?? false);
    }
}