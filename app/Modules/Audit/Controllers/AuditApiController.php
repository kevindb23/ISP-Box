<?php

namespace App\Modules\Audit\Controllers;

use App\Modules\Audit\DTOs\AuditFilterDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Audit\Validators\AuditFilterValidator;
use Framework\ApiController;
use Framework\SessionManager;

class AuditApiController extends ApiController
{
    public function __construct(
        private AuditService $service,
        private AuditFilterValidator $validator
    ) {
    }

    public function index(): void
    {
        $filter = new AuditFilterDTO($this->request()->query());
        $errors = $this->validator->validate($filter);

        if ($errors !== []) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        $this->success($this->service->latest($filter));
    }

    public function show($id): void
    {
        $item = $this->service->find((int)$id);

        if (!$item) {
            $this->error('Audit log not found.', 404);
            return;
        }

        $this->success($item);
    }

    public function notifications(): void
    {
<<<<<<< HEAD
        $user = SessionManager::user();
        if (!is_array($user) || empty($user['id'])) {
            $this->error('Authentication required.', 401);
            return;
        }
        $role = strtoupper(trim((string)($user['role'] ?? '')));
        $this->success($this->service->notificationFeed((int)$user['id'], 30, $role));
    }

    public function markRead($id): void
    {
        $user = SessionManager::user();
        if (!is_array($user) || empty($user['id'])) {
            $this->error('Authentication required.', 401);
            return;
        }
        $this->service->markNotificationRead((int)$id, (int)$user['id']);
        $this->success([], 'Notification marked as read.');
=======
        $this->success($this->service->securityNotifications());
>>>>>>> origin/main
    }
}
