<?php

namespace App\Modules\BngManagement\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\BngManagement\DTOs\UpdateAccelPppConfigDTO;
use App\Modules\BngManagement\Repositories\AccelPppConfigRepository;
use App\Modules\BngManagement\Repositories\BngDesiredStateRepository;
use App\Modules\Radius\Repositories\RadiusSettingsRepository;
use App\Modules\BngManagement\Validators\AccelPppConfigValidator;
use RuntimeException;
use Throwable;

final class AccelPppConfigService
{
    public function __construct(
        private AccelPppConfigRepository $repo,
        private AccelPppConfigRenderer $renderer,
        private AccelPppConfigValidator $validator,
        private BngConnectionService $bng,
        private BngDesiredStateRepository $desiredState,
        private RadiusSettingsRepository $radiusSettings,
        private NetworkIntegrationValidator $integrationValidator,
        private ?AuditService $audit = null
    ) {
    }

    public function get(): array
    {
        $profile=$this->repo->get();$defaults=(new UpdateAccelPppConfigDTO([]))->toArray();$warnings=[];
        $setting=$this->bng->getSetting();$parent=(string)($setting['bng_parent_interface']??'');
        if($profile&&$parent!==''&&!str_contains((string)($profile['config']['pppoe_interface']??''),preg_quote($parent,'/'))&&!str_contains((string)($profile['config']['pppoe_interface']??''),$parent))$warnings[]='Accel-PPP interface expression does not match the configured BNG parent interface '.$parent.'.';
        try{$radius=$this->radiusSettings->getConnectionConfig();if($profile&&!empty($profile['config']['radius_server'])&&(string)$profile['config']['radius_server']!==(string)$radius['host'])$warnings[]='Accel-PPP RADIUS host differs from the active Radius module host.';}catch(Throwable $e){$warnings[]='Active Radius module settings could not be compared: '.$e->getMessage();}
        if(!$profile&&$parent!==''){$defaults['pppoe_interface']='re:^'.preg_quote($parent,'/').'\\.[0-9]+\\.[0-9]+';$defaults['vlan_mon']='re:^'.preg_quote($parent,'/').'\\.[0-9]+,50-4000';}
        return ['profile'=>$profile,'deployments'=>$this->repo->deployments(),'defaults'=>$defaults,'integration_warnings'=>$warnings];
    }

    public function save(UpdateAccelPppConfigDTO $dto, int $userId): array
    {
        $config=$dto->toArray(); $existing=$this->repo->get(true);
        $errors=$this->validator->validate($config, !empty($existing['config']['radius_secret']) && !empty($existing['config']['dae_secret']));
        if($errors) throw new RuntimeException((string)reset($errors));
        if(trim((string)$config['radius_secret'])==='')$config['radius_secret']=(string)($existing['config']['radius_secret']??'');
        if(trim((string)$config['dae_secret'])==='')$config['dae_secret']=(string)($existing['config']['dae_secret']??'');
        $this->integrationValidator->assertAccel($config);
        $full=$this->renderer->render($config);$this->validator->validateRendered($full);
        $redactedConfig=$config; $redactedConfig['radius_secret']='[REDACTED]'; $redactedConfig['dae_secret']='[REDACTED]';
        try{$saved=$this->repo->save($config,$this->renderer->render($redactedConfig),hash('sha256',$full),$userId);}catch(Throwable $e){$this->audit('SAVE_ACCEL_PPP_DRAFT_FAILED','Failed to save Accel-PPP draft: '.$e->getMessage(),0);throw $e;}
        $this->audit('SAVE_ACCEL_PPP_DRAFT','Saved an Accel-PPP configuration draft. No remote configuration or session state was changed.',(int)$saved['id']);
        return $saved;
    }

    public function preview(): array
    {
        $profile=$this->repo->get(); if(!$profile)throw new RuntimeException('Accel-PPP configuration has not been saved.');
        return ['config'=>$profile['rendered_config'],'config_hash'=>$profile['config_hash'],'restart_required'=>(bool)$profile['restart_required'],'notice'=>'Secrets are redacted. Applying this draft requires a scheduled maintenance activation.'];
    }

