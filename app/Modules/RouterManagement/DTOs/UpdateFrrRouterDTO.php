<?php
namespace App\Modules\RouterManagement\DTOs;
final class UpdateFrrRouterDTO
{
    public function __construct(private array $input){}
    public function toArray(): array
    {
        $fields=['autonomous_system','router_id','neighbor','remote_as','subscriber_network','in_prefix_list','out_prefix_list','in_route_map','out_route_map'];$out=['enabled'=>(int)($this->input['enabled']??1)];
        foreach($fields as $field)$out[$field]=trim((string)($this->input[$field]??''));
        return $out;
    }
}
