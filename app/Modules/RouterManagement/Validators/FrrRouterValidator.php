<?php
namespace App\Modules\RouterManagement\Validators;
final class FrrRouterValidator
{
    public function validate(array $d): array
    {
        $e=[];foreach(['router_id','neighbor'] as $f)if(!filter_var($d[$f]??'',FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))$e[]=$f.' must be an IPv4 address.';if(!preg_match('/^([^\/]+)\/(\d{1,2})$/',(string)($d['subscriber_network']??''),$m)||filter_var($m[1]??'',FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false||(int)($m[2]??99)>32)$e[]='Subscriber network must be an IPv4 CIDR.';foreach(['autonomous_system','remote_as'] as $f)if((int)($d[$f]??0)<1||(int)$d[$f]>4294967295)$e[]=$f.' is invalid.';foreach(['in_prefix_list','out_prefix_list','in_route_map','out_route_map'] as $f)if(!preg_match('/^[A-Za-z0-9_.-]{1,64}$/',(string)($d[$f]??'')))$e[]=$f.' contains invalid characters.';return $e;
    }
}
