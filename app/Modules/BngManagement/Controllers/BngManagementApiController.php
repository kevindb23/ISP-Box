<?php

namespace App\Modules\BngManagement\Controllers;

use App\Modules\BngManagement\DTOs\UpdateAccelPppConfigDTO;
use App\Modules\BngManagement\DTOs\UpdateBngSettingDTO;
use App\Modules\BngManagement\Services\AccelPppConfigService;
use App\Modules\BngManagement\Services\BngConnectionService;
use App\Modules\BngManagement\Validators\UpdateBngSettingValidator;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

final class BngManagementApiController extends ApiController
{
    public function __construct(private BngConnectionService $bng, private AccelPppConfigService $accel, private UpdateBngSettingValidator $validator)
    {
    }

    public function setting(): mixed { try{return $this->jsonOk($this->bng->getSetting());}catch(Throwable $e){return $this->jsonError($e->getMessage(),500);} }
    public function settings(): mixed { try{return $this->jsonOk($this->bng->getSettings());}catch(Throwable $e){return $this->jsonError($e->getMessage(),500);} }
    public function saveSetting(): mixed
    {
        $payload=$this->request()->input(); $errors=$this->validator->validate($payload);
        if($errors){$this->error('Validation failed.',422,$errors);return null;}
        try{$saved=$this->bng->saveSetting(new UpdateBngSettingDTO($payload));return $this->jsonMessage($saved,'BNG connection saved.');}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);}
    }
    public function deleteSetting(): mixed { try{$this->bng->deleteSetting();return $this->jsonMessage(true,'BNG connection deleted.');}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);} }
    public function testConnection(): mixed { try{return $this->jsonMessage($this->bng->testConnection(),'BNG connection test completed.');}catch(Throwable $e){return $this->jsonError($e->getMessage(),500);} }
    public function scanHostKey(): mixed { try{return $this->jsonOk($this->bng->scanHostKey());}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);} }
    public function trustHostKey(): mixed { try{return $this->jsonMessage($this->bng->trustHostKey((string)($this->request()->input()['fingerprint']??'')),'BNG SSH host key trusted.');}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);} }
    public function runtime(): mixed { try { if (!$this->bng->getSetting()) return $this->jsonOk(['svlan_groups'=>[], 'client_vlan_interfaces'=>[], 'bng_interfaces'=>[]]); return $this->jsonOk($this->bng->getRuntimeStatus()); } catch(Throwable $e){return $this->jsonError($e->getMessage(),500);} }
    public function accel(): mixed { try{return $this->jsonOk($this->accel->get());}catch(Throwable $e){return $this->jsonError($e->getMessage(),500);} }
    public function saveAccel(): mixed { try{return $this->jsonMessage($this->accel->save(new UpdateAccelPppConfigDTO($this->request()->input()),(int)SessionManager::id()),'Accel-PPP draft saved.');}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);} }
    public function previewAccel(): mixed { try{return $this->jsonOk($this->accel->preview());}catch(Throwable $e){return $this->jsonError($e->getMessage(),404);} }
    public function previewAccelDraft(): mixed { try{return $this->jsonOk($this->accel->previewDraft(new UpdateAccelPppConfigDTO($this->request()->input())));}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);} }
    public function stageAccel(): mixed { try{return $this->jsonMessage($this->accel->stage((int)SessionManager::id()),'Accel-PPP configuration staged for maintenance.');}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);} }
    public function activateAccel(): mixed { try{$input=$this->request()->input();return $this->jsonMessage($this->accel->activateMaintenance((int)SessionManager::id(),(string)($input['confirmation']??''),(int)($input['acknowledge_disconnect']??0)===1),'Maintenance activation completed.');}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);} }
    public function installBootRecovery(): mixed { try{return $this->jsonMessage($this->accel->installBootRecovery((int)SessionManager::id(),(string)($this->request()->input()['confirmation']??'')),'BNG boot recovery installed and enabled.');}catch(Throwable $e){return $this->jsonError($e->getMessage(),422);} }

    private function jsonOk(mixed $data) { $this->success($data); return null; }
    private function jsonMessage(mixed $data,string $message) { $this->success($data,$message); return null; }
    private function jsonError(string $message,int $status) { $this->error($message,$status); return null; }
}
