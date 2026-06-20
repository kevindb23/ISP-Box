<?php

namespace App\Modules\ServiceProvisioning\Controllers;

use Framework\ApiController;
use Throwable;
use App\Modules\ServiceProvisioning\Services\ServiceProvisioningService;

class ServiceProvisioningApiController extends ApiController
{
    private ServiceProvisioningService $service;

    public function __construct(ServiceProvisioningService $service)
    {
        $this->service = $service;
    }

    public function list()
    {
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 20));
            $search = trim((string)($_GET['search'] ?? ''));
            $status = trim((string)($_GET['status'] ?? ''));

            $this->success(
                $this->service->paginateJobs($page, $limit, $search, $status),
                'Provisioning jobs loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $job = $this->service->getJobDetails((int)$id);

            if (!$job) {
                $this->error('Provisioning job not found.', 404);
                return;
            }

            $this->success($job, 'Provisioning job loaded successfully.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function validate()
    {
        try {
            $this->success(
                $this->service->validateProvisioning($this->makeDto()),
                'Validation passed.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function create()
    {
        try {
            $this->success(
                $this->service->createProvisioningJob($this->makeDto()),
                'Provisioning job created successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function run()
    {
        try {
            $payload = $this->getInputData();
            $jobId = (int)($payload['job_id'] ?? 0);

            if ($jobId <= 0) {
                $this->error('job_id is required.', 422);
                return;
            }

            $this->success(
                $this->service->runProvisioningJob($jobId),
                'Provisioning job executed successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function logs($jobId)
    {
        try {
            $this->success(
                $this->service->getJobLogs((int)$jobId),
                'Provisioning logs loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function checkAcs($jobId)
    {
        try {
            $this->success(
                $this->service->checkAcs((int)$jobId),
                'ACS check completed successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function supportSubscribers()
    {
        try {
            $search = trim((string)($_GET['search'] ?? ''));

            $this->success(
                $this->service->getSupportSubscribers($search),
                'Subscribers loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportPlans()
    {
        try {
            $search = trim((string)($_GET['search'] ?? ''));
            $subscriberId = (int)($_GET['subscriber_id'] ?? 0);

            $this->success(
                $this->service->getSupportPlans($search, $subscriberId),
                'Plans loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportOlts()
    {
        try {
            $search = trim((string)($_GET['search'] ?? ''));

            $this->success(
                $this->service->getSupportOlts($search),
                'OLTs loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportOltPorts()
    {
        try {
            $oltId = (int)($_GET['olt_id'] ?? 0);

            if ($oltId <= 0) {
                $this->error('olt_id is required.', 422);
                return;
            }

            $this->success(
                $this->service->getSupportOltPorts($oltId),
                'OLT ports loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportNetworkBoxes()
    {
        try {
            $oltId = (int)($_GET['olt_id'] ?? 0);
            $oltPortId = (int)($_GET['olt_port_id'] ?? 0);
            $boxType = trim((string)($_GET['box_type'] ?? 'NAP'));

            $this->success(
                $this->service->getSupportNetworkBoxes($oltId, $oltPortId, $boxType),
                'Network boxes loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportSplitters()
    {
        try {
            $networkBoxId = (int)($_GET['network_box_id'] ?? $_GET['nap_id'] ?? 0);

            if ($networkBoxId <= 0) {
                $this->error('network_box_id or nap_id is required.', 422);
                return;
            }

            $this->success(
                $this->service->getSupportSplitters($networkBoxId),
                'Splitters loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportSplitterOutputPorts()
    {
        try {
            $splitterId = (int)($_GET['splitter_id'] ?? 0);

            if ($splitterId <= 0) {
                $this->error('splitter_id is required.', 422);
                return;
            }

            $this->success(
                $this->service->getSupportSplitterOutputPorts($splitterId),
                'Splitter output ports loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportOnts()
    {
        try {
            $search = trim((string)($_GET['search'] ?? ''));

            $this->success(
                $this->service->getSupportOnts($search),
                'ONTs loaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function provision()
    {
        try {
            $this->success(
                $this->service->provision($this->makeDto()),
                'Provisioning successful.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function retry($jobId)
    {
        try {
            $this->success(
                $this->service->retryJob((int)$jobId),
                'Provisioning retry started successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function cancel($jobId)
    {
        try {
            $this->success(
                $this->service->cancelJob((int)$jobId),
                'Provisioning job cancelled successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    private function makeDto(): array
    {
        return $this->getInputData();
    }

    private function getInputData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST ?: [];
    }
}