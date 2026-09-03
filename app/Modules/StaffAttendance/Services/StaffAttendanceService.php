<?php

namespace App\Modules\StaffAttendance\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\StaffAttendance\DTOs\AttendanceCommandDTO;
use App\Modules\StaffAttendance\Entities\Attendance;
use App\Modules\StaffAttendance\Repositories\StaffAttendanceRepository;
use App\Modules\StaffAttendance\Validators\AttendanceCommandValidator;
use Exception;

class StaffAttendanceService
{
    private StaffAttendanceRepository $repo;

    private array $adminRoles = [
        'SUPERADMIN',
        'ADMIN',
        'ADMINISTRATOR',
        'NOC',
        'SUPPORT',
    ];

    public function __construct(
        StaffAttendanceRepository $repo,
        private AttendanceCommandValidator $validator,
        private AuditService $audit
    )
    {
        $this->repo = $repo;
    }

    public function today(array $sessionData): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $role = strtoupper((string)($sessionData['role'] ?? ''));

        $canViewTeam = in_array($role, $this->adminRoles, true);

        $attendance = $this->repo->findTodayAttendance($userId);
        $payload = [
            'attendance' => $attendance ? (new Attendance($attendance))->toArray() : null,
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

    public function timeIn(array $sessionData, AttendanceCommandDTO $command): array
    {
        $this->requireStaff($sessionData);

        $ip = $command->ipAddress;
        $userAgent = $command->userAgent;

        $this->validateOfficeIpIfRequired($ip, 'time_in');

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);

        $this->repo->acquireUserLock($userId);
        try {

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

        $attendanceId = $this->repo->transaction(function () use ($userId, $ip, $userAgent): int {
            $attendanceId = $this->repo->timeIn($userId, $ip, $userAgent);
            if ($attendanceId <= 0) throw new Exception('Failed to time in.');
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
            return $attendanceId;
        });
        $this->audit->logEvent(new AuditEventDTO(
            module: 'STAFF_ATTENDANCE', action: 'TIME_IN', description: 'Staff member timed in.',
            objectType: 'STAFF_ATTENDANCE', objectId: $attendanceId,
            newValues: ['user_id' => $userId, 'status' => 'AVAILABLE']
        ));

        return [
            'message' => 'Time in successful.',
            'attendance_id' => $attendanceId,
            'attendance' => $this->repo->findTodayAttendance($userId),
        ];
        } finally {
            $this->repo->releaseUserLock($userId);
        }
    }

    public function timeOut(array $sessionData, AttendanceCommandDTO $command): array
    {
        $this->requireStaff($sessionData);

        $ip = $command->ipAddress;
        $userAgent = $command->userAgent;

        $this->validateOfficeIpIfRequired($ip, 'time_out');

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $this->repo->acquireUserLock($userId);
        try {
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
        if ($this->repo->hasActiveFieldWork($userId)) {
            throw new Exception('Finish or hand off active field work before timing out.');
        }

        $this->repo->transaction(function () use ($userId, $ip, $userAgent, $attendance): void {
            if (!$this->repo->timeOut($userId, $ip, $userAgent)) throw new Exception('Attendance changed while timing out. Please refresh.');
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
        });

        $this->audit->logEvent(new AuditEventDTO(
            module: 'STAFF_ATTENDANCE', action: 'TIME_OUT', description: 'Staff member timed out.',
            objectType: 'STAFF_ATTENDANCE', objectId: (int)($attendance['id'] ?? 0),
            oldValues: ['status' => (string)($attendance['status'] ?? 'AVAILABLE')],
            newValues: ['status' => 'OFFLINE']
        ));

        return [
            'message' => 'Time out successful.',
            'attendance' => $this->repo->findTodayAttendance($userId),
        ];
        } finally { $this->repo->releaseUserLock($userId); }
    }

    public function updateStatus(array $sessionData, AttendanceCommandDTO $command): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $status = $command->status;
        $errors = $this->validator->validateStatus($command);
        if ($errors !== []) {
            throw new Exception((string)reset($errors));
        }

