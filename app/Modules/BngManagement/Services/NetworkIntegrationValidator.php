<?php

namespace App\Modules\BngManagement\Services;

use InvalidArgumentException;
use App\Modules\BngManagement\Repositories\NetworkIntegrationRepository;

final class NetworkIntegrationValidator
{
    public function __construct(private NetworkIntegrationRepository $repo) {}

    public function assertCore(array $core): void
    {
        $frr = $this->repo->frr();
        if ($frr) $this->assertCoreFrr($core, $frr);
        $cgnat = $this->repo->cgnat();
        if ($cgnat && (int)($cgnat['enabled'] ?? 0) === 1) $this->assertPublicRange($core, $cgnat);
    }

    public function assertFrr(array $frr): void
    {
        $core = $this->repo->core();
        if ($core) $this->assertCoreFrr($core, $frr);
        $cgnat = $this->repo->cgnat();
        if ($cgnat && (int)($cgnat['enabled'] ?? 0) === 1
            && (string)$cgnat['inside_network'] !== (string)$frr['subscriber_network']) {
            throw new InvalidArgumentException('FRR subscriber network must match the enabled CGNAT inside network.');
        }
        $accel = $this->repo->accel();
        if ($accel) $this->assertAccelRangeInNetwork($accel, (string)$frr['subscriber_network']);
    }

    public function assertCgnat(array $cgnat): void
    {
        if ((int)($cgnat['enabled'] ?? 0) !== 1) return;
        $frr = $this->repo->frr();
        if ($frr && (string)$cgnat['inside_network'] !== (string)$frr['subscriber_network']) {
            throw new InvalidArgumentException('CGNAT inside network must match the FRR subscriber network.');
        }
        $core = $this->repo->core();
        if ($core) $this->assertPublicRange($core, $cgnat);
    }

    public function assertAccel(array $accel): void
    {
        $frr = $this->repo->frr();
        if ($frr) $this->assertAccelRangeInNetwork($accel, (string)$frr['subscriber_network']);
        $cgnat = $this->repo->cgnat();
        if ($cgnat && (int)($cgnat['enabled'] ?? 0) === 1) {
            $this->assertAccelRangeInNetwork($accel, (string)$cgnat['inside_network']);
        }
    }

    private function assertCoreFrr(array $core, array $frr): void
    {
        $checks = [
            [(string)$core['autonomous_system'], (string)$frr['remote_as'], 'Core local AS must match FRR remote AS.'],
            [(string)$core['peer_as'], (string)$frr['autonomous_system'], 'Core peer AS must match FRR local AS.'],
            [(string)$core['local_address'], (string)$frr['neighbor'], 'Core local BGP address must match the FRR neighbor.'],
            [(string)$core['neighbor'], (string)$frr['router_id'], 'Core BGP neighbor must match the FRR router ID.'],
        ];
        foreach ($checks as [$left, $right, $message]) if ($left !== $right) throw new InvalidArgumentException($message);
    }

    private function assertPublicRange(array $core, array $cgnat): void
    {
        foreach (['public_start_ip', 'public_end_ip'] as $field) {
            if (!$this->ipInCidr((string)$cgnat[$field], (string)$core['public_prefix'])) {
                throw new InvalidArgumentException('CGNAT public IP range must be contained by the Core Router public prefix.');
            }
        }
    }

    private function assertAccelRangeInNetwork(array $accel, string $network): void
    {
        $range = explode('-', (string)($accel['pool_range'] ?? ''), 2);
        if (count($range) !== 2 || !$this->ipInCidr($range[0], $network) || !$this->ipInCidr($range[1], $network)
            || !$this->ipInCidr((string)($accel['pool_gateway'] ?? ''), $network)) {
            throw new InvalidArgumentException('Accel-PPP gateway and pool range must be contained by the FRR/CGNAT subscriber network.');
        }
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !preg_match('/^([^\/]+)\/(\d{1,2})$/', $cidr, $m)) return false;
        $prefix = (int)$m[2];
        if ($prefix < 0 || $prefix > 32 || !filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
        $mask = $prefix === 0 ? 0 : ((0xffffffff << (32 - $prefix)) & 0xffffffff);
        return (((int)sprintf('%u', ip2long($ip))) & $mask) === (((int)sprintf('%u', ip2long($m[1]))) & $mask);
    }
}
