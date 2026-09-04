<?php

namespace App\Modules\SubscriberPortal\Controllers;

use App\Modules\SubscriberPortal\DTOs\SubscriberPortalCommandDTO;
use App\Modules\Mfa\Services\MfaService;
use App\Modules\SubscriberPortal\Services\SubscriberPortalService;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class SubscriberPortalApiController extends ApiController
{
    private SubscriberPortalService $service;

    public function __construct(SubscriberPortalService $service, private MfaService $mfa)
    {
        $this->service = $service;
    }

    public function me(): void
    {
        try {
            $this->success(
                $this->service->profile($this->sessionUser()),
                'Subscriber profile loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function dashboard(): void
    {
        try {
            $this->success(
                $this->service->dashboard($this->sessionUser()),
                'Subscriber dashboard loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function summary(): void
    {
        try {
            $this->success($this->service->summary($this->sessionUser()), 'Subscriber summary loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function services(): void
    {
        try {
            $this->success(
                $this->service->services($this->sessionUser()),
                'Subscriber services loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function invoices(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'status' => $query['status'] ?? null,
                'search' => $query['search'] ?? null,
                'limit' => $query['limit'] ?? 50,
                'offset' => $query['offset'] ?? 0,
            ];

            $this->success(
                $this->service->invoices($this->sessionUser(), $filters),
                'Subscriber invoices loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function invoiceShow($id): void
    {
        try {
            $this->success(
                $this->service->invoiceDetails($this->sessionUser(), (int)$id),
                'Subscriber invoice loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function payments(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'search' => $query['search'] ?? null,
                'limit' => $query['limit'] ?? 50,
                'offset' => $query['offset'] ?? 0,
            ];

            $this->success(
                $this->service->payments($this->sessionUser(), $filters),
                'Subscriber payments loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function submitPayment(): void
    {
        try { $result=$this->service->submitManualPayment($this->sessionUser(),$this->request()->input(),$this->request()->files()); $this->success($result,$result['message']); }
        catch(Throwable $e){$this->error($e->getMessage(),422);}
    }

    public function changePassword(): void
    {
        try {
            $result = $this->service->changePassword(
                $this->sessionUser(),
                (new SubscriberPortalCommandDTO($this->request()->input()))->toArray()
            );

            $this->success(
                $result,
                $result['message'] ?? 'Portal password changed successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function security(): void
    {
        try {
            $user = $this->subscriberUser();
            $this->success($this->mfa->securityStatus($user['id']), 'Security settings loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function enrollMfa(): void
    {
        try {
            $user = $this->subscriberUser();
            $method = (string)($this->request()->input()['method'] ?? '');
            $this->success(
                $this->mfa->beginEnrollment($user['id'], $method),
                'MFA enrollment started.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function completeMfa(): void
    {
        try {
            $user = $this->subscriberUser();
            $input = $this->request()->input();
            $this->mfa->verifyEnrollment(
                $user['id'],
                (string)($input['code'] ?? ''),
                isset($input['challenge_token']) ? (string)$input['challenge_token'] : null
            );
            $this->success([], 'MFA enabled successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function disableMfa(): void
    {
        try {
            $user = $this->subscriberUser();
            $this->mfa->disable($user['id']);
            $this->success([], 'MFA disabled successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function tickets(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'status' => $query['status'] ?? null,
                'search' => $query['search'] ?? null,
                'limit' => $query['limit'] ?? 50,
                'offset' => $query['offset'] ?? 0,
            ];

            $this->success(
                $this->service->tickets($this->sessionUser(), $filters),
                'Subscriber tickets loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function createTicket(): void
    {
        try {
            $result = $this->service->createTicket(
                $this->sessionUser(),
                (new SubscriberPortalCommandDTO($this->request()->input()))->toArray()
            );

            $this->success(
                $result,
                $result['message'] ?? 'Ticket created successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function scheduleVisit(): void
    {
        try {
            $result = $this->service->scheduleVisit(
                $this->sessionUser(),
                (new SubscriberPortalCommandDTO($this->request()->input()))->toArray()
            );

            $this->success(
                $result,
                $result['message'] ?? 'Visit schedule submitted successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function visitSlots(): void
    {
        try {
            $date = trim((string)($this->request()->query()['date'] ?? ''));

            $this->success(
                $this->service->visitSlots(
                    $this->sessionUser(),
                    [
                        'date' => $date,
                    ]
                ),
                'Available visit slots loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function ticketShow($id): void
    {
        try {
            $this->success(
                $this->service->ticketDetails($this->sessionUser(), (int)$id),
                'Subscriber ticket loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function ticketReply(): void
    {
        try {
            $result = $this->service->replyTicket(
                $this->sessionUser(),
                (new SubscriberPortalCommandDTO($this->request()->input()))->toArray()
            );

            $this->success(
                $result,
                $result['message'] ?? 'Reply sent successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }
    private function sessionUser(): array
    {
        $user = SessionManager::user();

        if (!is_array($user)) {
            $user = [];
        }

        return [
            'id' => (int)($user['id'] ?? 0),
            'user_id' => (int)($user['id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'role' => strtoupper((string)($user['role'] ?? '')),
        ];
    }

    private function subscriberUser(): array
    {
        $user = $this->sessionUser();
        if ($user['id'] <= 0 || $user['role'] !== 'SUBSCRIBER') {
            throw new \RuntimeException('Subscriber portal access only.');
        }

        return $user;
    }


}