    public function previewDraft(UpdateAccelPppConfigDTO $dto): array
    {
        $config=$dto->toArray();$errors=$this->validator->validate($config,true);
        if($errors)throw new RuntimeException((string)reset($errors));
        $this->integrationValidator->assertAccel($config);
        $config['radius_secret']='[REDACTED]';$config['dae_secret']='[REDACTED]';
        $rendered=$this->renderer->render($config);$this->validator->validateRendered($rendered);
        return ['config'=>$rendered,'preview_hash'=>hash('sha256',$rendered),'saved'=>false,'restart_required'=>false,'notice'=>'Unsaved preview only. Secrets are redacted and no BNG state was changed.'];
    }

    public function synchronizeParentInterface(string $oldParent,string $newParent,int $userId): void
    {
        if($oldParent===''||$newParent===''||$oldParent===$newParent)return;$profile=$this->repo->get(true);if(!$profile)return;$config=$profile['config'];$changed=false;
        foreach(['pppoe_interface','vlan_mon'] as $field){$value=(string)($config[$field]??'');if(str_contains($value,$oldParent)){$config[$field]=str_replace($oldParent,$newParent,$value);$changed=true;}}
        if(!$changed)return;$rendered=$this->renderer->render($config);$this->validator->validateRendered($rendered);$redacted=$config;$redacted['radius_secret']='[REDACTED]';$redacted['dae_secret']='[REDACTED]';$this->repo->save($config,$this->renderer->render($redacted),hash('sha256',$rendered),$userId);$this->audit('SYNC_ACCEL_PARENT_INTERFACE','Updated Accel-PPP interface expressions after the BNG parent changed from '.$oldParent.' to '.$newParent.'.',(int)$profile['id']);
    }

    public function stage(int $userId): array
    {
        $profile=$this->repo->get(true); if(!$profile)throw new RuntimeException('Accel-PPP configuration has not been saved.');
        $config=$profile['config']; $errors=$this->validator->validate($config,true); if($errors)throw new RuntimeException((string)reset($errors));
        $rendered=$this->renderer->render($config);$this->validator->validateRendered($rendered); if(!hash_equals((string)$profile['config_hash'],hash('sha256',$rendered)))throw new RuntimeException('Saved configuration integrity check failed.');
        $setting=$this->bng->getFullSetting();
        $command='install -d -m 0750 /var/lib/nexusbox && /usr/bin/tee /var/lib/nexusbox/accel-ppp.pending.conf >/dev/null && /bin/chmod 0600 /var/lib/nexusbox/accel-ppp.pending.conf';
        try{
            $result=$this->bng->runPrivilegedCommand($setting,$command,$rendered);if((int)$result['exit_code']!==0)throw new RuntimeException('Unable to upload the pending Accel-PPP configuration.');
            $this->stageBootReconciliation($setting);
            $verify=$this->bng->runPrivilegedCommand(
                $setting,
                'test -x /usr/sbin/accel-pppd'
                .' && test -x /var/lib/nexusbox/bng-reconcile.pending'
                .' && /bin/grep -q "^\\[modules\\]" /var/lib/nexusbox/accel-ppp.pending.conf'
                .' && /bin/grep -q "^\\[radius\\]" /var/lib/nexusbox/accel-ppp.pending.conf'
                .' && /bin/grep -q "^ExecStart=/usr/local/sbin/nexusbox-bng-reconcile$" /var/lib/nexusbox/nexusbox-bng-reconcile.pending.service'
                .' && /bin/sh -n /var/lib/nexusbox/bng-reconcile.pending'
            );
            if((int)$verify['exit_code']!==0)throw new RuntimeException('Remote staged-artifact validation failed: '.($verify['stderr']?:$verify['stdout']));
            $this->repo->begin();$this->repo->markValidated((int)$profile['id']);$this->repo->markStaged((int)$profile['id'],$userId);$this->repo->createDeployment((int)$profile['id'],'STAGE','SUCCESS',(string)$profile['config_hash'],'Configuration and boot recovery artifacts validated and staged. No daemon restart was performed.',$userId);$this->repo->commit();
        }catch(Throwable $e){$this->repo->rollback();try{$this->bng->runPrivilegedCommand($setting,'/bin/rm -f /var/lib/nexusbox/accel-ppp.pending.conf /var/lib/nexusbox/bng-reconcile.pending /var/lib/nexusbox/nexusbox-bng-reconcile.pending.service');}catch(Throwable){}$this->repo->markFailed((int)$profile['id'],$e->getMessage());$this->repo->createDeployment((int)$profile['id'],'STAGE','FAILED',(string)$profile['config_hash'],$e->getMessage(),$userId);$this->audit('STAGE_ACCEL_PPP_FAILED','Failed to stage Accel-PPP configuration: '.$e->getMessage(),(int)$profile['id']);throw $e;}
        $this->audit('STAGE_ACCEL_PPP','Staged Accel-PPP configuration for a maintenance window. No active sessions were changed.',(int)$profile['id']);
        return ['status'=>'STAGED','config_hash'=>$profile['config_hash'],'remote_path'=>'/var/lib/nexusbox/accel-ppp.pending.conf','restart_performed'=>false,'message'=>'Configuration staged. Activate it only during a scheduled maintenance window.'];
    }

