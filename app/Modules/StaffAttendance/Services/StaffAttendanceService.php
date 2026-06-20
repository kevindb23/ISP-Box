<?php

namespace App\Modules\StaffAttendance\Services;

use App\Modules\StaffAttendance\Repositories\StaffAttendanceRepository;
use Exception;

class StaffAttendanceService
{
    private StaffAttendanceRepository $repo;

    private array $allowedStatuses = [
        'AVAILABLE',
        'BUSY',
        'ON_BREAK',
        'ON_SITE',
        'TRAVELING',
    ];

    private array $adminRoles = [
        'SUPERADMIN',
        'ADMIN',
        'ADMINISTRATOR',
    ];

    public function __construct(StaffAttendanceRepository $repo)
    {
        $this->repo = $repo;
    }

    public function today(array $sessionData): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $role = strtoupper((string)($sessionData['role'] ?? ''));

        $canViewTeam = in_array($role, $this->adminRoles, true);

        $payload = [
            'attendance' => $this->repo->findTodayAttendance($userId),
            'my_logs' => $this->repo->getUserTodayLogs($userId),
            'can_view_team' => $canViewTeam,
        ];

        if ($canViewTeam) {
            $payload['staff'] = $this->repo->getTodayStaff();
            $payload['logs'] = $this->repo->getTodayLogs();
        } else {
            $payload['staff'] = [];
            $payload['logs'] = [];
        }

        return $payload;
    }

    public function timeIn(array $sessionData, array $requestMeta): array
    {
        $this->requireStaff($sessionData);

        $ip = (string)($requestMeta['ip'] ?? '');
        $userAgent = (string)($requestMeta['user_agent'] ?? '');

        $this->validateOfficeIpIfRequired($ip, 'time_in');

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);

        $existing = $this->repo->findTodayAttendance($userId);

        if ($existing && !empty($existing['time_in_at']) && empty($existing['time_out_at'])) {
            return [
                'message' => 'You are already timed in.',
                'attendance' => $existing,
            ];
        }

        if ($existing && !empty($existing['time_out_at'])) {
            throw new Exception('You already timed out for today.');
        }

        $attendanceId = $this->repo->timeIn($userId, $ip, $userAgent);

        if ($attendanceId > 0) {
            $this->repo->createLog([
                'attendance_id' => $attendanceId,
                'user_id' => $userId,
                'action' => 'TIME_IN',
                'old_status' => 'OFFLINE',
                'new_status' => 'AVAILABLE',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'note' => 'Staff timed in.',
            ]);
        }

        return [
            'message' => 'Time in successful.',
            'attendance_id' => $attendanceId,
            'attendance' => $this->repo->findTodayAttendance($userId),
        ];
    }

    public function timeOut(array $sessionData, array $requestMeta): array
    {
        $this->requireStaff($sessionData);

        $ip = (string)($requestMeta['ip'] ?? '');
        $userAgent = (string)($requestMeta['user_agent'] ?? '');

        $this->validateOfficeIpIfRequired($ip, 'time_out');

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $attendance = $this->repo->findTodayAttendance($userId);

        if (!$attendance || empty($attendance['time_in_at'])) {
            throw new Exception('You have not timed in yet.');
        }

        if (!empty($attendance['time_out_at'])) {
            return [
                'message' => 'You are already timed out.',
                'attendance' => $attendance,
            ];
        }

        $ok = $this->repo->timeOut($userId, $ip, $userAgent);

        if (!$ok) {
            throw new Exception('Failed to time out.');
        }

        $this->repo->createLog([
            'attendance_id' => (int)($attendance['id'] ?? 0),
            'user_id' => $userId,
            'action' => 'TIME_OUT',
            'old_status' => (string)($attendance['status'] ?? 'AVAILABLE'),
            'new_status' => 'OFFLINE',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'note' => 'Staff timed out.',
        ]);

        return [
            'message' => 'Time out successful.',
            'attendance' => $this->repo->findTodayAttendance($userId),
        ];
    }

    public function updateStatus(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $status = strtoupper(trim((string)($input['status'] ?? '')));

        if (!in_array($status, $this->allowedStatuses, true)) {
            throw new Exception('Invalid duty status.');
        }

        $attendance = $this->repo->findTodayAttendance($userId);

        if (!$attendance || empty($attendance['time_in_at'])) {
            throw new Exception('You must time in first.');
        }

        if (!empty($attendance['time_out_at'])) {
            throw new Exception('You already timed out for today.');
        }

        $oldStatus = strtoupper((string)($attendance['status'] ?? 'OFFLINE'));

        if ($oldStatus === $status) {
            return [
                'message' => 'Duty status is already ' . str_replace('_', ' ', strtolower($status)) . '.',
                'attendance' => $attendance,
            ];
        }

        $ok = $this->repo->updateStatus($userId, $status);

        if (!$ok) {
            throw new Exception('Failed to update duty status.');
        }

        $this->repo->createLog([
            'attendance_id' => (int)($attendance['id'] ?? 0),
            'user_id' => $userId,
            'action' => 'STATUS_CHANGE',
            'old_status' => $oldStatus,
            'new_status' => $status,
            'ip_address' => '',
            'user_agent' => '',
            'note' => 'Duty status changed.',
        ]);

        return [
            'message' => 'Duty status updated.',
            'attendance' => $this->repo->findTodayAttendance($userId),
        ];
    }

    public function availableTechnicians(array $sessionData): array
    {
        $this->requireStaff($sessionData);

        return [
            'items' => $this->repo->getAvailableTechnicians(),
        ];
    }

    private function validateOfficeIpIfRequired(string $ip, string $action): void
    {
        $configPath = BASE_PATH . '/config/attendance.php';

        $config = file_exists($configPath)
            ? require $configPath
            : [];

        $requiresOfficeIp = false;

        if ($action === 'time_in') {
            $requiresOfficeIp = (bool)($config['time_in_requires_office_ip'] ?? true);
        }

        if ($action === 'time_out') {
            $requiresOfficeIp = (bool)($config['time_out_requires_office_ip'] ?? false);
        }

        if (!$requiresOfficeIp) {
            return;
        }

        $allowedIps = $config['office_allowed_ips'] ?? [];

        if (!$this->ipMatchesAny($ip, $allowedIps)) {
            throw new Exception('Time in is only allowed from the office network. Your IP: ' . $ip);
        }
    }

    private function ipMatchesAny(string $ip, array $allowedIps): bool
    {
        foreach ($allowedIps as $allowed) {
            $allowed = trim((string)$allowed);

            if ($allowed === '') {
                continue;
            }

            if ($allowed === $ip) {
                return true;
            }

            if (str_contains($allowed, '/') && $this->ipInCidr($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        if ($subnet === null || $bits === null) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $bits = (int)$bits;
        $mask = -1 << (32 - $bits);

        $subnetLong &= $mask;

        return ($ipLong & $mask) === $subnetLong;
    }

    private function requireStaff(array $sessionData): void
    {
        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $role = strtoupper((string)($sessionData['role'] ?? ''));

        if ($userId <= 0) {
            throw new Exception('You must be logged in.');
        }

        if ($role === 'SUBSCRIBER') {
            throw new Exception('Staff access only.');
        }
    }
}