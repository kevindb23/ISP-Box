<?php

namespace App\Modules\OltManagement\Controllers;

use Framework\ApiController;
use App\Modules\OltManagement\Services\OltManagementService;

class OltManagementApiController extends ApiController
{
    private OltManagementService $service;

    public function __construct(OltManagementService $service)
    {
        $this->service = $service;
    }

    private function input(): array
    {
        return $this->request()->input();
    }

    private function respond(bool $ok, string $message = '', $data = null, array $errors = [], int $httpCode = 200): void
    {
        $ok ? $this->success($data, $message ?: 'OK', $httpCode)
            : $this->error($message ?: 'Request failed.', $httpCode, $errors, $data);
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

        $httpCode = $ok ? 200 : 400;

        $this->respond($ok, $message, $data, $errors, $httpCode);
    }

    /* =========================================================
     * DEVICES
     * ========================================================= */
    public function devices(): void
    {
        $this->respond(true, '', $this->service->getAllDevices(), []);
    }

    public function device($id): void
    {
        $row = $this->service->getDeviceById((int)$id);

        if ($row === null) {
            $this->respond(false, 'OLT device not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function createDevice(): void
    {
        $this->respondServiceResult($this->service->createDevice($this->input()));
    }

    public function updateDevice($id): void
    {
        $this->respondServiceResult($this->service->updateDevice((int)$id, $this->input()));
    }

    public function deleteDevice(): void
    {
        $id = (int)($this->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deleteDevice($id));
    }

    /* =========================================================
     * PORTS
     * ========================================================= */
    public function ports(): void
    {
        $oltId = (int)($this->request()->query()['olt_id'] ?? 0);

        $data = $oltId > 0
            ? $this->service->getPortsByOltId($oltId)
            : $this->service->getAllPorts();

        $this->respond(true, '', $data, []);
    }

    public function portsByOlt($oltId): void
    {
        $this->respond(true, '', $this->service->getPortsByOltId((int)$oltId), []);
    }

    public function port($id): void
    {
        $row = $this->service->getPortById((int)$id);

        if ($row === null) {
            $this->respond(false, 'OLT port not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function availablePorts($oltId): void
    {
        $this->respond(true, '', $this->service->getAvailablePorts((int)$oltId), []);
    }

    public function createPort(): void
    {
        $this->respondServiceResult($this->service->createPort($this->input()));
    }

    public function updatePort($id): void
    {
        $this->respondServiceResult($this->service->updatePort((int)$id, $this->input()));
    }

    public function deletePort(): void
    {
        $id = (int)($this->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deletePort($id));
    }

    public function fetchPorts(): void
    {
        $oltId = (int)($this->input()['olt_id'] ?? 0);
        $this->respondServiceResult($this->service->fetchPorts($oltId));
    }

    public function importFetchedPorts(): void
    {
        $oltId = (int)($this->input()['olt_id'] ?? 0);
        $startingSvlanRaw = $this->input()['starting_svlan'] ?? '';
        $startingSvlan = ($startingSvlanRaw === '') ? null : (int)$startingSvlanRaw;

        $portsJson = $this->input()['ports_json'] ?? '[]';
        $ports = json_decode($portsJson, true);

        if (!is_array($ports)) {
            $ports = [];
        }

        $this->respondServiceResult(
            $this->service->importFetchedPorts($oltId, $ports, $startingSvlan)
        );
    }

    /* =========================================================
     * LINE PROFILES
     * ========================================================= */
    public function lineProfiles(): void
    {
        $oltId = (int)($this->request()->query()['olt_id'] ?? 0);

        $data = $oltId > 0
            ? $this->service->getLineProfilesByOltId($oltId)
            : $this->service->getAllLineProfiles();

        $this->respond(true, '', $data, []);
    }

    public function lineProfile($id): void
    {
        $row = $this->service->getLineProfileById((int)$id);

        if ($row === null) {
            $this->respond(false, 'OLT line profile not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function createLineProfile(): void
    {
        $this->respondServiceResult($this->service->createLineProfile($this->input()));
    }

    public function updateLineProfile($id): void
    {
        $this->respondServiceResult($this->service->updateLineProfile((int)$id, $this->input()));
    }

    public function deleteLineProfile(): void
    {
        $id = (int)($this->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deleteLineProfile($id));
    }

    public function lineProfileCliPreview($id): void
    {
        $res = $this->service->getLineProfileCliPreview((int)$id);

        if (!($res['ok'] ?? false)) {
            $this->respond(false, $res['message'] ?? 'Unable to generate CLI preview.', null, $res['errors'] ?? [], 400);
        }

        $this->respond(true, $res['message'] ?? '', $res['data'] ?? null, [], 200);
    }

    /* =========================================================
     * DBA PROFILES
     * ========================================================= */
    public function dbaProfiles(): void
    {
        $oltIdRaw = $this->request()->query()['olt_id'] ?? null;
        $oltId = ($oltIdRaw === '' || $oltIdRaw === null) ? null : (int)$oltIdRaw;

        $data = $oltId !== null && $oltId > 0
            ? $this->service->getDbaProfilesByOltId($oltId)
            : $this->service->getAllDbaProfiles();

        $this->respond(true, '', $data, []);
    }

    public function dbaProfile($id): void
    {
        $row = $this->service->getDbaProfileById((int)$id);

        if ($row === null) {
            $this->respond(false, 'OLT DBA profile not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function createDbaProfile(): void
    {
        $this->respondServiceResult($this->service->createDbaProfile($this->input()));
    }

    public function updateDbaProfile($id): void
    {
        $this->respondServiceResult($this->service->updateDbaProfile((int)$id, $this->input()));
    }

    public function deleteDbaProfile(): void
    {
        $id = (int)($this->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deleteDbaProfile($id));
    }

    public function dbaProfileCliPreview($id): void
    {
        $res = $this->service->getDbaProfileCliPreview((int)$id);

        if (!($res['ok'] ?? false)) {
            $this->respond(false, $res['message'] ?? 'Unable to generate CLI preview.', null, $res['errors'] ?? [], 400);
        }

        $this->respond(true, $res['message'] ?? '', $res['data'] ?? null, [], 200);
    }

    /* =========================================================
     * WAN PROFILES
     * ========================================================= */
    public function wanProfiles(): void
    {
        $oltIdRaw = $this->request()->query()['olt_id'] ?? null;
        $oltId = ($oltIdRaw === '' || $oltIdRaw === null) ? null : (int)$oltIdRaw;

        $data = $oltId !== null && $oltId > 0
            ? $this->service->getWanProfilesByOltId($oltId)
            : $this->service->getAllWanProfiles();

        $this->respond(true, '', $data, []);
    }

    public function wanProfile($id): void
    {
        $row = $this->service->getWanProfileById((int)$id);

        if ($row === null) {
            $this->respond(false, 'OLT WAN profile not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function createWanProfile(): void
    {
        $this->respondServiceResult($this->service->createWanProfile($this->input()));
    }

    public function updateWanProfile($id): void
    {
        $this->respondServiceResult($this->service->updateWanProfile((int)$id, $this->input()));
    }

    public function deleteWanProfile(): void
    {
        $id = (int)($this->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deleteWanProfile($id));
    }

    public function wanProfileCliPreview($id): void
    {
        $res = $this->service->getWanProfileCliPreview((int)$id);

        if (!($res['ok'] ?? false)) {
            $this->respond(false, $res['message'] ?? 'Unable to generate CLI preview.', null, $res['errors'] ?? [], 400);
        }

        $this->respond(true, $res['message'] ?? '', $res['data'] ?? null, [], 200);
    }

    /* =========================================================
     * TR069 PROFILES
     * ========================================================= */
    public function tr069Profiles(): void
    {
        $oltIdRaw = $this->request()->query()['olt_id'] ?? null;
        $oltId = ($oltIdRaw === '' || $oltIdRaw === null) ? null : (int)$oltIdRaw;

        $data = $oltId !== null && $oltId > 0
            ? $this->service->getTr069ProfilesByOltId($oltId)
            : $this->service->getAllTr069Profiles();

        $this->respond(true, '', $data, []);
    }

    public function tr069Profile($id): void
    {
        $row = $this->service->getTr069ProfileById((int)$id);

        if ($row === null) {
            $this->respond(false, 'OLT TR069 profile not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function createTr069Profile(): void
    {
        $this->respondServiceResult($this->service->createTr069Profile($this->input()));
    }

    public function updateTr069Profile($id): void
    {
        $this->respondServiceResult($this->service->updateTr069Profile((int)$id, $this->input()));
    }

    public function deleteTr069Profile(): void
    {
        $id = (int)($this->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deleteTr069Profile($id));
    }

    public function tr069ProfileCliPreview($id): void
    {
        $res = $this->service->getTr069ProfileCliPreview((int)$id);

        if (!($res['ok'] ?? false)) {
            $this->respond(false, $res['message'] ?? 'Unable to generate CLI preview.', null, $res['errors'] ?? [], 400);
        }

        $this->respond(true, $res['message'] ?? '', $res['data'] ?? null, [], 200);
    }

    /* =========================================================
     * SERVICE PROFILES
     * ========================================================= */
    public function srvProfiles(): void
    {
        $oltIdRaw = $this->request()->query()['olt_id'] ?? null;
        $oltId = ($oltIdRaw === '' || $oltIdRaw === null) ? null : (int)$oltIdRaw;

        $data = $oltId !== null && $oltId > 0
            ? $this->service->getSrvProfilesByOltId($oltId)
            : $this->service->getAllSrvProfiles();

        $this->respond(true, '', $data, []);
    }

    public function srvProfile($id): void
    {
        $row = $this->service->getSrvProfileById((int)$id);

        if ($row === null) {
            $this->respond(false, 'OLT service profile not found.', null, [], 404);
        }

        $this->respond(true, '', $row, []);
    }

    public function createSrvProfile(): void
    {
        $this->respondServiceResult($this->service->createSrvProfile($this->input()));
    }

    public function updateSrvProfile($id): void
    {
        $this->respondServiceResult($this->service->updateSrvProfile((int)$id, $this->input()));
    }

    public function deleteSrvProfile(): void
    {
        $id = (int)($this->input()['id'] ?? 0);
        $this->respondServiceResult($this->service->deleteSrvProfile($id));
    }

    public function srvProfileCliPreview($id): void
    {
        $res = $this->service->getSrvProfileCliPreview((int)$id);

        if (!($res['ok'] ?? false)) {
            $this->respond(false, $res['message'] ?? 'Unable to generate CLI preview.', null, $res['errors'] ?? [], 400);
        }

        $this->respond(true, $res['message'] ?? '', $res['data'] ?? null, [], 200);
    }

    /* =========================================================
 * CONTROL BOARD VLAN BINDINGS
 * ========================================================= */

public function controlBoardVlanWorkspace($oltId): void
{
    $this->respond(
        true,
        '',
        $this->service->getControlBoardVlanWorkspace((int)$oltId),
        []
    );
}

public function controlBoardVlanOptions($oltId): void
{
    $query = $this->request()->query();
    $type = (string)($query['type'] ?? 'SERVICE');
    $portId = (int)($query['port_id'] ?? 0);

    $this->respond(
        true,
        '',
        $this->service->getVlanBindingOptions((int)$oltId, $type, $portId),
        []
    );
}

public function createControlBoardVlanBinding(): void
{
    $this->respondServiceResult(
        $this->service->createPortVlanBinding($this->input())
    );
}

public function deleteControlBoardVlanBinding(): void
{
    $id = (int)($this->input()['id'] ?? 0);

    $this->respondServiceResult(
        $this->service->deletePortVlanBinding($id)
    );
}

public function ponSvlanOptions($oltId): void
{
    $portId = (int)($this->request()->query()['port_id'] ?? 0);

    $rows = $this->service->getPonSvlanOptions((int)$oltId, $portId);

    $this->respond(true, '', $rows, []);
}

public function assignPonSvlan(): void
{
    $portId = (int)($this->input()['olt_port_id'] ?? 0);
    $svlan  = (int)($this->input()['svlan'] ?? 0);

    if ($portId <= 0 || $svlan <= 0) {
        $this->respond(false, 'PON port and SVLAN are required.', null, [], 400);
    }

    $res = $this->service->assignPonPortSvlan($portId, $svlan);

    if (is_array($res)) {
        $this->respondServiceResult($res);
    }

    $this->respond(true, 'PON port SVLAN assigned.', [], []);
}

public function unassignPonSvlan(): void
{
    $portId = (int)($this->input()['olt_port_id'] ?? 0);

    if ($portId <= 0) {
        $this->respond(false, 'PON port is required.');
    }

    $this->respondServiceResult(
        $this->service->unassignPonPortSvlan($portId)
    );
}


}
