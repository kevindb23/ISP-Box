<?php

namespace App\Modules\CgnatManagement\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\CgnatManagement\Repositories\CgnatRepository;
use InvalidArgumentException;
use Throwable;

class CgnatService
{
    private CgnatRepository $repo;
    private BngConnectionService $bngService;
    private ?AuditService $audit;

    public function __construct(
        CgnatRepository $repo,
        BngConnectionService $bngService,
        ?AuditService $audit = null
    ) {
        $this->repo = $repo;
        $this->bngService = $bngService;
        $this->audit = $audit;
    }

    public function get(): array
    {
        $config = $this->repo->get();

        return [
            'config' => $config ?: [],
            'runtime' => [],
        ];
    }

    public function save(array $data): void
    {
        $payload = [
            'enabled' => isset($data['enabled']) ? (int)$data['enabled'] : 0,
            'inside_network' => trim((string)($data['inside_network'] ?? '')),
            'public_start_ip' => trim((string)($data['public_start_ip'] ?? '')),
            'public_end_ip' => trim((string)($data['public_end_ip'] ?? '')),
            'egress_interface' => trim((string)($data['egress_interface'] ?? '')),
            'router_next_hop' => trim((string)($data['router_next_hop'] ?? '')),
            'remarks' => trim((string)($data['remarks'] ?? '')),
        ];

        $this->repo->save($payload);

        $this->auditLog(
            'SAVE_LEGACY_CONFIG',
            sprintf(
                'Saved CGNAT config. Enabled=%s Inside=%s PublicRange=%s-%s Egress=%s NextHop=%s',
                $payload['enabled'] === 1 ? 'YES' : 'NO',
                $payload['inside_network'],
                $payload['public_start_ip'],
                $payload['public_end_ip'],
                $payload['egress_interface'],
                $payload['router_next_hop']
            )
        );
    }

    public function apply(): array
    {
        $cfg = $this->repo->get();

        if (!$cfg) {
            throw new InvalidArgumentException('CGNAT is not configured.');
        }

        if ((int)($cfg['enabled'] ?? 0) !== 1) {
            $this->auditLog(
                'APPLY_LEGACY_CONFIG_SKIPPED',
                'Skipped applying CGNAT config because CGNAT is disabled.'
            );

            return [
                'status' => 'disabled',
                'message' => 'CGNAT is disabled.',
            ];
        }

        $insideNetwork = trim((string)($cfg['inside_network'] ?? ''));
        $publicStartIp = trim((string)($cfg['public_start_ip'] ?? ''));
        $publicEndIp = trim((string)($cfg['public_end_ip'] ?? ''));
        $egressInterface = trim((string)($cfg['egress_interface'] ?? ''));

        if ($insideNetwork === '') {
            throw new InvalidArgumentException('Inside network is required.');
        }

        if ($publicStartIp === '' || $publicEndIp === '') {
            throw new InvalidArgumentException('Public start/end IP is required.');
        }

        $setting = $this->bngService->getFullSetting();
        $runtime = $this->bngService->getRuntimeStatus();

        if ($egressInterface === '') {
            $egressInterface = trim((string)($runtime['default_interface'] ?? ''));
        }

        if ($egressInterface === '') {
            throw new InvalidArgumentException('No egress interface detected.');
        }

        $publicIps = $this->expandIpRange($publicStartIp, $publicEndIp);

        if (empty($publicIps)) {
            throw new InvalidArgumentException('Invalid public IP range.');
        }

        $results = [
            'status' => 'completed',
            'inside_network' => $insideNetwork,
            'public_start_ip' => $publicStartIp,
            'public_end_ip' => $publicEndIp,
            'egress_interface' => $egressInterface,
            'ip_bindings' => [],
            'snat_rule' => [
                'status' => 'UNKNOWN',
                'rule' => $this->buildSnatRuleString($insideNetwork, $egressInterface, $publicStartIp, $publicEndIp),
            ],
        ];

        foreach ($publicIps as $ip) {
            $exists = $this->ipExistsOnInterface($setting, $egressInterface, $ip);

            if ($exists) {
                $results['ip_bindings'][] = [
                    'ip' => $ip,
                    'status' => 'EXISTS',
                ];
                continue;
            }

            $addResult = $this->bngService->runRemoteCommand(
                $setting,
                $this->privileged($setting, "/usr/sbin/ip addr add {$ip}/32 dev {$egressInterface}")
            );

            $results['ip_bindings'][] = [
                'ip' => $ip,
                'status' => $addResult['exit_code'] === 0 ? 'ADDED' : 'FAILED',
                'stderr' => $addResult['stderr'],
            ];
        }

        $currentNatRules = $this->fetchNatRules($setting);
        $snatExists = $this->snatRuleExists(
            $currentNatRules,
            $insideNetwork,
            $egressInterface,
            $publicStartIp,
            $publicEndIp
        );

        if ($snatExists) {
            $results['snat_rule']['status'] = 'EXISTS';
        } else {
            $command = $this->privileged(
                $setting,
                "/usr/sbin/iptables -t nat -A POSTROUTING -s {$insideNetwork} -o {$egressInterface} -j SNAT --to-source {$publicStartIp}-{$publicEndIp}"
            );

            $addRuleResult = $this->bngService->runRemoteCommand($setting, $command);

            $results['snat_rule']['status'] = $addRuleResult['exit_code'] === 0 ? 'ADDED' : 'FAILED';
            $results['snat_rule']['stderr'] = $addRuleResult['stderr'];
        }

        $results['verify'] = $this->verifyState(
            $setting,
            $insideNetwork,
            $egressInterface,
            $publicStartIp,
            $publicEndIp,
            $publicIps
        );

        $failedIps = array_values(array_filter($results['ip_bindings'], static function (array $row): bool {
            return ($row['status'] ?? '') === 'FAILED';
        }));

        $this->auditLog(
            'APPLY_LEGACY_CONFIG',
            sprintf(
                'Applied CGNAT config. Inside=%s PublicRange=%s-%s Egress=%s SNAT=%s BoundIPs=%d FailedIPs=%d',
                $insideNetwork,
                $publicStartIp,
                $publicEndIp,
                $egressInterface,
                $results['snat_rule']['status'] ?? 'UNKNOWN',
                count($results['ip_bindings']),
                count($failedIps)
            )
        );

        return $results;
    }

