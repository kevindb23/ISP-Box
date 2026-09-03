<?php
namespace App\Modules\RouterManagement\Services;
final class RouterConfigRenderer
{
    public function core(array $c):string
    {
        $q='"'.addcslashes($c['interface_description'],'"\\').'"';return implode("\n",[
            'set routing-options autonomous-system '.$c['autonomous_system'],
            'set routing-options static route 0.0.0.0/0 next-hop '.$c['default_next_hop'],
            'set routing-options static route 0.0.0.0/0 retain','set routing-options static route 0.0.0.0/0 no-readvertise',
            'set routing-options static route '.$c['public_prefix'].' next-hop '.$c['bng_next_hop'],
            'set protocols bgp group '.$c['bgp_group'].' type external','set protocols bgp group '.$c['bgp_group'].' local-address '.$c['local_address'],
            'set protocols bgp group '.$c['bgp_group'].' family inet unicast','set protocols bgp group '.$c['bgp_group'].' export EXPORT-DEFAULT',
            'set protocols bgp group '.$c['bgp_group'].' peer-as '.$c['peer_as'],'set protocols bgp group '.$c['bgp_group'].' neighbor '.$c['neighbor'],
            'set interfaces '.$c['interface_name'].' description '.$q,'set interfaces '.$c['interface_name'].' enable','set interfaces '.$c['interface_name'].' unit 0 family inet address '.$c['interface_address'],
        ])."\n";
    }
    public function frr(array $c):string
    {
        return implode("\n",['ip route '.$c['subscriber_network'].' Null0','router bgp '.$c['autonomous_system'],' bgp router-id '.$c['router_id'],' neighbor '.$c['neighbor'].' remote-as '.$c['remote_as'],' address-family ipv4 unicast','  network '.$c['subscriber_network'],'  neighbor '.$c['neighbor'].' soft-reconfiguration inbound','  neighbor '.$c['neighbor'].' route-map '.$c['in_route_map'].' in','  neighbor '.$c['neighbor'].' route-map '.$c['out_route_map'].' out',' exit-address-family','exit','ip prefix-list '.$c['in_prefix_list'].' seq 5 permit 0.0.0.0/0','ip prefix-list '.$c['out_prefix_list'].' seq 5 permit '.$c['subscriber_network'],'route-map '.$c['in_route_map'].' permit 10',' match ip address prefix-list '.$c['in_prefix_list'],'exit','route-map '.$c['out_route_map'].' permit 10',' match ip address prefix-list '.$c['out_prefix_list'],'exit'])."\n";
    }
}
