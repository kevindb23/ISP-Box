<?php

namespace App\Modules\TechnicianPortal\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\TechnicianPortal\Entities\TechnicianWorkOrder;
use App\Modules\TechnicianPortal\Repositories\TechnicianPortalRepository;
use App\Modules\WorkOrders\Services\WorkOrdersService;
use App\Modules\StaffAttendance\Services\StaffAttendanceService;
use App\Modules\StaffAttendance\DTOs\AttendanceCommandDTO;
use Exception;

class TechnicianPortalService
{
    private TechnicianPortalRepository $repo;

    private const UPLOAD_DIR = '/storage/uploads/work-orders';
    private const PRIVATE_PREFIX = 'private:work-orders/';

    public function __construct(
        TechnicianPortalRepository $repo,
        private AuditService $audit,
        private WorkOrdersService $workOrders,
        private StaffAttendanceService $attendance
    )
    {
        $this->repo = $repo;
    }

    public function dashboard(array $sessionData): array
    {
        $userId = $this->requireTechnician($sessionData);

        return [
            'attendance' => $this->repo->getTodayAttendance($userId),
            'summary' => $this->repo->getWorkOrderSummary($userId),
            'work_orders' => array_map(
                static fn(array $row): array => (new TechnicianWorkOrder($row))->toArray(),
                $this->repo->getAssignedWorkOrders($userId, ['limit' => 10, 'offset' => 0])
            ),
        ];
    }

    public function workOrders(array $sessionData): array
    {
        $userId = $this->requireTechnician($sessionData);

        $filters = [
            'status' => $_GET['status'] ?? null,
            'limit' => $_GET['limit'] ?? 100,
            'offset' => $_GET['offset'] ?? 0,
        ];

        return [
            'items' => array_map(
                static fn(array $row): array => (new TechnicianWorkOrder($row))->toArray(),
                $this->repo->getAssignedWorkOrders($userId, $filters)
            ),
            'total' => $this->repo->countAssignedWorkOrders($userId, $filters),
            'summary' => $this->repo->getWorkOrderSummary($userId),
        ];
    }

    public function workOrderDetails(array $sessionData, int $workOrderId): array
    {
        $userId = $this->requireTechnician($sessionData);

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        $workOrder = $this->repo->findAssignedWorkOrder($workOrderId, $userId);

        if (!$workOrder) {
            throw new Exception('Work order not found.');
        }

        return [
            'work_order' => (new TechnicianWorkOrder($workOrder))->toArray(),
            'tasks' => $this->repo->getWorkOrderTasks($workOrderId),
            'notes' => $this->repo->getWorkOrderNotes($workOrderId),
            'attachments' => $this->repo->getWorkOrderAttachments($workOrderId),
        ];
    }

