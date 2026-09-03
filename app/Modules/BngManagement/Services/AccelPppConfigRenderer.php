<?php

namespace App\Modules\BngManagement\Services;

final class AccelPppConfigRenderer
{
    public function render(array $c): string
    {
        $line = static fn(string $key, mixed $value): string => $key . '=' . str_replace(["\r","\n"], '', (string)$value);
        $bool = static fn(mixed $value): int => (int)((int)$value === 1);
        $sections = [];
        $sections[] = "[modules]\n" . implode("\n", array_map('strval', $c['modules']));
        $sections[] = "[core]\n".$line('thread-count',(int)$c['thread_count'])."\n".$line('log-error',$c['log_error'])."\n".$line('log-debug',$c['log_debug']);
        $sections[] = "[log]\n".$line('log-file',$c['log_file'])."\n".$line('log-level',(int)$c['log_level']);
        $sections[] = "[pppoe]\n".implode("\n", [
            $line('verbose',$bool($c['pppoe_verbose'])),$line('interface',$c['pppoe_interface']),$line('vlan-mon',$c['vlan_mon']),$line('vlan-name',$c['vlan_name']),$line('vlan-timeout',(int)$c['vlan_timeout']),$line('ifname',$c['ifname']),$line('ac-name',$c['ac_name']),$line('service-name',$c['service_name']),$line('accept-any-service',$bool($c['accept_any_service'])),$line('pado-delay',(int)$c['pado_delay']),$line('max-sessions',(int)$c['max_sessions']),$line('ip-pool',$c['ip_pool'])]);
        $sections[] = "[auth]\n".$line('default-auth',$c['default_auth']);
        $sections[] = "[ppp]\n".implode("\n", [$line('ipv4',$c['ipv4']),$line('mtu',(int)$c['mtu']),$line('mru',(int)$c['mru']),$line('min-mtu',(int)$c['min_mtu']),$line('lcp-echo-interval',(int)$c['lcp_echo_interval']),$line('lcp-echo-failure',(int)$c['lcp_echo_failure']),$line('ipcp',$bool($c['ipcp'])),$line('ipcp-accept-local',$bool($c['ipcp_accept_local'])),$line('ipcp-accept-remote',$bool($c['ipcp_accept_remote'])),$line('verbose',$bool($c['ppp_verbose']))]);
        $sections[] = "[ip-pool]\n".$line('gw-ip-address',$c['pool_gateway'])."\n".$c['pool_range'].','.$c['pool_name'];
        $sections[] = "[radius]\n".implode("\n", [$line('nas-ip-address',$c['nas_ip_address']),$line('nas-identifier',$c['nas_identifier']),$line('gw-ip-address',$c['radius_gateway']),$line('server',$c['radius_server'].','.$c['radius_secret'].',auth-port='.(int)$c['radius_auth_port'].',acct-port='.(int)$c['radius_acct_port']),$line('timeout',(int)$c['radius_timeout']),$line('max-try',(int)$c['radius_max_try']),$line('acct-interim-interval',(int)$c['acct_interim_interval']),$line('verbose',$bool($c['radius_verbose'])),$line('dae-server',$c['dae_address'].':'.(int)$c['dae_port'].','.$c['dae_secret'])]);
        $sections[] = "[cli]\n".$line('telnet',$c['cli_address'].':'.(int)$c['cli_port']);
        $sections[] = "[shaper]\n".implode("\n", [$line('attr',$c['shaper_attr']),$line('down-limiter',$c['down_limiter']),$line('up-limiter',$c['up_limiter']),$line('leaf-qdisc',$c['leaf_qdisc'])]);
        return implode("\n\n", $sections) . "\n";
    }
}
