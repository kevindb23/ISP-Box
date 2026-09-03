<?php
namespace App\Modules\RouterManagement\Validators;
final class CoreRouterValidator
{
    public function validate(array $d,bool $hasPassword=false): array
    {
        $e=[];if(trim((string)$d['host'])===''||!preg_match('/^[A-Za-z0-9_.:-]{1,255}$/',(string)$d['host']))$e[]='Core Router management host is required and must be valid.';if((int)$d['port']<1||(int)$d['port']>65535)$e[]='SSH port is invalid.';if(!preg_match('/^[A-Za-z0-9_.-]{1,100}$/',(string)$d['username']))$e[]='Username is invalid.';if(!$hasPassword&&trim((string)$d['password'])==='')$e[]='Password is required.';
        foreach(['default_next_hop','bng_next_hop','local_address','neighbor'] as $f)if(!filter_var($d[$f]??'',FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))$e[]=$f.' must be an IPv4 address.';
        foreach(['public_prefix','interface_address'] as $f)if(!$this->cidr((string)($d[$f]??'')))$e[]=$f.' must be an IPv4 CIDR.';
        foreach(['autonomous_system','peer_as'] as $f)if((int)($d[$f]??0)<1||(int)$d[$f]>4294967295)$e[]=$f.' is invalid.';
        foreach(['bgp_group','interface_name'] as $f)if(!preg_match('/^[A-Za-z0-9_.\/-]{1,64}$/',(string)($d[$f]??'')))$e[]=$f.' contains invalid characters.';
        if(!preg_match('/^[A-Za-z0-9 _.,:\/-]{1,120}$/',(string)($d['interface_description']??'')))$e[]='Interface description contains invalid characters.';return $e;
    }
    private function cidr(string $v):bool{return preg_match('/^([^\/]+)\/(\d{1,2})$/',$v,$m)&&filter_var($m[1],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false&&(int)$m[2]<=32;}
}