        $this->repo->acquireUserLock($userId);
        try {
        $attendance = $this->repo->findTodayAttendance($userId);

        if (!$attendance || empty($attendance['time_in_at'])) {
            throw new Exception('You must time in first.');
        }

        if (!empty($attendance['time_out_at'])) {
            throw new Exception('You already timed out for today.');
        }

        $oldStatus = strtoupper((string)($attendance['status'] ?? 'OFFLINE'));

        $role = strtoupper((string)($sessionData['role'] ?? ''));
        if ($role === 'TECHNICIAN' && in_array($status, ['BUSY', 'ON_SITE'], true)) {
            throw new Exception('Busy and on-site status are controlled by active work orders.');
        }
        if ($role === 'TECHNICIAN' && $this->repo->hasActiveFieldWork($userId)) {
            throw new Exception('Duty status is controlled by your active work order.');
        }

        if ($oldStatus === $status) {
            return [
                'message' => 'Duty status is already ' . str_replace('_', ' ', strtolower($status)) . '.',
                'attendance' => $attendance,
            ];
        }

        $this->repo->transaction(function () use ($userId, $status, $attendance, $oldStatus): void {
            if (!$this->repo->updateStatus($userId, $status)) throw new Exception('Failed to update duty status.');
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
        });

        $this->audit->logEvent(new AuditEventDTO(
            module: 'STAFF_ATTENDANCE', action: 'STATUS_CHANGE', description: 'Changed staff duty status.',
            objectType: 'STAFF_ATTENDANCE', objectId: (int)($attendance['id'] ?? 0),
            oldValues: ['status' => $oldStatus], newValues: ['status' => $status]
        ));

        return [
            'message' => 'Duty status updated.',
            'attendance' => $this->repo->findTodayAttendance($userId),
        ];
        } finally { $this->repo->releaseUserLock($userId); }
    }

    public function availableTechnicians(array $sessionData): array
    {
        $this->requireStaff($sessionData);

        return [
            'items' => $this->repo->getAvailableTechnicians(),
        ];
    }

    public function history(array $sessionData, array $filters): array
    {
        $this->requireStaff($sessionData);
        $role=strtoupper((string)($sessionData['role']??''));
        if (!in_array($role,$this->adminRoles,true)) throw new Exception('Team attendance access is restricted to operations managers.');
        $to=(string)($filters['to']??date('Y-m-d')); $from=(string)($filters['from']??date('Y-m-d',strtotime('-30 days')));
        foreach ([$from,$to] as $date) if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new Exception('Invalid attendance date range.');
        if ($from>$to) throw new Exception('From date must be before the to date.');
        return ['items'=>$this->repo->getHistory($from,$to),'from'=>$from,'to'=>$to];
    }

    public function syncFromWorkOrder(array $sessionData, string $status, int $workOrderId): array
    {
        $this->requireStaff($sessionData);
        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $this->repo->acquireUserLock($userId);
        try {
        $status = strtoupper(trim($status));
        if (!in_array($status, ['AVAILABLE', 'BUSY', 'ON_SITE'], true)) throw new Exception('Invalid work-order duty status.');
        $attendance = $this->repo->findTodayAttendance($userId);
        if (!$attendance || empty($attendance['time_in_at']) || !empty($attendance['time_out_at'])) {
            throw new Exception('Technician must be clocked in before work-order status can change.');
        }
        $oldStatus = strtoupper((string)($attendance['status'] ?? 'OFFLINE'));
        if ($oldStatus === $status) return ['attendance'=>$attendance, 'message'=>'Duty status already synchronized.'];
        $this->repo->transaction(function () use ($userId, $status, $attendance, $oldStatus, $workOrderId): void {
            if (!$this->repo->updateStatus($userId, $status)) throw new Exception('Failed to synchronize duty status.');
            $this->repo->createLog([
                'attendance_id'=>(int)$attendance['id'], 'user_id'=>$userId, 'action'=>'STATUS_CHANGE',
                'old_status'=>$oldStatus, 'new_status'=>$status, 'ip_address'=>'', 'user_agent'=>'',
                'note'=>'Auto-updated from work order #' . $workOrderId . '.',
            ]);
        });
        $this->audit->logEvent(new AuditEventDTO(
            module:'STAFF_ATTENDANCE', action:'WORK_ORDER_STATUS_SYNC', description:'Duty status synchronized from work order.',
            objectType:'STAFF_ATTENDANCE', objectId:(int)$attendance['id'],
            oldValues:['status'=>$oldStatus], newValues:['status'=>$status], metadata:['work_order_id'=>$workOrderId]
        ));
        return ['attendance'=>$this->repo->findTodayAttendance($userId), 'message'=>'Duty status synchronized.'];
        } finally { $this->repo->releaseUserLock($userId); }
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
