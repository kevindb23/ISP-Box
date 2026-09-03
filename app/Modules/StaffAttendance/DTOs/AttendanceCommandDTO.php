<?php

namespace App\Modules\StaffAttendance\DTOs;

final class AttendanceCommandDTO
{
    public function __construct(
        public string $status = '',
        public string $ipAddress = '',
        public string $userAgent = ''
    ) {
        $this->status = strtoupper(trim($this->status));
        $this->ipAddress = trim($this->ipAddress);
    }

    public static function fromRequest(array $input = []): self
    {
        return new self(
            status: (string)($input['status'] ?? ''),
            ipAddress: (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            userAgent: (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
        );
    }
}
