<?php
namespace App\Modules\RouterManagement\DTOs;
final class UpdateCoreRouterDTO
{
    public function __construct(private array $input){}
    public function toArray(): array
    {
        $fields=['host','username','password','autonomous_system','default_next_hop','public_prefix','bng_next_hop','bgp_group','local_address','peer_as','neighbor','interface_name','interface_description','interface_address'];
        $out=['enabled'=>(int)($this->input['enabled']??1),'port'=>(int)($this->input['port']??22)];
        foreach($fields as $field)$out[$field]=trim((string)($this->input[$field]??''));
        return $out;
    }
}
