<?php

namespace App\Modules\NapManagement\Controllers;

use Framework\Controller;
use App\Modules\NapManagement\Services\NapManagementService;

class NapManagementApiController extends Controller
{
    private NapManagementService $service;

    public function __construct(NapManagementService $service)
    {
        $this->service = $service;
    }

    private function respond(bool $ok, string $message = '', $data = null, array $errors = [], int $httpCode = 200): void
    {
        if (ob_get_length()) {
            ob_clean();
        }

        http_response_code($httpCode);
        header('Content-Type: application/json');

        echo json_encode([
            'ok' => $ok,
            'status' => $ok ? 'success' : 'error',
            'success' => $ok,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ]);

        exit;
    }

    private function respondServiceResult(array $res): void
    {
        $ok = (bool)($res['ok'] ?? false);
        $message = (string)($res['message'] ?? '');
        $errors = is_array($res['errors'] ?? null) ? $res['errors'] : [];

        $data = $res;
        unset($data['ok'], $data['message'], $data['errors']);

        $this->respond($ok, $message, $data, $errors, $ok ? 200 : 422);
    }

    private function input(): array
    {
        $input = $_POST;

        if (empty($input)) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        return is_array($input) ? $input : [];
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN DATA API
    |--------------------------------------------------------------------------
    */

    public function odfs(): void
    {
        $this->respond(true, '', $this->service->getOdfAll(), []);
    }

    public function odf($id): void
    {
        $row = $this->service->getOdfById((int)$id);

        if (!$row) {
            $this->respond(false, 'ODF not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function lcps(): void
    {
        $this->respond(true, '', $this->service->getLcpAll(), []);
    }

    public function lcp($id): void
    {
        $row = $this->service->getLcpById((int)$id);

        if (!$row) {
            $this->respond(false, 'LCP not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function naps(): void
    {
        $this->respond(true, '', $this->service->getNapAll(), []);
    }

    public function nap($id): void
    {
        $row = $this->service->getNapById((int)$id);

        if (!$row) {
            $this->respond(false, 'NAP not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    /*
    |--------------------------------------------------------------------------
    | SUPPORTING API
    |--------------------------------------------------------------------------
    */

    public function odfPorts($odfId): void
    {
        $this->respond(true, '', $this->service->getOdfPorts((int)$odfId), []);
    }

    public function lcpPorts($lcpId): void
    {
        $includeCurrent = isset($_GET['include_current']) && $_GET['include_current'] !== ''
            ? (int)$_GET['include_current']
            : null;

        $this->respond(true, '', $this->service->getAvailablePortsByLcp((int)$lcpId, $includeCurrent), []);
    }

    public function napParentPorts($napId): void
    {
        $includeCurrent = isset($_GET['include_current']) && $_GET['include_current'] !== ''
            ? (int)$_GET['include_current']
            : null;

        $this->respond(true, '', $this->service->getAvailablePortsByNap((int)$napId, $includeCurrent), []);
    }

    public function odfPortGrid(): void
    {
        $this->respond(true, '', $this->service->getOdfPortGrid(), []);
    }

    public function lcpPortGrid(): void
    {
        $this->respond(true, '', $this->service->getLcpPortGrid(), []);
    }

    public function napPortGrid(): void
    {
        $this->respond(true, '', $this->service->getNapPortGrid(), []);
    }

    public function odfCandidates(): void
    {
        $this->respond(true, '', $this->service->getOdfCandidates(), []);
    }

    public function lcpCandidates(): void
    {
        $this->respond(true, '', $this->service->getLcpCandidates(), []);
    }

    public function napCandidates(): void
    {
        $excludeBoxId = isset($_GET['exclude_box_id']) && $_GET['exclude_box_id'] !== ''
            ? (int)$_GET['exclude_box_id']
            : null;

        $this->respond(true, '', $this->service->getNapCandidates($excludeBoxId), []);
    }

    public function topology(): void
    {
        try {
            $data = $this->service->getTopology();
            $this->respond(true, '', $data, []);
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), [], [], 500);
        }
    }

    public function topologyObjects(): void
    {
        try {
            $this->respond(true, '', $this->service->getTopologyObjects(), []);
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), [], [], 500);
        }
    }

    public function designProfiles(): void
    {
        $this->respond(true, '', $this->service->getDesignProfiles(), []);
    }

    /*
    |--------------------------------------------------------------------------
    | WRITE API
    |--------------------------------------------------------------------------
    */

    public function createOdf(): void
    {
        $this->respondServiceResult($this->service->createOdf($this->input()));
    }

    public function updateOdf($id): void
    {
        $this->respondServiceResult($this->service->updateOdf((int)$id, $this->input()));
    }

    public function deleteOdf(): void
    {
        $this->respondServiceResult($this->service->deleteOdf((int)($this->input()['id'] ?? 0)));
    }

    public function createLcp(): void
    {
        $this->respondServiceResult($this->service->createLcp($this->input()));
    }

    public function updateLcp($id): void
    {
        $this->respondServiceResult($this->service->updateLcp((int)$id, $this->input()));
    }

    public function deleteLcp(): void
    {
        $this->respondServiceResult($this->service->deleteLcp((int)($this->input()['id'] ?? 0)));
    }

    public function createNap(): void
    {
        $this->respondServiceResult($this->service->createNap($this->input()));
    }

    public function updateNap($id): void
    {
        $payload = $this->input();
        $payload['id'] = (int)$id;

        $this->respondServiceResult($this->service->updateNap($payload));
    }

    public function deleteNap(): void
    {
        $this->respondServiceResult($this->service->deleteNap((int)($this->input()['id'] ?? 0)));
    }

    public function generateDesign(): void
    {
        $this->respondServiceResult($this->service->generateNetworkDesign($this->input()));
    }

    /*
    |--------------------------------------------------------------------------
    | PLANNER API
    |--------------------------------------------------------------------------
    */

    public function plannerObjects(): void
    {
        try {
            $data = $this->service->getPlannerTopologyObjects();
            $this->respond(true, '', $data, []);
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), [], [], 500);
        }
    }

    public function plannerConnect(): void
    {
        $input = $this->input();

        $required = [
            'source_type',
            'source_id',
            'target_type',
            'target_id',
            'feed_mode',
            'parent_port_id',
        ];

        $errors = [];

        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                $label = match ($field) {
                    'parent_port_id' => 'Distribution source port',
                    'feed_mode' => 'Connection mode',
                    default => ucfirst(str_replace('_', ' ', $field)),
                };
                $errors[$field] = $label . ' is required.';
            }
        }

        $sourceType = strtoupper((string)($input['source_type'] ?? ''));
        $targetType = strtoupper((string)($input['target_type'] ?? ''));

        if ($sourceType !== 'LCP' && $sourceType !== 'NAP') {
            $errors['source_type'] = 'Source type must be LCP or NAP.';
        }

        if ($targetType !== 'NAP') {
            $errors['target_type'] = 'Target type must be NAP.';
        }

        if (!empty($errors)) {
            $this->respond(false, 'Invalid planner connect request.', null, $errors, 422);
        }

        if (!method_exists($this->service, 'plannerConnect')) {
            $this->respond(
                false,
                'plannerConnect() is not yet implemented in NapManagementService.',
                null,
                ['service' => 'Add plannerConnect(array $input): array to NapManagementService.'],
                500
            );
        }

        $this->respondServiceResult($this->service->plannerConnect($input));
    }

    public function plannerConnectObject(): void
    {
        $this->respondServiceResult($this->service->plannerConnectObject($this->input()));
    }

    public function plannerDeleteObjectLink(): void
    {
        $this->respondServiceResult($this->service->plannerDeleteObjectLink($this->input()));
    }

    public function plannerDeleteLink(): void
    {
        $input = $this->input();

        $required = [
            'source_type',
            'source_id',
            'target_type',
            'target_id',
        ];

        $errors = [];

        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        $sourceType = strtoupper((string)($input['source_type'] ?? ''));
        $targetType = strtoupper((string)($input['target_type'] ?? ''));

        if ($sourceType !== 'LCP' && $sourceType !== 'NAP') {
            $errors['source_type'] = 'Source type must be LCP or NAP.';
        }

        if ($targetType !== 'NAP') {
            $errors['target_type'] = 'Target type must be NAP.';
        }

        if (!empty($errors)) {
            $this->respond(false, 'Invalid planner delete-link request.', null, $errors, 422);
        }

        if (!method_exists($this->service, 'plannerDeleteLink')) {
            $this->respond(
                false,
                'plannerDeleteLink() is not yet implemented in NapManagementService.',
                null,
                ['service' => 'Add plannerDeleteLink(array $input): array to NapManagementService.'],
                500
            );
        }

        $this->respondServiceResult($this->service->plannerDeleteLink($input));
    }

    public function rebuildPlannerProjection(): void
    {
        try {
            $this->respondServiceResult($this->service->rebuildPlannerProjection());
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), [], [], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TOPOLOGY / DESIGN API
    |--------------------------------------------------------------------------
    */

    public function createTopologyObject(): void
    {
        $this->respondServiceResult($this->service->createTopologyObject($this->input()));
    }

    public function createTopologyLink(): void
    {
        $this->respondServiceResult($this->service->createTopologyLink($this->input()));
    }

    public function deleteTopologyLink(): void
    {
        $this->respondServiceResult($this->service->deleteTopologyLink((int)($this->input()['id'] ?? 0)));
    }

    /*
    |--------------------------------------------------------------------------
    | MAINTENANCE API
    |--------------------------------------------------------------------------
    */

    public function setOdfMaintenance($id): void
    {
        $res = $this->service->setOdfMaintenance((int)$id, $this->input());

        if (!is_array($res)) {
            $res = [
                'ok' => (bool)$res,
                'message' => $res ? 'ODF maintenance updated.' : 'Failed to update ODF maintenance.',
                'errors' => []
            ];
        }

        $this->respondServiceResult($res);
    }

    public function setLcpMaintenance($id): void
    {
        $res = $this->service->setLcpMaintenance((int)$id, $this->input());

        if (!is_array($res)) {
            $res = [
                'ok' => (bool)$res,
                'message' => $res ? 'LCP maintenance updated.' : 'Failed to update LCP maintenance.',
                'errors' => []
            ];
        }

        $this->respondServiceResult($res);
    }

    public function setNapMaintenance($id): void
    {
        $res = $this->service->setNapMaintenance((int)$id, $this->input());

        if (!is_array($res)) {
            $res = [
                'ok' => (bool)$res,
                'message' => $res ? 'NAP maintenance updated.' : 'Failed to update NAP maintenance.',
                'errors' => []
            ];
        }

        $this->respondServiceResult($res);
    }

    public function savePlannerNodePosition(): void
    {
        $this->respondServiceResult(
            $this->service->savePlannerNodePosition($this->input())
        );
    }

    public function resetPlannerLayout(): void
    {
        $this->respondServiceResult(
            $this->service->resetPlannerLayout()
        );
    }
}