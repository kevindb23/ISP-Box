<?php

namespace App\Modules\Billing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Repositories\BillingRunRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use Throwable;

class BillingCycleService
{
    private InvoiceRepository $invoiceRepo;
    private BillingRunRepository $runRepo;
    private InvoiceService $invoiceService;
    private ?AuditService $audit;

    public function __construct(
        InvoiceRepository $invoiceRepo,
        BillingRunRepository $runRepo,
        InvoiceService $invoiceService,
        ?AuditService $audit = null
    ) {
        $this->invoiceRepo = $invoiceRepo;
        $this->runRepo = $runRepo;
        $this->invoiceService = $invoiceService;
        $this->audit = $audit;
    }

    public function generateDueInvoices(
        ?string $asOfDate = null,
        int $limit = 200,
        string $runType = 'MANUAL',
        ?int $triggeredBy = null
    ): array {
        $asOfDate = $asOfDate ?: date('Y-m-d');
        $startedAt = date('Y-m-d H:i:s');
        $runType = strtoupper($runType ?: 'MANUAL');

        $runId = $this->runRepo->createRun([
            'run_no' => $this->runRepo->generateRunNo(),
            'run_type' => $runType,
            'as_of_date' => $asOfDate,
            'checked_count' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'status' => 'SUCCESS',
            'message' => 'Billing run started.',
            'triggered_by' => $triggeredBy,
            'started_at' => $startedAt,
            'completed_at' => null,
        ]);

        $this->safeAudit(
            'BILLING',
            'START_BILLING_RUN',
            sprintf(
                'Billing cycle started. Run ID=%d Type=%s Date=%s',
                $runId,
                $runType,
                $asOfDate
            )
        );

        $services = $this->invoiceRepo->findDuePostpaidServices($asOfDate, $limit);

        $summary = [
            'billing_run_id' => $runId,
            'as_of_date' => $asOfDate,
            'checked' => count($services),
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($services as $service) {
            $serviceId = (int)($service['service_id'] ?? 0);

            if ($serviceId <= 0) {
                $summary['skipped']++;

                $item = [
                    'created' => false,
                    'reason' => 'Invalid service ID.',
                    'service' => $service,
                ];

                $summary['items'][] = $item;
                $this->saveRunItem($runId, $service, $item, 'SKIPPED');

                continue;
            }

            try {
                $result = $this->invoiceService->createRecurringInvoiceForService($serviceId, $asOfDate);

                if (!empty($result['created'])) {
                    $summary['created']++;
                    $resultStatus = 'CREATED';
                } else {
                    $summary['skipped']++;
                    $resultStatus = 'SKIPPED';
                }

                $item = array_merge($result, [
                    'subscriber_id' => $service['subscriber_id'] ?? null,
                    'plan_id' => $service['plan_id'] ?? null,
                    'subscriber_name' => $service['subscriber_name'] ?? null,
                    'account_number' => $service['account_number'] ?? null,
                    'plan_name' => $service['plan_name'] ?? null,
                ]);

                $summary['items'][] = $item;
                $this->saveRunItem($runId, $service, $item, $resultStatus);
            } catch (Throwable $e) {
                $summary['failed']++;

                $this->safeAudit(
                    'BILLING',
                    'BILLING_RUN_ITEM_FAILED',
                    sprintf(
                        'Billing generation failed for Service ID %d. Error: %s',
                        $serviceId,
                        $e->getMessage()
                    )
                );

                $item = [
                    'created' => false,
                    'failed' => true,
                    'service_id' => $serviceId,
                    'subscriber_id' => $service['subscriber_id'] ?? null,
                    'plan_id' => $service['plan_id'] ?? null,
                    'subscriber_name' => $service['subscriber_name'] ?? null,
                    'account_number' => $service['account_number'] ?? null,
                    'plan_name' => $service['plan_name'] ?? null,
                    'error' => $e->getMessage(),
                ];

                $summary['items'][] = $item;
                $this->saveRunItem($runId, $service, $item, 'FAILED', $e->getMessage());
            }
        }

        $status = 'SUCCESS';

        if ($summary['failed'] > 0 && ($summary['created'] > 0 || $summary['skipped'] > 0)) {
            $status = 'PARTIAL';
        } elseif ($summary['failed'] > 0 && $summary['created'] === 0) {
            $status = 'FAILED';
        }

        $message = sprintf(
            'Billing run completed. Checked: %d, Created: %d, Skipped: %d, Failed: %d.',
            $summary['checked'],
            $summary['created'],
            $summary['skipped'],
            $summary['failed']
        );

        $this->runRepo->updateRunSummary($runId, [
            'checked_count' => $summary['checked'],
            'created_count' => $summary['created'],
            'skipped_count' => $summary['skipped'],
            'failed_count' => $summary['failed'],
            'status' => $status,
            'message' => $message,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->safeAudit(
            'BILLING',
            'BILLING_RUN',
            sprintf(
                'Billing cycle completed. Run ID=%d Status=%s Checked=%d Created=%d Skipped=%d Failed=%d',
                $runId,
                $status,
                $summary['checked'],
                $summary['created'],
                $summary['skipped'],
                $summary['failed']
            )
        );

        $summary['status'] = $status;
        $summary['message'] = $message;

        return $summary;
    }

    public function listRuns(array $filters = []): array
    {
        return [
            'items' => $this->runRepo->list($filters),
            'total' => $this->runRepo->count($filters),
        ];
    }

    public function showRun(int $id): array
    {
        $run = $this->runRepo->find($id);

        if (!$run) {
            throw new \Exception('Billing run not found.');
        }

        return [
            'run' => $run,
            'items' => $this->runRepo->getItems($id),
        ];
    }

    private function saveRunItem(
        int $runId,
        array $service,
        array $result,
        string $resultStatus,
        ?string $errorMessage = null
    ): void {
        $invoice = $result['invoice'] ?? null;
        $invoiceId = null;

        if (is_array($invoice)) {
            $invoiceId = (int)($invoice['id'] ?? 0);
        }

        $this->runRepo->createRunItem($runId, [
            'service_id' => $result['service_id'] ?? $service['service_id'] ?? null,
            'subscriber_id' => $result['subscriber_id'] ?? $service['subscriber_id'] ?? null,
            'plan_id' => $result['plan_id'] ?? $service['plan_id'] ?? null,
            'invoice_id' => $invoiceId > 0 ? $invoiceId : null,
            'subscriber_name' => $result['subscriber_name'] ?? $service['subscriber_name'] ?? null,
            'account_number' => $result['account_number'] ?? $service['account_number'] ?? null,
            'plan_name' => $result['plan_name'] ?? $service['plan_name'] ?? null,
            'result_status' => $resultStatus,
            'reason' => $result['reason'] ?? null,
            'error_message' => $errorMessage ?? ($result['error'] ?? null),
            'billing_period_start' => $result['billing_period_start'] ?? null,
            'billing_period_end' => $result['billing_period_end'] ?? null,
            'next_due_date' => $result['next_due_date'] ?? null,
            'payload_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function safeAudit(string $module, string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log($module, $action, $description);
        } catch (Throwable $e) {
            error_log('[Audit][' . $module . '] ' . $e->getMessage());
        }
    }
}
