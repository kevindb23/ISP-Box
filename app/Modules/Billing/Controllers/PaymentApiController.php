<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\Services\PaymentService;
use App\Modules\Billing\Validators\CreateInvoicesValidator;
use Framework\ApiController;
use Throwable;
use Framework\SessionManager;

class PaymentApiController extends ApiController
{
    private PaymentService $service;

    public function __construct(PaymentService $service, private CreateInvoicesValidator $validator)
    {
        $this->service = $service;
    }

    public function list(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'payment_status' => $query['payment_status'] ?? null,
                'invoice_id' => $query['invoice_id'] ?? null,
                'subscriber_id' => $query['subscriber_id'] ?? null,
                'search' => $query['search'] ?? null,
                'limit' => $query['limit'] ?? 50,
                'offset' => $query['offset'] ?? 0,
            ];

            $this->success($this->service->list($filters));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function show($id): void
    {
        try {
            $this->success($this->service->show((int)$id));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 404);
        }
    }

    public function create(): void
    {
        try {
            $dto = new CreatePaymentDTO($this->request()->input());
            $errors = $this->validator->payment($dto);
            if ($errors !== []) {
                $this->error('Please correct the highlighted fields.', 422, $errors);
                return;
            }
            $payload = $dto->toArray();

            if (!isset($payload['received_by'])) {
                $payload['received_by'] = $_SESSION['user']['id'] ?? null;
            }

            $this->success($this->service->create($payload), 'Payment recorded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function byInvoice($invoiceId): void
    {
        try {
            $this->success($this->service->getByInvoice((int)$invoiceId));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 404);
        }
    }

    public function void($id): void
    {
        try {
            $payload = $this->request()->input();

            $userId = $_SESSION['user']['id'] ?? null;
            $reason = trim((string)($payload['reason'] ?? $payload['void_reason'] ?? ''));

            $this->success(
                $this->service->void(
                    (int)$id,
                    $userId ? (int)$userId : null,
                    $reason !== '' ? $reason : null
                ),
                'Payment voided.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function approve($id): void
    {
        try { $this->success($this->service->approve((int)$id,(int)SessionManager::id()),'Payment approved and posted.'); }
        catch(Throwable $e){$this->error($e->getMessage(),422);}
    }

    public function reject($id): void
    {
        try { $reason=(string)($this->request()->input()['reason']??''); $this->success($this->service->reject((int)$id,(int)SessionManager::id(),$reason),'Payment rejected.'); }
        catch(Throwable $e){$this->error($e->getMessage(),422);}
    }

    public function proof($id): void
    {
        try {
            $role=strtoupper((string)SessionManager::role());
            $file=$this->service->proof((int)$id,(int)SessionManager::id(),$role!=='SUBSCRIBER');
            header('Content-Type: '.$file['mime']); header('Content-Disposition: inline; filename="'.addslashes($file['name']).'"'); header('X-Content-Type-Options: nosniff');
            readfile($file['path']); exit;
        } catch(Throwable $e){$this->error($e->getMessage(),404);}
    }

}