    public function activateMaintenance(int $userId,string $confirmation,bool $acknowledgeDisconnect): array
    {
        if($confirmation!=='ACTIVATE DURING MAINTENANCE'||!$acknowledgeDisconnect)throw new RuntimeException('Maintenance activation requires the exact confirmation phrase and session-disconnection acknowledgement.');
        $profile=$this->repo->get(true);if(!$profile||$profile['status']!=='STAGED')throw new RuntimeException('Only a validated staged profile can be activated.');
        $setting=$this->bng->getFullSetting();$runtime=$this->bng->getRuntimeStatus();$active=count($runtime['bng_interfaces']??[]);$hash=(string)$profile['config_hash'];$backup='/var/lib/nexusbox/accel-ppp.backup.'.$hash.'.conf';$scriptBackup='/var/lib/nexusbox/bng-reconcile.backup.'.$hash;$unitBackup='/var/lib/nexusbox/nexusbox-bng-reconcile.backup.'.$hash.'.service';
        $command='set -eu; test -s /var/lib/nexusbox/accel-ppp.pending.conf; test -s /var/lib/nexusbox/bng-reconcile.pending; test -s /var/lib/nexusbox/nexusbox-bng-reconcile.pending.service; /bin/rm -f '.escapeshellarg($backup).' '.escapeshellarg($scriptBackup).' '.escapeshellarg($unitBackup).'; if test -f /etc/accel-ppp.conf; then /bin/cp -p /etc/accel-ppp.conf '.escapeshellarg($backup).'; fi; if test -f /usr/local/sbin/nexusbox-bng-reconcile; then /bin/cp -p /usr/local/sbin/nexusbox-bng-reconcile '.escapeshellarg($scriptBackup).'; fi; if test -f /etc/systemd/system/nexusbox-bng-reconcile.service; then /bin/cp -p /etc/systemd/system/nexusbox-bng-reconcile.service '.escapeshellarg($unitBackup).'; fi; /usr/bin/install -m 0600 /var/lib/nexusbox/accel-ppp.pending.conf /etc/accel-ppp.conf; /usr/bin/install -m 0750 /var/lib/nexusbox/bng-reconcile.pending /usr/local/sbin/nexusbox-bng-reconcile; /usr/bin/install -m 0644 /var/lib/nexusbox/nexusbox-bng-reconcile.pending.service /etc/systemd/system/nexusbox-bng-reconcile.service; /bin/systemctl daemon-reload; /bin/systemctl enable nexusbox-bng-reconcile.service; /bin/systemctl restart nexusbox-bng-reconcile.service; /bin/systemctl restart accel-ppp.service; /bin/systemctl is-active --quiet accel-ppp.service';
        $result=$this->bng->runPrivilegedCommand($setting,$command);
        if((int)$result['exit_code']!==0){$rollback='set +e; if test -f '.escapeshellarg($backup).'; then /usr/bin/install -m 0600 '.escapeshellarg($backup).' /etc/accel-ppp.conf; fi; if test -f '.escapeshellarg($scriptBackup).'; then /usr/bin/install -m 0750 '.escapeshellarg($scriptBackup).' /usr/local/sbin/nexusbox-bng-reconcile; else /bin/rm -f /usr/local/sbin/nexusbox-bng-reconcile; fi; if test -f '.escapeshellarg($unitBackup).'; then /usr/bin/install -m 0644 '.escapeshellarg($unitBackup).' /etc/systemd/system/nexusbox-bng-reconcile.service; else /bin/systemctl disable nexusbox-bng-reconcile.service >/dev/null 2>&1; /bin/rm -f /etc/systemd/system/nexusbox-bng-reconcile.service; fi; /bin/systemctl daemon-reload; /bin/systemctl restart accel-ppp.service; /bin/systemctl is-active --quiet accel-ppp.service';$rolled=$this->bng->runPrivilegedCommand($setting,$rollback);$message='Maintenance activation failed. Rollback '.((int)$rolled['exit_code']===0?'completed.':'also failed; manual intervention is required.');$this->repo->markFailed((int)$profile['id'],$message);$this->repo->createDeployment((int)$profile['id'],'ACTIVATE','FAILED',$hash,$message,$userId);$this->audit('ACTIVATE_ACCEL_PPP_FAILED',$message,(int)$profile['id']);throw new RuntimeException($message);}
        $this->repo->markActive((int)$profile['id'],$userId);$this->repo->createDeployment((int)$profile['id'],'ACTIVATE','SUCCESS',$hash,'Maintenance activation completed and Accel-PPP is active.',$userId);$this->audit('ACTIVATE_ACCEL_PPP','Activated staged Accel-PPP configuration during maintenance. Active PPP interfaces before restart: '.$active.'.',(int)$profile['id']);
        return ['status'=>'ACTIVE','active_sessions_before_restart'=>$active,'rollback_backup'=>$backup,'service_active'=>true];
    }

