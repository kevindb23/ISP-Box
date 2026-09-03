<?php

namespace App\Modules\OltManagement\Controllers;

use Framework\Controller;
use App\Modules\OltManagement\Services\OltManagementService;
use Framework\Request;

class OltManagementController extends Controller
{
    private OltManagementService $service;

    public function __construct(OltManagementService $service, private Request $request)
    {
        $this->service = $service;
    }

    private function jsonResponse(bool $ok, string $message = '', array $data = [], array $errors = []): void
    {
        $ok ? $this->success($data, $message ?: 'OK') : $this->error($message ?: 'Request failed.', 422, $errors, $data);
        exit;
    }

    private function respondServiceResult(array $res): void
    {
        $ok = (bool)($res['ok'] ?? false);
        $message = (string)($res['message'] ?? '');
        $errors = [];

        if (!empty($res['errors']) && is_array($res['errors'])) {
            $errors = array_values($res['errors']);
        }

        $data = $res;
        unset($data['ok'], $data['message'], $data['errors']);

        $this->jsonResponse($ok, $message, $data, $errors);
    }

    /**
     * DEVICES PAGE
     * Route: /olt-management
     */
    public function index()
    {
        $data = $this->service->getIndexData(null, null);

        return $this->view('OltManagement/index', array_merge($data, [
            'pageMode' => 'devices',
            'selectedOltId' => 0,
            'selectedSlot' => null,
        ]));
    }

    /**
     * PORTS PAGE
     * Route: /olt-management/ports?olt_id=1&selected_slot=2
     */
    public function ports()
    {
        $query = $this->request->query();
        $selectedOltId = (int)($query['olt_id'] ?? 0);
        $selectedSlot = isset($query['selected_slot']) && $query['selected_slot'] !== ''
            ? (int)$query['selected_slot']
            : null;

        $data = $this->service->getIndexData(
            $selectedOltId > 0 ? $selectedOltId : null,
            $selectedSlot
        );

        return $this->view('OltManagement/ports', array_merge($data, [
            'pageMode' => 'ports',
            'selectedOltId' => $selectedOltId,
            'selectedSlot' => $selectedSlot,
        ]));
    }

    /**
     * PROFILES PAGE
     * Route: /olt-management/profiles?olt_id=1&tab=line
     */
    public function profiles()
    {
        $query = $this->request->query();
        $selectedOltId = (int)($query['olt_id'] ?? 0);
        $activeTab = strtolower(trim((string)($query['tab'] ?? 'line')));

        $allowedTabs = ['dba', 'line', 'wan', 'tr069', 'srv'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'line';
        }

        $devices = $this->service->getAllDevices();
        $selectedOlt = null;

        if ($selectedOltId > 0) {
            $selectedOlt = $this->service->getDeviceById($selectedOltId);
        }

        return $this->view('OltManagement/profiles', [
            'pageMode' => 'profiles',
            'selectedOltId' => $selectedOltId,
            'selectedOlt' => $selectedOlt,
            'oltDevices' => $devices,
            'activeTab' => $activeTab,
        ]);
    }

    public function createDevice()
    {
        $this->respondServiceResult($this->service->createDevice($this->request->input()));
    }

    public function updateDevice($id)
    {
        $this->respondServiceResult($this->service->updateDevice((int)$id, $this->request->input()));
    }

    public function deleteDevice()
    {
        $id = (int)($this->request->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deleteDevice($id));
    }

    public function updatePort($id)
    {
        $this->respondServiceResult($this->service->updatePort((int)$id, $this->request->input()));
    }

    public function deletePort()
    {
        $id = (int)($this->request->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deletePort($id));
    }

    public function fetchPorts()
    {
        $oltId = (int)($this->request->input()['olt_id'] ?? 0);
        $this->respondServiceResult($this->service->fetchPorts($oltId));
    }

    public function importFetchedPorts()
    {
        $input = $this->request->input();
        $oltId = (int)($input['olt_id'] ?? 0);
        $startingSvlanRaw = $input['starting_svlan'] ?? '';
        $startingSvlan = ($startingSvlanRaw === '' ? null : (int)$startingSvlanRaw);

        $portsJson = $input['ports_json'] ?? '[]';
        $ports = json_decode($portsJson, true);

        if (!is_array($ports)) {
            $ports = [];
        }

        $this->respondServiceResult(
            $this->service->importFetchedPorts($oltId, $ports, $startingSvlan)
        );
    }

    public function availablePorts($oltId): void
    {
        $this->jsonResponse(true, '', [
            'items' => $this->service->getAvailablePorts((int)$oltId)
        ], []);
    }
}