    public function checkIn(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $latitude = $this->nullableString($input['latitude'] ?? null);
        $longitude = $this->nullableString($input['longitude'] ?? null);

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        $workOrder = $this->repo->findAssignedWorkOrder($workOrderId, $userId);
        if (!$workOrder) {
            throw new Exception('Work order not found.');
        }
        if (strtoupper((string)$workOrder['status']) !== 'IN_PROGRESS') {
            throw new Exception('Start the work order before checking in on site.');
        }
        $this->requireClockedIn($userId);
        $this->workOrders->updateStatus($sessionData, ['work_order_id'=>$workOrderId, 'status'=>'ON_SITE', 'note'=>'Technician checked in on site.']);
        $this->repo->checkInWorkOrder($workOrderId, $latitude, $longitude);
        $this->setDutyStatus($sessionData, 'ON_SITE', $workOrderId);

        $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => 'Technician checked in on site.',
            'note_type' => 'CHECK_IN',
        ]);

        $this->auditAction('CHECK_IN', 'Technician checked in on site.', 'WORK_ORDER', $workOrderId, [
            'latitude' => $latitude, 'longitude' => $longitude,
        ]);

        return [
            'message' => 'Checked in successfully.',
            'work_order_id' => $workOrderId,
            'status' => 'ON_SITE',
        ];
    }

    public function startWork(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        $workOrder = $this->repo->findAssignedWorkOrder($workOrderId, $userId);
        if (!$workOrder) {
            throw new Exception('Work order not found.');
        }
        $this->requireClockedIn($userId);
        $this->workOrders->updateStatus($sessionData, ['work_order_id'=>$workOrderId, 'status'=>'IN_PROGRESS', 'note'=>'Technician started work.']);
        $this->setDutyStatus($sessionData, 'BUSY', $workOrderId);

        $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => 'Technician started work.',
            'note_type' => 'STATUS',
        ]);

        $this->auditAction('START_WORK', 'Technician started work.', 'WORK_ORDER', $workOrderId);

        return [
            'message' => 'Work started successfully.',
            'work_order_id' => $workOrderId,
            'status' => 'IN_PROGRESS',
        ];
    }

    public function completeWork(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $completionNotes = trim((string)($input['completion_notes'] ?? ''));

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        if ($completionNotes === '') {
            throw new Exception('Completion notes are required.');
        }

        $workOrder = $this->repo->findAssignedWorkOrder($workOrderId, $userId);

        if (!$workOrder) {
            throw new Exception('Work order not found.');
        }

        $this->requireClockedIn($userId);

        if (!$this->repo->allRequiredTasksCompleted($workOrderId)) {
            throw new Exception('Complete all required tasks before completing the work order.');
        }

        $this->workOrders->updateStatus($sessionData, ['work_order_id'=>$workOrderId, 'status'=>'COMPLETED', 'note'=>$completionNotes]);
        $this->setDutyStatus($sessionData, $this->repo->hasOtherActiveWork($userId, $workOrderId) ? 'BUSY' : 'AVAILABLE', $workOrderId);

        $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => $completionNotes,
            'note_type' => 'COMPLETION',
        ]);

        $workOrderNo = (string)($workOrder['work_order_no'] ?? ('#' . $workOrderId));
        $this->auditAction(
            'COMPLETE_WORK',
            "Technician completed work order {$workOrderNo}.",
            'WORK_ORDER',
            $workOrderId,
            [
                'work_order_no' => $workOrderNo,
                'ticket_id' => $workOrder['ticket_id'] ?? null,
                'ticket_resolved' => !empty($workOrder['ticket_id']),
            ]
        );

        return [
            'message' => 'Work completed successfully.',
            'work_order_id' => $workOrderId,
            'status' => 'COMPLETED',
            'ticket_id' => $workOrder['ticket_id'] ?? null,
            'ticket_resolved' => !empty($workOrder['ticket_id']),
        ];
    }

    public function completeTask(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);
        $taskId = (int)($input['task_id'] ?? 0);

        if ($taskId <= 0) {
            throw new Exception('Invalid task ID.');
        }

        $task = $this->repo->findAssignedTask($taskId, $userId);

        if (!$task) {
            throw new Exception('Task not found or work order is not assigned to you.');
        }

        if ((int)($task['is_completed'] ?? 0) !== 1) {
            $this->workOrders->completeTask($sessionData, ['task_id'=>$taskId, 'notes'=>'Completed by assigned technician.']);
        }

        $workOrderId = (int)$task['work_order_id'];
        $this->auditAction(
            'COMPLETE_TASK',
            'Technician completed required work-order task: ' . (string)($task['task_name'] ?? ('#' . $taskId)) . '.',
            'WORK_ORDER',
            $workOrderId,
            ['task_id' => $taskId]
        );

        return [
            'message' => 'Task marked as completed.',
            'task_id' => $taskId,
            'work_order_id' => $workOrderId,
        ];
    }

    private function requireClockedIn(int $userId): void
    {
        $attendance = $this->repo->getTodayAttendance($userId);
        if (!$attendance || empty($attendance['time_in_at']) || !empty($attendance['time_out_at'])) {
            throw new Exception('You must be clocked in before working on a work order.');
        }
    }

    private function setDutyStatus(array $sessionData, string $status, int $workOrderId): void
    {
        $this->attendance->syncFromWorkOrder($sessionData, $status, $workOrderId);
    }

    public function addNote(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $note = trim((string)($input['note'] ?? ''));

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        if ($note === '') {
            throw new Exception('Note is required.');
        }

        if (!$this->repo->findAssignedWorkOrder($workOrderId, $userId)) {
            throw new Exception('Work order not found.');
        }

        $noteId = $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => $note,
            'note_type' => 'NOTE',
        ]);

        $this->auditAction('ADD_NOTE', 'Technician added a work order note.', 'WORK_ORDER', $workOrderId, [
            'note_id' => $noteId,
        ]);

        return [
            'message' => 'Note added successfully.',
            'note_id' => $noteId,
            'work_order_id' => $workOrderId,
        ];
    }

    public function uploadPhoto(array $sessionData, array $input, array $files): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $attachmentType = strtoupper(trim((string)($input['attachment_type'] ?? 'OTHER')));
        $remarks = $this->nullableString($input['remarks'] ?? null);

        $allowedTypes = ['BEFORE', 'AFTER', 'ONT', 'NAP', 'OTHER'];

        if (!in_array($attachmentType, $allowedTypes, true)) {
            $attachmentType = 'OTHER';
        }

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        if (!$this->repo->findAssignedWorkOrder($workOrderId, $userId)) {
            throw new Exception('Work order not found.');
        }

        if (empty($files['photo']) || !is_array($files['photo'])) {
            throw new Exception('Photo is required.');
        }

        $file = $files['photo'];

        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Photo upload failed.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $originalName = (string)($file['name'] ?? '');
        $fileSize = (int)($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new Exception('Invalid uploaded photo.');
        }

        if ($fileSize <= 0) {
            throw new Exception('Uploaded photo is empty.');
        }

        if ($fileSize > 5 * 1024 * 1024) {
            throw new Exception('Photo must not exceed 5MB.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new Exception('Only JPG, PNG, and WEBP photos are allowed.');
        }

        $mimeType = $this->detectMimeType($tmpName);
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new Exception('Invalid image file type.');
        }

        $uploadDir = rtrim(BASE_PATH, '/') . self::UPLOAD_DIR . '/' . $workOrderId;

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            throw new Exception('Failed to create upload directory.');
        }

        $safeOriginalName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($originalName));
        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeOriginalName;
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new Exception('Failed to save uploaded photo.');
        }

        $storedPath = self::PRIVATE_PREFIX . $workOrderId . '/' . $fileName;

        $attachmentId = $this->repo->createWorkOrderAttachment([
            'work_order_id' => $workOrderId,
            'uploaded_by_user_id' => $userId,
            'file_name' => $safeOriginalName,
            'file_path' => $storedPath,
            'file_type' => $mimeType,
            'file_size' => $fileSize,
            'attachment_type' => $attachmentType,
            'remarks' => $remarks,
        ]);

        $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => "Uploaded {$attachmentType} photo: {$safeOriginalName}",
            'note_type' => 'PHOTO',
        ]);

        $this->auditAction('UPLOAD_PHOTO', 'Technician uploaded a work order photo.', 'WORK_ORDER', $workOrderId, [
            'attachment_id' => $attachmentId, 'attachment_type' => $attachmentType,
        ]);

        return [
            'message' => 'Photo uploaded successfully.',
            'attachment_id' => $attachmentId,
            'work_order_id' => $workOrderId,
            'file_path' => '/api/v1/technician-portal/attachments/' . $attachmentId . '/download',
            'attachment_type' => $attachmentType,
        ];
    }

    public function deletePhoto(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $attachmentId = (int)($input['attachment_id'] ?? 0);

        if ($attachmentId <= 0) {
            throw new Exception('Invalid photo ID.');
        }

        $attachment = $this->repo->findWorkOrderAttachmentForTechnician($attachmentId, $userId);

        if (!$attachment) {
            throw new Exception('Photo not found or access denied.');
        }

        $workOrderId = (int)($attachment['work_order_id'] ?? 0);
        $fileName = (string)($attachment['file_name'] ?? 'photo');
        $filePath = (string)($attachment['file_path'] ?? '');

        $absolutePath = $this->resolveAttachmentPath($filePath);
        if ($absolutePath !== null && is_file($absolutePath) && !unlink($absolutePath)) {
            throw new Exception('Unable to remove the stored photo.');
        }

        $this->repo->deleteWorkOrderAttachment($attachmentId);

        if ($workOrderId > 0) {
            $this->repo->createWorkOrderNote([
                'work_order_id' => $workOrderId,
                'user_id' => $userId,
                'note' => "Deleted photo: {$fileName}",
                'note_type' => 'PHOTO',
            ]);
        }

        $this->auditAction('DELETE_PHOTO', 'Technician deleted a work order photo.', 'WORK_ORDER', $workOrderId, [
            'attachment_id' => $attachmentId,
        ]);

        return [
            'message' => 'Photo deleted successfully.',
            'attachment_id' => $attachmentId,
            'work_order_id' => $workOrderId,
        ];
    }

    public function downloadPhoto(array $sessionData, int $attachmentId): array
    {
        $userId = $this->requireTechnician($sessionData);
        $role = strtoupper((string)($sessionData['role'] ?? ''));
        $attachment = $role === 'SUPERADMIN'
            ? $this->repo->findWorkOrderAttachment($attachmentId)
            : $this->repo->findWorkOrderAttachmentForTechnician($attachmentId, $userId);
        if (!$attachment) throw new Exception('Photo not found or access denied.');

        $absolutePath = $this->resolveAttachmentPath((string)($attachment['file_path'] ?? ''));
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new Exception('Photo file is unavailable.');
        }

        return [
            'path' => $absolutePath,
            'name' => basename((string)($attachment['file_name'] ?? 'photo')),
            'mime' => (string)($attachment['file_type'] ?? 'application/octet-stream'),
            'size' => filesize($absolutePath) ?: 0,
        ];
    }

    private function resolveAttachmentPath(string $storedPath): ?string
    {
        if (str_starts_with($storedPath, self::PRIVATE_PREFIX)) {
            $relative = substr($storedPath, strlen('private:'));
            $base = rtrim(BASE_PATH, '/') . '/storage/uploads';
        } elseif (str_starts_with($storedPath, '/uploads/work-orders/')) {
            $relative = $storedPath;
            $base = rtrim(BASE_PATH, '/') . '/public';
        } else {
            return null;
        }

        $candidate = $base . '/' . ltrim($relative, '/');
        $realBase = realpath($base);
        $realFile = realpath($candidate);
        return $realBase && $realFile && str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)
            ? $realFile
            : null;
    }

    public function timeIn(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);
        $result = $this->attendance->timeIn($sessionData, AttendanceCommandDTO::fromRequest());
        $attendanceId = (int)($result['attendance_id'] ?? $result['attendance']['id'] ?? 0);
        $this->repo->updateTimeInLocation($userId, $this->nullableString($input['latitude'] ?? null), $this->nullableString($input['longitude'] ?? null), $this->nullableString($input['notes'] ?? null));

        return [
            'message' => 'Timed in successfully.',
            'attendance_id' => $attendanceId,
            'status' => 'AVAILABLE',
        ];
    }

    public function timeOut(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $this->attendance->timeOut($sessionData, AttendanceCommandDTO::fromRequest());
        $this->repo->updateTimeOutLocation($userId, $this->nullableString($input['latitude'] ?? null), $this->nullableString($input['longitude'] ?? null));

        return [
            'message' => 'Timed out successfully.',
            'status' => 'OFFLINE',
        ];
    }

    public function updateAttendanceStatus(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $status = strtoupper(trim((string)($input['status'] ?? '')));

        $allowed = ['AVAILABLE', 'ON_BREAK', 'TRAVELING'];

        if (!in_array($status, $allowed, true)) {
            throw new Exception('Invalid attendance status.');
        }

        $this->attendance->updateStatus($sessionData, new AttendanceCommandDTO(status:$status));

        return [
            'message' => 'Attendance status updated.',
            'status' => $status,
        ];
    }

    private function auditAction(
        string $action,
        string $description,
        string $objectType,
        int $objectId,
        array $metadata = []
    ): void {
        $this->audit->logEvent(new AuditEventDTO(
            module: 'TECHNICIAN_PORTAL', action: $action, description: $description,
            objectType: $objectType, objectId: $objectId > 0 ? $objectId : null, metadata: $metadata
        ));
    }

    private function requireTechnician(array $sessionData): int
    {
        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $role = strtoupper((string)($sessionData['role'] ?? ''));

        if ($userId <= 0) {
            throw new Exception('You must be logged in.');
        }

        if ($role !== 'TECHNICIAN' && $role !== 'SUPERADMIN') {
            throw new Exception('Technician access only.');
        }

        return $userId;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function detectMimeType(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $mimeType = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($mimeType) && $mimeType !== '') {
                    return $mimeType;
                }
            }
        }

        return mime_content_type($path) ?: '';
    }
}