    public function installBootRecovery(int $userId, string $confirmation): array
    {
        if ($confirmation !== 'INSTALL BNG BOOT RECOVERY') throw new RuntimeException('Type the exact confirmation phrase: INSTALL BNG BOOT RECOVERY');
        $setting = $this->bng->getFullSetting();
        $this->stageBootReconciliation($setting);
        $command = 'set -eu; /usr/bin/install -m 0750 /var/lib/nexusbox/bng-reconcile.pending /usr/local/sbin/nexusbox-bng-reconcile; '
            . '/usr/bin/install -m 0644 /var/lib/nexusbox/nexusbox-bng-reconcile.pending.service /etc/systemd/system/nexusbox-bng-reconcile.service; '
            . '/bin/systemctl daemon-reload; /bin/systemctl enable nexusbox-bng-reconcile.service; '
            . '/usr/bin/systemd-analyze verify /etc/systemd/system/nexusbox-bng-reconcile.service';
        $result = $this->bng->runPrivilegedCommand($setting, $command);
        if ((int)$result['exit_code'] !== 0) {
            $message = 'Unable to install BNG boot recovery: ' . trim((string)($result['stderr'] ?: $result['stdout']));
            $this->audit('INSTALL_BOOT_RECOVERY_FAILED', $message, 0);
            throw new RuntimeException($message);
        }
        $this->audit('INSTALL_BOOT_RECOVERY', 'Installed and enabled BNG VLAN/CGNAT desired-state recovery. It was not executed and Accel-PPP was not restarted.', 0);
        return ['installed'=>true,'enabled'=>true,'executed'=>false,'restart_performed'=>false];
    }