    private function verifyState(
        array $setting,
        string $insideNetwork,
        string $egressInterface,
        string $publicStartIp,
        string $publicEndIp,
        array $publicIps
    ): array {
        $natRules = $this->fetchNatRules($setting);

        $bindingCheck = [];
        foreach ($publicIps as $ip) {
            $bindingCheck[] = [
                'ip' => $ip,
                'present' => $this->ipExistsOnInterface($setting, $egressInterface, $ip),
            ];
        }

        return [
            'snat_present' => $this->snatRuleExists(
                $natRules,
                $insideNetwork,
                $egressInterface,
                $publicStartIp,
                $publicEndIp
            ),
            'bound_ips' => $bindingCheck,
            'nat_rules' => $natRules,
        ];
    }

    private function fetchNatRules(array $setting): array
    {
        $result = $this->bngService->runRemoteCommand(
            $setting,
            $this->privileged($setting, '/usr/sbin/iptables -t nat -S POSTROUTING')
        );

        if ($result['exit_code'] !== 0) {
            return [];
        }

        return $this->splitLines($result['stdout']);
    }

    private function ipExistsOnInterface(array $setting, string $interface, string $ip): bool
    {
        $result = $this->bngService->runRemoteCommand(
            $setting,
            $this->privileged($setting, "/usr/sbin/ip addr show dev {$interface}")
        );

        if ($result['exit_code'] !== 0) {
            return false;
        }

        foreach ($this->splitLines($result['stdout']) as $line) {
            if (strpos($line, $ip . '/32') !== false || strpos($line, $ip . ' ') !== false) {
                return true;
            }
        }

        return false;
    }

    private function snatRuleExists(
        array $natRules,
        string $insideNetwork,
        string $egressInterface,
        string $publicStartIp,
        string $publicEndIp
    ): bool {
        $needleSource = "-s {$insideNetwork}";
        $needleIface = "-o {$egressInterface}";
        $needleRange = "--to-source {$publicStartIp}-{$publicEndIp}";

        foreach ($natRules as $rule) {
            if (
                strpos($rule, $needleSource) !== false &&
                strpos($rule, $needleIface) !== false &&
                strpos($rule, $needleRange) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function buildSnatRuleString(
        string $insideNetwork,
        string $egressInterface,
        string $publicStartIp,
        string $publicEndIp
    ): string {
        return "-A POSTROUTING -s {$insideNetwork} -o {$egressInterface} -j SNAT --to-source {$publicStartIp}-{$publicEndIp}";
    }

    private function privileged(array $setting, string $command): string
    {
        $username = trim((string)($setting['username'] ?? ''));

        if ($username === '' || strtolower($username) === 'root') {
            return $command;
        }

        return 'sudo -n ' . $command;
    }

    private function expandIpRange(string $start, string $end): array
    {
        $startLong = ip2long($start);
        $endLong = ip2long($end);

        if ($startLong === false || $endLong === false || $startLong > $endLong) {
            return [];
        }

        $ips = [];
        for ($i = $startLong; $i <= $endLong; $i++) {
            $ips[] = long2ip($i);
        }

        return $ips;
    }

    private function splitLines(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($text)) ?: [],
            static fn(string $line) => trim($line) !== ''
        ));
    }

    private function auditLog(string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log(
                'CGNAT',
                $action,
                $description
            );
        } catch (Throwable $e) {
        }
    }
}