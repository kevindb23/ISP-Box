<?php
namespace App\Modules\RouterManagement\Services;
use App\Infrastructure\NetworkAutomation\NetworkCommandRunner;
use App\Modules\Audit\Services\AuditService;
use App\Modules\BngManagement\Services\BngConnectionService;
use App\Modules\BngManagement\Services\NetworkIntegrationValidator;
use App\Modules\RouterManagement\Repositories\RouterManagementRepository;
use App\Modules\RouterManagement\Validators\CoreRouterValidator;
use App\Modules\RouterManagement\Validators\FrrRouterValidator;
use InvalidArgumentException;
use Throwable;
final class RouterManagementService
{
    public function __construct(private RouterManagementRepository $repo,private RouterConfigRenderer $renderer,private CoreRouterValidator $coreValidator,private FrrRouterValidator $frrValidator,private BngConnectionService $bng,private NetworkCommandRunner $runner,private NetworkIntegrationValidator $integrationValidator,private ?AuditService $audit=null){}
    public function get():array
    {
        $core=$this->repo->core();$frr=$this->repo->frr();
        return ['core'=>$core,'frr'=>$frr,'core_hash'=>$core?hash('sha256',(string)$core['rendered_config']):null,'frr_hash'=>$frr?hash('sha256',(string)$frr['rendered_config']):null,'core_defaults'=>$this->coreDefaults(),'frr_defaults'=>$this->frrDefaults(),'deployments'=>$this->repo->deployments()];
    }
    public function saveCore(array $data,int $user):array
    {
        $existing=$this->repo->core(true);$errors=$this->coreValidator->validate($data,!empty($existing['password']));if($errors)throw new InvalidArgumentException((string)reset($errors));$connection=array_intersect_key($data,array_flip(['enabled','host','port','username','password']));$config=array_diff_key($data,$connection);$this->integrationValidator->assertCore($config);$rendered=$this->renderer->core($config);$saved=$this->repo->saveCore($connection,$config,$rendered,$user);$this->log('SAVE_CORE_ROUTER','Saved Core Router configuration draft for '.$connection['host'].'.');return$saved;
    }
    public function saveFrr(array $data,int $user):array{$errors=$this->frrValidator->validate($data);if($errors)throw new InvalidArgumentException((string)reset($errors));$this->integrationValidator->assertFrr($data);$rendered=$this->renderer->frr($data);$saved=$this->repo->saveFrr($data,$rendered,$user);$this->log('SAVE_FRR_ROUTER','Saved FRR Router configuration draft using the BNG connection.');return$saved;}
    public function scanCoreHostKey():array
    {
        $core=$this->repo->core(true);if(!$core)throw new InvalidArgumentException('Save the Core Router connection first.');$result=$this->runner->run(['/usr/bin/ssh-keyscan','-p',(string)$core['port'],'-T','8',(string)$core['host']],'',15);if(!$result->succeeded()||trim($result->stdout)==='')throw new InvalidArgumentException('Unable to scan the Core Router SSH host key.');$line=$this->firstKey($result->stdout);return['fingerprint'=>$this->fingerprint($line),'key'=>$line,'host'=>$core['host'],'port'=>$core['port']];
    }
    public function trustCoreHostKey(string $fingerprint):array{$scan=$this->scanCoreHostKey();if(!hash_equals($scan['fingerprint'],trim($fingerprint)))throw new InvalidArgumentException('Core Router fingerprint does not match the current scan.');$core=$this->repo->core(true);$this->repo->trustCore((int)$core['id'],$scan['key'],$scan['fingerprint']);$this->log('TRUST_CORE_ROUTER_HOST_KEY','Trusted Core Router SSH fingerprint '.$scan['fingerprint'].'.');return$this->repo->core()??[];}
    public function runtime(string $type):array
    {
        if($type==='CORE'){$core=$this->repo->core(true);if(!$core)throw new InvalidArgumentException('Core Router is not configured.');$config=$this->runCore($core,"cli -c 'show configuration routing-options | display set; show configuration protocols bgp | display set; show bgp summary'");if((int)$config['exit_code']!==0)throw new InvalidArgumentException('Unable to read Core Router runtime: '.trim((string)$config['stderr']));return['type'=>'CORE','output'=>$config['stdout'],'error'=>'','exit_code'=>0];}
        $setting=$this->bng->getFullSetting();$command="/usr/bin/vtysh -c 'show running-config' -c 'show bgp ipv4 unicast summary'";$run=$this->runFrr($setting,$command);if((int)$run['exit_code']!==0)throw new InvalidArgumentException('Unable to read FRR runtime: '.trim((string)$run['stderr']));return['type'=>'FRR','output'=>$run['stdout'],'error'=>'','exit_code'=>0];
    }
    public function apply(string $type,string $confirmation,string $expectedHash,int $user):array
    {
        $expected='APPLY '.$type.' ROUTER CONFIG';if($confirmation!==$expected)throw new InvalidArgumentException('Type the exact confirmation phrase: '.$expected);
        $profile=$type==='CORE'?$this->repo->core(true):$this->repo->frr();if(!$profile)throw new InvalidArgumentException($type.' Router configuration is not saved.');
        $hash=hash('sha256',(string)$profile['rendered_config']);if($expectedHash===''||!hash_equals($hash,$expectedHash))throw new InvalidArgumentException('The saved Router draft changed. Reload and review it before applying.');
        if($type==='CORE'){$commands=array_filter(explode("\n",trim((string)$profile['rendered_config'])));$cli='configure; '.implode('; ',$commands).'; commit check; commit; exit';$result=$this->runCore($profile,'cli -c '.escapeshellarg($cli));}
        else{$setting=$this->bng->getFullSetting();$parts=["/usr/bin/vtysh -c 'configure terminal'"];foreach(array_filter(explode("\n",trim((string)$profile['rendered_config'])))as $line)$parts[]='-c '.escapeshellarg($line);$parts[]="-c 'end' -c 'write memory'";$command=implode(' ',$parts);$result=$this->runFrr($setting,$command);}
        $ok=(int)$result['exit_code']===0;$message=$ok?'Configuration applied successfully.':('Apply failed: '.($result['stderr']?:$result['stdout']));$this->repo->deployment($type,$ok?'SUCCESS':'FAILED',$hash,$message,$user);$this->log($ok?'APPLY_'.$type.'_ROUTER':'APPLY_'.$type.'_ROUTER_FAILED',$message);if(!$ok)throw new InvalidArgumentException($message);return['type'=>$type,'status'=>'SUCCESS','config_hash'=>$hash,'output'=>$result['stdout']];
    }
    private function runCore(array $core,string $command):array
    {
        if(empty($core['known_host_key']))throw new InvalidArgumentException('Core Router SSH host key is not trusted.');$dir=BASE_PATH.'/storage/runtime';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new InvalidArgumentException('Unable to prepare router runtime storage.');$known=$dir.'/core_router_known_hosts';file_put_contents($known,$core['known_host_key']."\n",LOCK_EX);chmod($known,0600);$args=['/usr/bin/sshpass','-d','3','/usr/bin/ssh','-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='.$known,'-o','LogLevel=ERROR','-o','ConnectTimeout=8','-p',(string)$core['port'],$core['username'].'@'.$core['host'],$command];$r=$this->runner->run($args,'',45,[3=>(string)$core['password']."\n"]);return['stdout'=>$r->stdout,'stderr'=>$r->stderr,'exit_code'=>$r->exitCode];
    }
    private function runFrr(array $setting,string $command):array
    {
        if(strtolower((string)$setting['username'])==='root')return$this->bng->runRemoteCommand($setting,$command);
        $password=(string)($setting['password']??'');if($password==='')throw new InvalidArgumentException('The saved BNG password is required for privileged FRR access.');
        return$this->bng->runRemoteCommandWithInput($setting,"sudo -S -p '' ".$command,$password."\n");
    }
    private function firstKey(string $output):string{foreach(preg_split('/\R/',$output)?:[]as$line)if($line!==''&&!str_starts_with($line,'#'))return trim($line);throw new InvalidArgumentException('No SSH host key was returned.');}
    private function fingerprint(string $line):string{$parts=preg_split('/\s+/',trim($line));$decoded=isset($parts[2])?base64_decode($parts[2],true):false;if($decoded===false)throw new InvalidArgumentException('Scanned SSH host key is invalid.');return'SHA256:'.rtrim(base64_encode(hash('sha256',$decoded,true)),'=');}
    private function log(string $action,string $message):void{try{$this->audit?->log('ROUTERS',$action,$message);}catch(Throwable){}}
    private function coreDefaults():array{return['enabled'=>1,'host'=>'10.0.15.6','port'=>22,'username'=>'root','autonomous_system'=>'65000','default_next_hop'=>'10.0.26.1','public_prefix'=>'126.209.31.168/29','bng_next_hop'=>'10.255.255.2','bgp_group'=>'BNG','local_address'=>'10.255.255.1','peer_as'=>'65001','neighbor'=>'10.255.255.2','interface_name'=>'xe-0/1/6','interface_description'=>'LINK TO BNG SERVER','interface_address'=>'10.255.255.1/30'];}
    private function frrDefaults():array{return['enabled'=>1,'autonomous_system'=>'65001','router_id'=>'10.255.255.2','neighbor'=>'10.255.255.1','remote_as'=>'65000','subscriber_network'=>'100.64.0.0/24','in_prefix_list'=>'PL-DEFAULT','out_prefix_list'=>'PL-SUBS','in_route_map'=>'RM-IN-DEFAULT','out_route_map'=>'RM-OUT-SUBS'];}
}
