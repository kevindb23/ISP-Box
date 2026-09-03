<?php

namespace App\Modules\SystemSettings\DTOs;

class UpdateGeneralSettingsDTO
{
    public function __construct(
        public string $systemName,
        public string $timezone,
        public string $currency,
        public string $dateFormat,
        public string $timeFormat,
        public string $subscriberPrefix,
        public string $servicePrefix,
        public string $jobPrefix,
        public int $invoiceDueDays,
        public int $gracePeriodDays,
        public string $provisioningMode,
        public string $installationAssignmentMode,
        public int $sessionTimeout,
        public int $maxLoginAttempts,
        public int $logRetentionDays,
        public bool $maintenanceMode
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            trim((string)($data['system_name'] ?? 'NexusBox')),
            trim((string)($data['timezone'] ?? 'Asia/Manila')),
            strtoupper(trim((string)($data['currency'] ?? 'PHP'))),
            trim((string)($data['date_format'] ?? 'Y-m-d')),
            trim((string)($data['time_format'] ?? 'H:i:s')),
            strtoupper(trim((string)($data['subscriber_prefix'] ?? 'SUB'))),
            strtoupper(trim((string)($data['service_prefix'] ?? 'SVC'))),
            strtoupper(trim((string)($data['job_prefix'] ?? 'JOB'))),
            (int)($data['invoice_due_days'] ?? 7),
            (int)($data['grace_period_days'] ?? 3),
            strtoupper(trim((string)($data['provisioning_mode'] ?? 'FULL_AUTO'))),
            strtoupper(trim((string)($data['installation_assignment_mode'] ?? 'MANUAL'))),
            (int)($data['session_timeout'] ?? 3600),
            (int)($data['max_login_attempts'] ?? 5),
            (int)($data['log_retention_days'] ?? 30),
            filter_var($data['maintenance_mode'] ?? false, FILTER_VALIDATE_BOOL)
        );
    }

    public function toConfig(): array
    {
        return [
            'system_name' => $this->systemName,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'date_format' => $this->dateFormat,
            'time_format' => $this->timeFormat,
            'subscriber_prefix' => $this->subscriberPrefix,
            'service_prefix' => $this->servicePrefix,
            'job_prefix' => $this->jobPrefix,
            'invoice_due_days' => (string)$this->invoiceDueDays,
            'grace_period_days' => (string)$this->gracePeriodDays,
            'provisioning_mode' => $this->provisioningMode,
            'installation_assignment_mode' => $this->installationAssignmentMode,
            'session_timeout' => (string)$this->sessionTimeout,
            'max_login_attempts' => (string)$this->maxLoginAttempts,
            'log_retention_days' => (string)$this->logRetentionDays,
            'maintenance_mode' => $this->maintenanceMode ? '1' : '0',
        ];
    }
}
