<?php

namespace App\Modules\CgnatManagement\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\BngManagement\Services\BngConnectionService;
use App\Modules\BngManagement\Services\NetworkIntegrationValidator;
use App\Modules\CgnatManagement\Repositories\CgnatRepository;
use App\Modules\CgnatManagement\Entities\CgnatSetting;
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
        private NetworkIntegrationValidator $integrationValidator,
        ?AuditService $audit = null
    ) {
        $this->repo = $repo;
        $this->bngService = $bngService;
        $this->audit = $audit;
    }

    public function get(): array
    {
        $config = $this->repo->get();

        $runtime = ['interfaces' => [], 'nat_rules' => [], 'warning' => null];
        try {
            $bngRuntime = $this->bngService->getRuntimeStatus();
            $debug = $bngRuntime['debug'] ?? [];
            if ((int)($debug['links_exit_code'] ?? 1) !== 0 || (int)($debug['nat_exit_code'] ?? 1) !== 0) {
                throw new InvalidArgumentException('Unable to read physical interfaces or live POSTROUTING rules from the BNG server.');
            }
            // CGNAT must bind to real server interfaces, never VLAN or Accel-PPP
            // session interfaces. BNG runtime already classifies these for us.
            $runtime['interfaces'] = array_values(array_unique(array_filter(array_map(
                static fn(array $row): string => trim((string)($row['interface'] ?? '')),
                $bngRuntime['parent_interfaces'] ?? []
            ))));
            $natRules = array_values(array_filter(
                array_map('strval', $bngRuntime['iptables'] ?? []),
                static fn(string $rule): bool => str_contains($rule, 'POSTROUTING')
            ));
            $runtime['nat_rules'] = array_map(static fn(string $rule): array => [
                'rule' => $rule,
                'hash' => hash('sha256', $rule),
                'removable' => str_starts_with($rule, '-A POSTROUTING '),
            ], $natRules);
        } catch (Throwable $e) {
            $runtime['warning'] = $e->getMessage();
        }

        return [
            'config' => $config ? (new CgnatSetting($config))->toArray() : [],
            'runtime' => $runtime,
        ];
    }

    public function save(array $data): void
    {
        $payload = [
            'enabled' => isset($data['enabled']) ? (int)$data['enabled'] : 0,
            'inside_network' => trim((string)($data['inside_network'] ?? '')),
            'bng_interface' => trim((string)($data['bng_interface'] ?? '')),
            'public_start_ip' => trim((string)($data['public_start_ip'] ?? '')),
            'public_end_ip' => trim((string)($data['public_end_ip'] ?? '')),
            'egress_interface' => trim((string)($data['egress_interface'] ?? '')),
        ];

        $this->integrationValidator->assertCgnat($payload);
        $this->repo->save($payload);

        $this->auditLog(
            'SAVE_CONFIG',
            sprintf(
                'Saved CGNAT config. Enabled=%s Inside=%s BNGInterface=%s PublicRange=%s-%s Egress=%s',
                $payload['enabled'] === 1 ? 'YES' : 'NO',
                $payload['inside_network'],
                $payload['bng_interface'],
                $payload['public_start_ip'],
                $payload['public_end_ip'],
                $payload['egress_interface']
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
            $setting = $this->bngService->getFullSetting();
            $previous = $this->repo->appliedState();
            if ($previous) $this->removeEntireAppliedState($setting, $previous);
            $this->repo->markApplied([]);
            $this->auditLog(
                'DISABLE_CONFIG_APPLIED',
                'Removed the previously managed CGNAT state because CGNAT is disabled.'
            );

            return [
                'status' => 'disabled',
                'message' => 'CGNAT is disabled and previously managed state was removed.',
            ];
        }

        $insideNetwork = trim((string)($cfg['inside_network'] ?? ''));
        $bngInterface = trim((string)($cfg['bng_interface'] ?? ''));
        $publicStartIp = trim((string)($cfg['public_start_ip'] ?? ''));
        $publicEndIp = trim((string)($cfg['public_end_ip'] ?? ''));
        $egressInterface = trim((string)($cfg['egress_interface'] ?? ''));

        if ($insideNetwork === '') {
            throw new InvalidArgumentException('Inside network is required.');
        }

        if ($publicStartIp === '' || $publicEndIp === '') {
            throw new InvalidArgumentException('Public start/end IP is required.');
        }

        $this->assertInterfaceName($bngInterface, 'BNG interface');
        $this->assertInterfaceName($egressInterface, 'Egress interface');

        $setting = $this->bngService->getFullSetting();


        if (!$this->interfaceExists($setting, $bngInterface)) {
            throw new InvalidArgumentException('Configured BNG interface does not exist on the server: ' . $bngInterface);
        }
        if (!$this->interfaceExists($setting, $egressInterface)) {
            throw new InvalidArgumentException('Configured egress interface does not exist on the server: ' . $egressInterface);
        }

        $publicIps = $this->expandIpRange($publicStartIp, $publicEndIp);

        if (empty($publicIps)) {
            throw new InvalidArgumentException('Invalid public IP range.');
        }

        $previousApplied = $this->repo->appliedState();
        $results = [
            'status' => 'completed',
            'inside_network' => $insideNetwork,
            'bng_interface' => $bngInterface,
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

            $addResult = $this->bngService->runPrivilegedCommand(
                $setting,
                '/usr/sbin/ip addr add ' . escapeshellarg($ip . '/32') . ' dev ' . escapeshellarg($egressInterface)
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
            $command = '/usr/sbin/iptables -t nat -A POSTROUTING -s ' . escapeshellarg($insideNetwork)
                . ' -o ' . escapeshellarg($egressInterface) . ' -j SNAT --to-source '
                . escapeshellarg($publicStartIp . '-' . $publicEndIp);

            $addRuleResult = $this->bngService->runPrivilegedCommand($setting, $command);

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

        $missingIps = array_values(array_filter(
            $results['verify']['bound_ips'] ?? [],
            static fn(array $row): bool => empty($row['present'])
        ));
        $snatFailed = ($results['snat_rule']['status'] ?? '') === 'FAILED'
            || empty($results['verify']['snat_present']);

        if ($failedIps || $missingIps || $snatFailed) {
            $this->rollbackNewState($setting, $insideNetwork, $egressInterface, $publicStartIp, $publicEndIp, $results);
            $message = sprintf(
                'CGNAT apply verification failed. FailedIPs=%d MissingIPs=%d SNAT=%s',
                count($failedIps),
                count($missingIps),
                $results['snat_rule']['status'] ?? 'UNKNOWN'
            );
            $this->auditLog('APPLY_CONFIG_FAILED', $message);
            throw new InvalidArgumentException($message);
        }

        $newState = [
            'inside_network' => $insideNetwork,
            'bng_interface' => $bngInterface,
            'public_start_ip' => $publicStartIp,
            'public_end_ip' => $publicEndIp,
            'egress_interface' => $egressInterface,
        ];
        $this->removeStaleAppliedState($setting, $previousApplied, $newState, $publicIps);
        $this->repo->markApplied($newState);

        $this->auditLog(
            'APPLY_CONFIG',
            sprintf(
                'Applied CGNAT config. Inside=%s BNGInterface=%s PublicRange=%s-%s Egress=%s SNAT=%s BoundIPs=%d FailedIPs=%d',
                $insideNetwork,
                $bngInterface,
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

    public function removePostroutingRules(array $ruleHashes): array
    {
        $setting=$this->bngService->getFullSetting();
        $current=$this->fetchNatRules($setting);
        $available=[];
        foreach($current as $rule)if(str_starts_with($rule,'-A POSTROUTING '))$available[hash('sha256',$rule)]=$rule;
        $results=[];
        foreach($ruleHashes as $hash){
            $rule=$available[(string)$hash]??null;
            if($rule===null)throw new InvalidArgumentException('A selected POSTROUTING rule no longer exists. Refresh and select it again.');
            $tokens=array_values(array_filter(str_getcsv($rule,' ','"','\\'),static fn($value):bool=>$value!==''));
            if(($tokens[0]??'')!=='-A'||($tokens[1]??'')!=='POSTROUTING')throw new InvalidArgumentException('Only POSTROUTING append rules can be removed.');
            $tokens[0]='-D';
            $command='/usr/sbin/iptables -t nat '.implode(' ',array_map('escapeshellarg',$tokens));
            $result=$this->bngService->runPrivilegedCommand($setting,$command);
            $ok=(int)$result['exit_code']===0;
            $results[]=['rule'=>$rule,'status'=>$ok?'REMOVED':'FAILED','stderr'=>$result['stderr']??''];
            $this->auditLog($ok?'REMOVE_POSTROUTING_RULE':'REMOVE_POSTROUTING_RULE_FAILED',sprintf('%s POSTROUTING rule: %s',$ok?'Removed':'Failed to remove',$rule));
            if(!$ok)throw new InvalidArgumentException('Failed to remove POSTROUTING rule: '.trim((string)($result['stderr']??'')));
        }
        $remaining=$this->fetchNatRules($setting);
        foreach($results as &$row)$row['verified_absent']=!in_array($row['rule'],$remaining,true);
        unset($row);
        if(array_filter($results,static fn(array $row):bool=>!$row['verified_absent']))throw new InvalidArgumentException('A POSTROUTING rule remained after deletion. It may have been duplicated; refresh and remove the remaining entry.');
        return ['removed'=>$results,'remaining_rules'=>$remaining,'desired_state_warning'=>'An enabled saved CGNAT SNAT rule can be restored during BNG reconciliation. Disable or update the saved CGNAT configuration when removal should be permanent.'];
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
        $result = $this->bngService->runPrivilegedCommand($setting, '/usr/sbin/iptables -t nat -S POSTROUTING');

        if ($result['exit_code'] !== 0) {
            throw new InvalidArgumentException('Unable to read live POSTROUTING rules: ' . trim((string)($result['stderr'] ?? '')));
        }

        return $this->splitLines($result['stdout']);
    }

    private function ipExistsOnInterface(array $setting, string $interface, string $ip): bool
    {
        $result = $this->bngService->runPrivilegedCommand(
            $setting,
            '/usr/sbin/ip addr show dev ' . escapeshellarg($interface)
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

    private function rollbackNewState(array $setting, string $inside, string $egress, string $start, string $end, array $results): void
    {
        if (($results['snat_rule']['status'] ?? '') === 'ADDED') {
            $this->runBestEffort($setting, '/usr/sbin/iptables -t nat -D POSTROUTING -s '
                . escapeshellarg($inside) . ' -o ' . escapeshellarg($egress)
                . ' -j SNAT --to-source ' . escapeshellarg($start . '-' . $end));
        }
        foreach ($results['ip_bindings'] ?? [] as $row) {
            if (($row['status'] ?? '') === 'ADDED') {
                $this->runBestEffort($setting, '/usr/sbin/ip addr del '
                    . escapeshellarg((string)$row['ip'] . '/32') . ' dev ' . escapeshellarg($egress));
            }
        }
    }

    private function removeStaleAppliedState(array $setting, ?array $old, array $new, array $newIps): void
    {
        if (!$old) return;
        $oldStart = (string)($old['public_start_ip'] ?? '');
        $oldEnd = (string)($old['public_end_ip'] ?? '');
        $oldInside = (string)($old['inside_network'] ?? '');
        $oldEgress = (string)($old['egress_interface'] ?? '');
        $sameRule = $oldStart === $new['public_start_ip'] && $oldEnd === $new['public_end_ip']
            && $oldInside === $new['inside_network'] && $oldEgress === $new['egress_interface'];
        if (!$sameRule && $oldInside !== '' && $oldEgress !== '' && $oldStart !== '' && $oldEnd !== ''
            && $this->snatRuleExists($this->fetchNatRules($setting), $oldInside, $oldEgress, $oldStart, $oldEnd)) {
            $this->runRequired($setting, '/usr/sbin/iptables -t nat -D POSTROUTING -s '
                . escapeshellarg($oldInside) . ' -o ' . escapeshellarg($oldEgress)
                . ' -j SNAT --to-source ' . escapeshellarg($oldStart . '-' . $oldEnd), 'remove the previously managed SNAT rule');
        }
        foreach ($this->expandIpRange($oldStart, $oldEnd) as $ip) {
            if ($oldEgress !== '' && ($oldEgress !== $new['egress_interface'] || !in_array($ip, $newIps, true))
                && $this->ipExistsOnInterface($setting, $oldEgress, $ip)) {
                $this->runRequired($setting, '/usr/sbin/ip addr del ' . escapeshellarg($ip . '/32')
                    . ' dev ' . escapeshellarg($oldEgress), 'remove stale managed public IP ' . $ip);
            }
        }
    }

    private function removeEntireAppliedState(array $setting, array $old): void
    {
        $inside = (string)($old['inside_network'] ?? '');
        $egress = (string)($old['egress_interface'] ?? '');
        $start = (string)($old['public_start_ip'] ?? '');
        $end = (string)($old['public_end_ip'] ?? '');
        if ($inside !== '' && $egress !== '' && $start !== '' && $end !== '') {
            if ($this->snatRuleExists($this->fetchNatRules($setting), $inside, $egress, $start, $end)) {
                $this->runRequired($setting, '/usr/sbin/iptables -t nat -D POSTROUTING -s '
                    . escapeshellarg($inside) . ' -o ' . escapeshellarg($egress)
                    . ' -j SNAT --to-source ' . escapeshellarg($start . '-' . $end), 'remove the managed SNAT rule');
            }
            foreach ($this->expandIpRange($start, $end) as $ip) {
                if ($this->ipExistsOnInterface($setting, $egress, $ip)) {
                    $this->runRequired($setting, '/usr/sbin/ip addr del ' . escapeshellarg($ip . '/32')
                        . ' dev ' . escapeshellarg($egress), 'remove managed public IP ' . $ip);
                }
            }
        }
    }

    private function runRequired(array $setting, string $command, string $operation): void
    {
        $result = $this->bngService->runPrivilegedCommand($setting, $command);
        if ((int)$result['exit_code'] !== 0) {
            throw new InvalidArgumentException('Unable to ' . $operation . ': ' . trim((string)($result['stderr'] ?? '')));
        }
    }

    private function runBestEffort(array $setting, string $command): void
    {
        try { $this->bngService->runPrivilegedCommand($setting, $command); } catch (Throwable) {}
    }

    private function expandIpRange(string $start, string $end): array
    {
        $startLong = ip2long($start);
        $endLong = ip2long($end);

        if ($startLong === false || $endLong === false) {
            return [];
        }

        $startLong = (int)sprintf('%u', $startLong);
        $endLong = (int)sprintf('%u', $endLong);
        if ($startLong > $endLong || ($endLong - $startLong) > 1023) return [];

        $ips = [];
        for ($i = $startLong; $i <= $endLong; $i++) {
            $ips[] = long2ip($i);
        }

        return $ips;
    }

    private function assertInterfaceName(string $interface, string $label): void
    {
        if ($interface === '' || !preg_match('/^[A-Za-z0-9_.:-]{1,32}$/', $interface)) {
            throw new InvalidArgumentException($label . ' is required and must be a valid Linux interface name.');
        }
        if (str_contains($interface, '.') || str_contains($interface, '@') || preg_match('/^1WAN\d+$/i', $interface)) {
            throw new InvalidArgumentException($label . ' must be a physical server interface, not a VLAN or Accel-PPP session interface.');
        }
    }

    private function interfaceExists(array $setting, string $interface): bool
    {
        $result = $this->bngService->runPrivilegedCommand(
            $setting,
            '/usr/sbin/ip link show ' . escapeshellarg($interface)
        );
        return (int)$result['exit_code'] === 0;
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
            error_log('[Audit][CGNAT] ' . $e->getMessage());
        }
    }
}