    private function stageBootReconciliation(array $setting): void
    {
        $parent=trim((string)($setting['bng_parent_interface']??''));
        if(!preg_match('/^[A-Za-z0-9_.:-]+$/',$parent))throw new RuntimeException('BNG parent interface is invalid.');
        $script=["#!/bin/sh","set -eu","/usr/sbin/ip link show ".escapeshellarg($parent)." >/dev/null"];
        foreach($this->desiredState->interfaces() as $row){
            $iface=trim((string)($row['interface']??'')); if(!preg_match('/^[A-Za-z0-9_.:-]+$/',$iface))continue;
            $parts=explode('.',$iface);
            if(count($parts)===2){$vlan=(int)$parts[1];$script[]="/usr/sbin/ip link show ".escapeshellarg($iface)." >/dev/null 2>&1 || /usr/sbin/ip link add link ".escapeshellarg($parent)." name ".escapeshellarg($iface)." type vlan id {$vlan}";}
            elseif(count($parts)===3){$outer=$parts[0].'.'.$parts[1];$vlan=(int)$parts[2];$script[]="/usr/sbin/ip link show ".escapeshellarg($iface)." >/dev/null 2>&1 || /usr/sbin/ip link add link ".escapeshellarg($outer)." name ".escapeshellarg($iface)." type vlan id {$vlan}";}
            else continue;
            $script[]="/usr/sbin/ip link set ".escapeshellarg($iface)." up";
        }
        $cgnat=$this->desiredState->cgnat();
        if($cgnat){$inside=trim((string)$cgnat['inside_network']);$bngInterface=trim((string)($cgnat['bng_interface']??''));$start=trim((string)$cgnat['public_start_ip']);$end=trim((string)$cgnat['public_end_ip']);$egress=trim((string)$cgnat['egress_interface']);if(!preg_match('/^[A-Za-z0-9_.:-]+$/',$bngInterface)||!preg_match('/^[A-Za-z0-9_.:-]+$/',$egress)||!filter_var($start,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)||!filter_var($end,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)||!preg_match('/^[0-9.]+\/\d{1,2}$/',$inside))throw new RuntimeException('Saved CGNAT desired state is invalid.');$first=(int)sprintf('%u',ip2long($start));$last=(int)sprintf('%u',ip2long($end));if($last<$first||($last-$first)>1023)throw new RuntimeException('Saved CGNAT public range is invalid or exceeds the 1024-address recovery limit.');$script[]="/usr/sbin/ip link show ".escapeshellarg($bngInterface)." >/dev/null";$script[]="/usr/sbin/ip link show ".escapeshellarg($egress)." >/dev/null";for($ip=$first;$ip<=$last;$ip++){$address=long2ip($ip);$script[]="/usr/sbin/ip -4 address show dev ".escapeshellarg($egress)." | /bin/grep -Fq ".escapeshellarg($address.'/32')." || /usr/sbin/ip address add ".escapeshellarg($address.'/32')." dev ".escapeshellarg($egress);}$script[]="/usr/sbin/iptables -t nat -C POSTROUTING -s ".escapeshellarg($inside)." -o ".escapeshellarg($egress)." -j SNAT --to-source ".escapeshellarg($start.'-'.$end)." 2>/dev/null || /usr/sbin/iptables -t nat -A POSTROUTING -s ".escapeshellarg($inside)." -o ".escapeshellarg($egress)." -j SNAT --to-source ".escapeshellarg($start.'-'.$end);}
        $scriptText=implode("\n",$script)."\n";
        $unit="[Unit]\nDescription=NexusBox BNG desired-state reconciliation\nAfter=network-online.target\nWants=network-online.target\nBefore=accel-ppp.service\n\n[Service]\nType=oneshot\nExecStart=/usr/local/sbin/nexusbox-bng-reconcile\nRemainAfterExit=yes\n\n[Install]\nWantedBy=multi-user.target\n";
        foreach([['bng-reconcile.pending',$scriptText,0750],['nexusbox-bng-reconcile.pending.service',$unit,0644]] as [$name,$content,$mode]){
            $command='/usr/bin/tee /var/lib/nexusbox/'.escapeshellarg($name).' >/dev/null && /bin/chmod '.decoct($mode).' /var/lib/nexusbox/'.escapeshellarg($name);
            $result=$this->bng->runPrivilegedCommand($setting,$command,$content);
            if((int)$result['exit_code']!==0)throw new RuntimeException('Unable to stage BNG boot reconciliation artifacts.');
        }
    }

    private function audit(string $action,string $description,int $id): void
    {
        try{$this->audit?->logEvent(new AuditEventDTO(module:'BNG_MANAGEMENT',action:$action,description:$description,objectType:'BNG_ACCEL_PROFILE',objectId:$id));}catch(Throwable){}
    }
}
