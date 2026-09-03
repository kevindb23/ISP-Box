<?php
namespace App\Modules\RouterManagement\Controllers;
use App\Modules\RouterManagement\DTOs\UpdateCoreRouterDTO;
use App\Modules\RouterManagement\DTOs\UpdateFrrRouterDTO;
use App\Modules\RouterManagement\Services\RouterManagementService;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;
final class RouterManagementApiController extends ApiController
{
    public function __construct(private RouterManagementService $service){}
    public function index():mixed{try{$this->success($this->service->get());}catch(Throwable$e){$this->error($e->getMessage(),500);}return null;}
    public function saveCore():mixed{try{$this->success($this->service->saveCore((new UpdateCoreRouterDTO($this->request()->input()))->toArray(),(int)SessionManager::id()),'Core Router draft saved.');}catch(Throwable$e){$this->error($e->getMessage(),422);}return null;}
    public function saveFrr():mixed{try{$this->success($this->service->saveFrr((new UpdateFrrRouterDTO($this->request()->input()))->toArray(),(int)SessionManager::id()),'FRR Router draft saved.');}catch(Throwable$e){$this->error($e->getMessage(),422);}return null;}
    public function scanCore():mixed{try{$this->success($this->service->scanCoreHostKey());}catch(Throwable$e){$this->error($e->getMessage(),422);}return null;}
    public function trustCore():mixed{try{$this->success($this->service->trustCoreHostKey((string)($this->request()->input()['fingerprint']??'')),'Core Router SSH host key trusted.');}catch(Throwable$e){$this->error($e->getMessage(),422);}return null;}
    public function runtimeCore():mixed{return$this->runtime('CORE');}public function runtimeFrr():mixed{return$this->runtime('FRR');}
    public function applyCore():mixed{return$this->apply('CORE');}public function applyFrr():mixed{return$this->apply('FRR');}
    private function runtime(string$type):mixed{try{$this->success($this->service->runtime($type));}catch(Throwable$e){$this->error($e->getMessage(),422);}return null;}
    private function apply(string$type):mixed{try{$input=$this->request()->input();$this->success($this->service->apply($type,(string)($input['confirmation']??''),(string)($input['config_hash']??''),(int)SessionManager::id()),$type.' Router configuration applied.');}catch(Throwable$e){$this->error($e->getMessage(),422);}return null;}
}
