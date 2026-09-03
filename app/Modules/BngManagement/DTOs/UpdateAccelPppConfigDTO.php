<?php

namespace App\Modules\BngManagement\DTOs;

final class UpdateAccelPppConfigDTO
{
    public function __construct(private array $input)
    {
    }

    public function toArray(): array
    {
        $defaults = [
            'modules' => ['radius','log_file','pppoe','auth_pap','ippool','shaper'],
            'thread_count' => 2, 'log_error' => '/var/log/accel-ppp/error.log',
            'log_debug' => '/var/log/accel-ppp/debug.log', 'log_file' => '/var/log/accel-ppp/accel.log',
            'log_level' => 5, 'pppoe_verbose' => 1,
            'pppoe_interface' => 're:^ens17\\.[0-9]+\\.[0-9]+',
            'vlan_mon' => 're:^ens17\\.[0-9]+,50-4000', 'vlan_name' => '%I.%N',
            // NexusBox persists configured QinQ interfaces itself. A zero timeout
            // prevents Accel-PPP from deleting them on hosts without vlan_mon.ko.
            'vlan_timeout' => 0, 'ifname' => '1WAN%d', 'ac_name' => 'ISP-IN-A-BOX',
            'service_name' => '', 'accept_any_service' => 1, 'pado_delay' => 0,
            'max_sessions' => 5000, 'ip_pool' => 'pool1', 'default_auth' => 'radius',
            'ipv4' => 'require', 'mtu' => 1492, 'mru' => 1492, 'min_mtu' => 1280,
            'lcp_echo_interval' => 15, 'lcp_echo_failure' => 5, 'ipcp' => 1,
            'ipcp_accept_local' => 1, 'ipcp_accept_remote' => 1, 'ppp_verbose' => 1,
            'pool_gateway' => '100.64.0.1', 'pool_range' => '100.64.0.2-100.64.0.254',
            'pool_name' => 'pool1', 'nas_ip_address' => '10.0.10.147',
            'nas_identifier' => 'ISPinAbox', 'radius_gateway' => '100.64.0.1',
            'radius_server' => '10.0.10.154', 'radius_auth_port' => 1812,
            'radius_acct_port' => 1813, 'radius_timeout' => 3, 'radius_max_try' => 3,
            'acct_interim_interval' => 60, 'radius_verbose' => 1,
            'dae_address' => '10.0.10.147', 'dae_port' => 3799,
            'cli_address' => '127.0.0.1', 'cli_port' => 2001,
            'shaper_attr' => 'Filter-Id', 'down_limiter' => 'htb', 'up_limiter' => 'htb',
            'leaf_qdisc' => 'fq_codel', 'radius_secret' => '', 'dae_secret' => '',
        ];
        $data = array_replace($defaults, array_intersect_key($this->input, $defaults));
        $modules = $this->input['modules'] ?? $defaults['modules'];
        if (is_string($modules)) $modules = preg_split('/\s*,\s*/', trim($modules)) ?: [];
        $data['modules'] = is_array($modules) ? array_values(array_filter(array_map('trim', $modules))) : $defaults['modules'];
        return $data;
    }
}
