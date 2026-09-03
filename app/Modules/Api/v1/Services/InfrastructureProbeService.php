<?php

namespace App\Modules\Api\v1\Services;

use App\Infrastructure\NetworkAutomation\NetworkCommandRunner;
use App\Modules\BngManagement\Services\BngConnectionService;
use Throwable;

/** Executes a fixed allowlist of read-only health commands and returns aggregates only. */
final class InfrastructureProbeService
{
    public function __construct(private NetworkCommandRunner $runner, private BngConnectionService $bng) {}

    public function collect(): array
    {
        return ['local_services'=>$this->localServices(),'failed_systemd_units'=>$this->failedUnits(),'remote'=>$this->bngServices()];
    }

    private function localServices(): array
    {
        $services=['web'=>['nginx.service','apache2.service'],'mariadb'=>['mariadb.service','mysql.service']];
        $out=[]; foreach($services as $name=>$candidates){$out[$name]=['status'=>'DISABLED','unit'=>null];foreach($candidates as $unit){$r=$this->runner->run(['/bin/systemctl','is-active',$unit],'',3);$state=trim($r->stdout);if($state==='active'){$out[$name]=['status'=>'HEALTHY','unit'=>$unit];break;}if(!in_array($state,['unknown','inactive'],true)&&$state!=='')$out[$name]=['status'=>'CRITICAL','unit'=>$unit];}}
        return $out;
    }

    private function failedUnits(): array
    {
        try{$r=$this->runner->run(['/bin/systemctl','--failed','--no-legend','--plain'],'',5);$count=count(array_filter(preg_split('/\R/',trim($r->stdout))?:[]));return ['status'=>$count>0?'DEGRADED':'HEALTHY','count'=>$count];}
        catch(Throwable){return ['status'=>'UNKNOWN','count'=>null];}
    }

    private function bngServices(): array
    {
        try{
            $setting=$this->bng->getFullSetting();
            $command="printf 'accel='; /bin/systemctl is-active accel-ppp.service 2>/dev/null || true; printf 'frr='; /bin/systemctl is-active frr.service 2>/dev/null || true; printf 'reconcile='; /bin/systemctl is-active nexusbox-bng-reconcile.service 2>/dev/null || true; printf 'ip_forward='; /bin/cat /proc/sys/net/ipv4/ip_forward 2>/dev/null || true; printf 'conntrack_count='; /bin/cat /proc/sys/net/netfilter/nf_conntrack_count 2>/dev/null || true; printf 'conntrack_max='; /bin/cat /proc/sys/net/netfilter/nf_conntrack_max 2>/dev/null || true; printf 'bgp='; /usr/bin/vtysh -c 'show bgp ipv4 unicast summary json' 2>/dev/null | /usr/bin/sha256sum | /usr/bin/cut -d' ' -f1 || true";
            $r=$this->bng->runRemoteCommand($setting,$command); if((int)$r['exit_code']!==0)return ['status'=>'CRITICAL','error_code'=>'REMOTE_DIAGNOSTIC_FAILED'];
            $values=[];foreach(preg_split('/\R/',trim((string)$r['stdout']))?:[]as$line){[$key,$value]=array_pad(explode('=',trim($line),2),2,'');if($key!=='')$values[$key]=trim($value);}
            $count=is_numeric($values['conntrack_count']??null)?(int)$values['conntrack_count']:null;$max=is_numeric($values['conntrack_max']??null)?(int)$values['conntrack_max']:null;
            return ['status'=>($values['accel']??'')==='active'&&($values['frr']??'')==='active'?'HEALTHY':'CRITICAL','accel_ppp'=>$this->state($values['accel']??''),'frr'=>$this->state($values['frr']??''),'boot_reconcile'=>$this->state($values['reconcile']??''),'ip_forwarding'=>($values['ip_forward']??'0')==='1','conntrack_count'=>$count,'conntrack_max'=>$max,'conntrack_percent'=>$count!==null&&$max>0?round($count/$max*100,1):null,'bgp_runtime_hash'=>preg_match('/^[a-f0-9]{64}$/',$values['bgp']??'')?($values['bgp']):null];
        }catch(Throwable){return ['status'=>'UNKNOWN','error_code'=>'REMOTE_DIAGNOSTIC_UNAVAILABLE'];}
    }

    private function state(string $state): string{return trim($state)==='active'?'HEALTHY':(trim($state)===''?'UNKNOWN':'CRITICAL');}
}
