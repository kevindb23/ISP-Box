<?php

namespace App\Modules\BngManagement\Validators;

final class AccelPppConfigValidator
{
    public function validate(array $data, bool $hasStoredSecrets = false): array
    {
        $errors = [];
        $allowedModules = ['radius','log_file','pppoe','auth_pap','auth_chap_md5','auth_mschap_v1','auth_mschap_v2','ippool','shaper','cli'];
        foreach (($data['modules'] ?? []) as $module) if (!in_array($module, $allowedModules, true)) $errors['modules'] = 'Unsupported Accel-PPP module: ' . $module;
        foreach (['radius','log_file','pppoe','auth_pap','ippool'] as $required) if (!in_array($required, $data['modules'] ?? [], true)) $errors['modules'] = "Required module {$required} is missing.";
        $radiusPosition=array_search('radius',$data['modules']??[],true); $poolPosition=array_search('ippool',$data['modules']??[],true);
        if($radiusPosition!==false&&$poolPosition!==false&&$radiusPosition>$poolPosition)$errors['modules']='The radius module must precede ippool so RADIUS Framed-IP-Address values retain priority.';
        foreach (['nas_ip_address','radius_gateway','radius_server','dae_address','pool_gateway'] as $field) if (!filter_var($data[$field] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $errors[$field] = 'A valid IPv4 address is required.';
        foreach (['thread_count'=>[1,128],'log_level'=>[0,9],'vlan_timeout'=>[0,86400],'mtu'=>[1280,2000],'mru'=>[1280,2000],'min_mtu'=>[576,2000],'max_sessions'=>[1,1000000],'radius_auth_port'=>[1,65535],'radius_acct_port'=>[1,65535],'dae_port'=>[1,65535],'cli_port'=>[1,65535]] as $field=>$range) { $value=(int)($data[$field]??0); if($value<$range[0]||$value>$range[1])$errors[$field]='Value is outside the supported range.'; }
        foreach (['log_error','log_debug','log_file'] as $field) if (!preg_match('#^/[A-Za-z0-9_./-]+$#', (string)($data[$field] ?? ''))) $errors[$field] = 'An absolute safe path is required.';
        foreach (['pppoe_interface','vlan_mon','ifname','ac_name','pool_range','pool_name','nas_identifier','shaper_attr','down_limiter','up_limiter','leaf_qdisc'] as $field) if (str_contains((string)($data[$field]??''), "\n") || trim((string)($data[$field]??''))==='') $errors[$field] = 'A single-line value is required.';
        if (!$hasStoredSecrets && trim((string)($data['radius_secret'] ?? '')) === '') $errors['radius_secret'] = 'RADIUS secret is required.';
        if (!$hasStoredSecrets && trim((string)($data['dae_secret'] ?? '')) === '') $errors['dae_secret'] = 'DAE secret is required.';
        foreach(['pppoe_verbose','accept_any_service','ipcp','ipcp_accept_local','ipcp_accept_remote','ppp_verbose','radius_verbose'] as $field)if(!in_array((int)($data[$field]??-1),[0,1],true))$errors[$field]='Value must be 0 or 1.';
        if(!in_array((string)($data['ipv4']??''),['deny','allow','prefer','require'],true))$errors['ipv4']='Invalid IPv4 negotiation policy.';
        return $errors;
    }

    public function validateRendered(string $config): void
    {
        if(str_contains($config,"\0")||!str_ends_with($config,"\n"))throw new \RuntimeException('Generated configuration contains invalid control data.');
        foreach(['modules','core','log','pppoe','auth','ppp','ip-pool','radius','cli','shaper'] as $section){if(substr_count($config,"[{$section}]")!==1)throw new \RuntimeException("Generated configuration must contain exactly one [{$section}] section.");}
        if(!preg_match('/^server=[^,]+,[^,]+,auth-port=\d+,acct-port=\d+$/m',$config))throw new \RuntimeException('Generated RADIUS server directive is invalid.');
    }
}
