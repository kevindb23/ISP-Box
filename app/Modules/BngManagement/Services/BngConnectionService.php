<?php

namespace App\Modules\BngManagement\Services;

use App\Infrastructure\NetworkAutomation\NetworkCommandRunner;
use App\Modules\Audit\Services\AuditService;
use App\Modules\BngManagement\DTOs\UpdateBngSettingDTO;
use App\Modules\BngManagement\Entities\BngSetting;
use App\Modules\BngManagement\Repositories\BngSettingRepository;
use App\Modules\BngManagement\Repositories\BngDesiredStateRepository;
use InvalidArgumentException;
use Throwable;

class BngConnectionService
{
    private BngSettingRepository $repo;
    private ?AuditService $audit;

    private string $sshBinary = '/usr/bin/ssh';
    private string $sshpassBinary = '/usr/bin/sshpass';

    public function __construct(
        BngSettingRepository $repo,
        private NetworkCommandRunner $networkRunner,
        private BngDesiredStateRepository $desiredState,
        ?AuditService $audit = null
    ) {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    public function getSetting(): ?array
    {
        $row = $this->repo->get();
        return $row ? (new BngSetting($row))->toArray(false) : null;
    }

    public function getSettings(): array
    {
        return array_map(fn(array $row) => (new BngSetting($row))->toArray(false), $this->repo->list());
    }

    public function saveSetting(UpdateBngSettingDTO $dto): array
    {
        $id = $this->repo->save($dto->toArray());
        $row = $this->repo->find($id);

        if (!$row || (int)$row['id'] !== $id) {
            throw new InvalidArgumentException('Failed to save BNG setting.');
        }

        $safe = (new BngSetting($row))->toArray(false);

        $this->auditLog(
            'UPDATE_BNG_SETTING',
            sprintf(
                'Updated BNG connection setting. Host=%s Port=%s Username=%s Auth=%s Parent=%s VLAN Mode=%s',
                $safe['host'] ?? '',
                $safe['port'] ?? '',
                $safe['username'] ?? '',
                $safe['auth_type'] ?? '',
                $safe['bng_parent_interface'] ?? '',
                $safe['vlan_mode'] ?? ''
            )
        );

        return $safe;
    }

    public function testConnection(): array
    {
        $setting = $this->getFullSetting();
        $result = $this->runRemoteCommand($setting, 'hostname');

        $ok = $result['exit_code'] === 0;

        $this->auditLog(
            'TEST_BNG_CONNECTION',
            sprintf(
                'Tested BNG SSH connection to %s:%d. Result=%s ExitCode=%d',
                $setting['host'],
                (int)$setting['port'],
                $ok ? 'SUCCESS' : 'FAILED',
                (int)$result['exit_code']
            )
        );

        return [
            'ok' => $ok,
            'host' => $setting['host'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'exit_code' => $result['exit_code'],
        ];
    }

    public function deleteSetting(): void
    {
        $setting = $this->getSetting();
        if (!$setting) return;

        $counts=$this->desiredState->dependentCounts();
        if(array_sum($counts)>0)throw new InvalidArgumentException('BNG connection cannot be deleted while deployed VLANs, enabled CGNAT state, Accel-PPP profiles, or FRR Router profiles depend on it.');
        if (!$this->repo->delete()) {
            throw new InvalidArgumentException('Failed to delete BNG setting.');
        }

        $this->auditLog(
            'DELETE_BNG_SETTING',
            sprintf('Deleted BNG connection setting. Host=%s Parent=%s', $setting['host'] ?? '', $setting['bng_parent_interface'] ?? '')
        );
    }

    public function getRuntimeStatus(): array
    {
        $setting = $this->getFullSetting();

        $routesResult = $this->runRemoteCommand($setting, $this->withPrivilege($setting, '/usr/sbin/ip route'));
        $linksResult = $this->runRemoteCommand($setting, $this->withPrivilege($setting, '/usr/sbin/ip -o link show'));
        $addrResult = $this->runRemoteCommand($setting, $this->withPrivilege($setting, '/usr/sbin/ip -br addr'));
        $natResult = $this->runRemoteCommand($setting, $this->withPrivilege($setting, '/usr/sbin/iptables -t nat -S POSTROUTING'));

        $routes = $this->lines($routesResult['stdout']);
        $linkLines = $this->lines($linksResult['stdout']);
        $addrLines = $this->lines($addrResult['stdout']);
        $natRules = $this->lines($natResult['stdout']);

        $interfaces = $this->parseInterfaces($linkLines);
        $addrMap = $this->parseAddressMap($addrLines);

        $detectedInterface = $this->detectPreferredInterface(
            $routes,
            $interfaces,
            $setting['preferred_interface'] ?? null
        );

        $runtimeMap = $this->buildRuntimeInterfaceMap(
            $interfaces,
            $addrMap,
            trim((string)($setting['bng_parent_interface'] ?? ''))
        );
        $runtimeMap = $this->mergeDesiredVlanInterfaces(
            $runtimeMap,
            $this->desiredState->interfaces(),
            trim((string)($setting['bng_parent_interface'] ?? ''))
        );

        return [
            'connection' => [
                'host' => $setting['host'],
                'port' => (int)$setting['port'],
                'username' => $setting['username'],
                'auth_type' => $setting['auth_type'],
                'preferred_interface' => $setting['preferred_interface'] ?? null,
                'bng_parent_interface' => $setting['bng_parent_interface'] ?? null,
                'auto_create_svlan_interface' => (int)($setting['auto_create_svlan_interface'] ?? 1),
                'vlan_mode' => $setting['vlan_mode'] ?? 'QINQ',
            ],
            'default_interface' => $detectedInterface,
            'bng_parent_interface' => $setting['bng_parent_interface'] ?? null,
            'interfaces' => $interfaces,
            'parent_interfaces' => $runtimeMap['parent_interfaces'],
            'bng_interfaces' => $runtimeMap['bng_interfaces'],
            'client_vlan_interfaces' => $runtimeMap['client_vlan_interfaces'],
            'svlan_groups' => $runtimeMap['svlan_groups'],
            'routes' => $routes,
            'iptables' => $natRules,
            'debug' => [
                'routes_exit_code' => $routesResult['exit_code'],
                'links_exit_code' => $linksResult['exit_code'],
                'addr_exit_code' => $addrResult['exit_code'],
                'nat_exit_code' => $natResult['exit_code'],
                'routes_stderr' => $routesResult['stderr'],
                'links_stderr' => $linksResult['stderr'],
                'addr_stderr' => $addrResult['stderr'],
                'nat_stderr' => $natResult['stderr'],
            ],
        ];
    }

    private function mergeDesiredVlanInterfaces(array $runtimeMap, array $desired, string $configuredParent): array
    {
        $liveCvlans = [];
        foreach ($runtimeMap['client_vlan_interfaces'] ?? [] as $row) {
            $liveCvlans[(string)($row['interface'] ?? '')] = true;
        }

        $svlanIndexes = [];
        foreach ($runtimeMap['svlan_groups'] ?? [] as $index => $row) {
            $svlanIndexes[(string)($row['interface'] ?? '')] = $index;
        }

        foreach ($desired as $row) {
            $interface = trim((string)($row['interface'] ?? ''));
            if ($interface === '' || !preg_match('/^(.+?)\.(\d+)(?:\.(\d+))?$/', $interface, $matches)) {
                continue;
            }

            $parent = (string)$matches[1];
            if ($configuredParent !== '' && $parent !== $configuredParent) {
                continue;
            }

            $svlan = (int)$matches[2];
            $cvlan = isset($matches[3]) ? (int)$matches[3] : null;
            $svlanInterface = $parent . '.' . $svlan;

            if (!isset($svlanIndexes[$svlanInterface])) {
                $runtimeMap['svlan_groups'][] = [
                    'svlan' => $svlan,
                    'interface' => $svlanInterface,
                    'parent_interface' => $parent,
                    'status' => 'NOT PRESENT',
                    'addresses' => [],
                    'clients' => [],
                    'desired' => true,
                ];
                $svlanIndexes[$svlanInterface] = array_key_last($runtimeMap['svlan_groups']);
            }

            if ($cvlan === null || isset($liveCvlans[$interface])) {
                continue;
            }

            $missing = [
                'interface' => $interface,
                'parent_interface' => $parent,
                'svlan' => $svlan,
                'cvlan' => $cvlan,
                'status' => 'NOT PRESENT',
                'ip' => null,
                'addresses' => [],
                'desired' => true,
            ];
            $runtimeMap['client_vlan_interfaces'][] = $missing;
            $runtimeMap['svlan_groups'][$svlanIndexes[$svlanInterface]]['clients'][] = [
                'cvlan' => $cvlan,
                'interface' => $interface,
                'status' => 'NOT PRESENT',
                'ppp_interface' => null,
                'ip' => null,
                'local_ip' => null,
                'peer_ip' => null,
                'ppp_username' => null,
                'subscriber_name' => null,
                'service_id' => null,
                'desired' => true,
            ];
            $liveCvlans[$interface] = true;
        }

        usort($runtimeMap['svlan_groups'], static fn(array $a, array $b): int => (int)$a['svlan'] <=> (int)$b['svlan']);
        usort($runtimeMap['client_vlan_interfaces'], static fn(array $a, array $b): int => strnatcasecmp((string)$a['interface'], (string)$b['interface']));

        return $runtimeMap;
    }

    public function ensureSvlanInterface(int $svlan): array
    {
        if ($svlan < 1 || $svlan > 4094) {
            throw new InvalidArgumentException('Invalid S-VLAN ID.');
        }

        $setting = $this->getFullSetting();

        if ((int)($setting['auto_create_svlan_interface'] ?? 1) !== 1) {
            $this->auditLog(
                'ENSURE_SVLAN_INTERFACE_SKIPPED',
                sprintf('Skipped creating BNG S-VLAN interface for S-VLAN %d because auto-create is disabled.', $svlan)
            );

            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Auto-create S-VLAN interface is disabled.',
            ];
        }

        $vlanMode = strtoupper(trim((string)($setting['vlan_mode'] ?? 'QINQ')));

        if ($vlanMode !== 'QINQ') {
            $this->auditLog(
                'ENSURE_SVLAN_INTERFACE_SKIPPED',
                sprintf('Skipped creating BNG S-VLAN interface for S-VLAN %d because VLAN mode is %s.', $svlan, $vlanMode)
            );

            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'VLAN mode is not QINQ. No S-VLAN interface created.',
                'vlan_mode' => $vlanMode,
            ];
        }

        $parent = trim((string)($setting['bng_parent_interface'] ?? ''));

        if ($parent === '') {
            throw new InvalidArgumentException('BNG parent interface is not configured.');
        }

        if (!$this->isSafeInterfaceName($parent)) {
            throw new InvalidArgumentException('Invalid BNG parent interface name.');
        }

        $iface = $parent . '.' . $svlan;

        $commands = [
            'check_parent' => "/usr/sbin/ip link show " . escapeshellarg($parent),
            'ensure_vlan' => "/usr/sbin/ip link show " . escapeshellarg($iface) .
                " || /usr/sbin/ip link add link " . escapeshellarg($parent) .
                " name " . escapeshellarg($iface) .
                " type vlan id " . (int)$svlan,
            'set_up' => "/usr/sbin/ip link set " . escapeshellarg($iface) . " up",
            'verify' => "/usr/sbin/ip -br link show " . escapeshellarg($iface),
        ];

        $results = [];

        foreach ($commands as $key => $command) {
            $result = $this->runPrivilegedCommand($setting, $command);
            $results[$key] = $result;

            if ($result['exit_code'] !== 0) {
                $this->auditLog(
                    'ENSURE_SVLAN_INTERFACE_FAILED',
                    sprintf(
                        'Failed to prepare BNG S-VLAN interface %s at step %s.',
                        $iface,
                        $key
                    )
                );

                return [
                    'ok' => false,
                    'message' => 'Failed to prepare BNG S-VLAN interface at step: ' . $key,
                    'parent_interface' => $parent,
                    'svlan' => $svlan,
                    'interface' => $iface,
                    'results' => $results,
                ];
            }
        }

        $this->auditLog(
            'ENSURE_SVLAN_INTERFACE',
            sprintf(
                'Prepared BNG S-VLAN interface %s on parent %s for S-VLAN %d.',
                $iface,
                $parent,
                $svlan
            )
        );
        $this->repo->recordDesiredInterface($svlan, $iface);

        return [
            'ok' => true,
            'message' => 'BNG S-VLAN interface is ready.',
            'parent_interface' => $parent,
            'svlan' => $svlan,
            'interface' => $iface,
            'results' => $results,
        ];
    }

    public function ensureCvlanInterface(int $svlan, int $cvlan): array
    {
        if ($svlan < 1 || $svlan > 4094) {
            throw new InvalidArgumentException('Invalid S-VLAN ID.');
        }

        if ($cvlan < 1 || $cvlan > 4094) {
            throw new InvalidArgumentException('Invalid C-VLAN ID.');
        }

        $setting = $this->getFullSetting();
        $vlanMode = strtoupper(trim((string)($setting['vlan_mode'] ?? 'QINQ')));
        if ($vlanMode !== 'QINQ') {
            throw new InvalidArgumentException('BNG VLAN mode must be QINQ to create a nested C-VLAN interface.');
        }

        $parent = trim((string)($setting['bng_parent_interface'] ?? ''));
        if ($parent === '' || !$this->isSafeInterfaceName($parent)) {
            throw new InvalidArgumentException('BNG parent interface is not configured or is invalid.');
        }

        $svlanInterface = $parent . '.' . $svlan;
        $cvlanInterface = $svlanInterface . '.' . $cvlan;
        $commands = [
            'check_parent' => '/usr/sbin/ip link show ' . escapeshellarg($parent),
            'ensure_svlan' => '/usr/sbin/ip link show ' . escapeshellarg($svlanInterface)
                . ' || /usr/sbin/ip link add link ' . escapeshellarg($parent)
                . ' name ' . escapeshellarg($svlanInterface)
                . ' type vlan id ' . $svlan,
            'set_svlan_up' => '/usr/sbin/ip link set ' . escapeshellarg($svlanInterface) . ' up',
            'ensure_cvlan' => '/usr/sbin/ip link show ' . escapeshellarg($cvlanInterface)
                . ' || /usr/sbin/ip link add link ' . escapeshellarg($svlanInterface)
                . ' name ' . escapeshellarg($cvlanInterface)
                . ' type vlan id ' . $cvlan,
            'set_cvlan_up' => '/usr/sbin/ip link set ' . escapeshellarg($cvlanInterface) . ' up',
            'verify' => '/usr/sbin/ip -br link show ' . escapeshellarg($cvlanInterface),
        ];

        $results = [];
        foreach ($commands as $key => $command) {
            $result = $this->runPrivilegedCommand($setting, $command);
            $results[$key] = $result;
            if ($result['exit_code'] !== 0) {
                $this->auditLog(
                    'ENSURE_CVLAN_INTERFACE_FAILED',
                    sprintf('Failed to prepare BNG C-VLAN interface %s at step %s.', $cvlanInterface, $key)
                );

                return [
                    'ok' => false,
                    'message' => 'Failed to prepare BNG C-VLAN interface at step: ' . $key,
                    'parent_interface' => $parent,
                    'svlan' => $svlan,
                    'cvlan' => $cvlan,
                    'interface' => $cvlanInterface,
                    'results' => $results,
                ];
            }
        }

        $this->auditLog(
            'ENSURE_CVLAN_INTERFACE',
            sprintf('Prepared BNG C-VLAN interface %s on S-VLAN %d.', $cvlanInterface, $svlan)
        );
        $this->repo->recordDesiredInterface($cvlan, $cvlanInterface);

        return [
            'ok' => true,
            'message' => 'BNG C-VLAN interface is ready.',
            'parent_interface' => $parent,
            'svlan' => $svlan,
            'cvlan' => $cvlan,
            'interface' => $cvlanInterface,
            'results' => $results,
        ];
    }

    public function getFullSetting(): array
    {
        $row = $this->repo->get();

        if (!$row) {
            throw new InvalidArgumentException('BNG connection is not configured.');
        }

        $entity = new BngSetting($row);

        if ($entity->enabled !== 1) {
            throw new InvalidArgumentException('BNG connection is disabled.');
        }

        if ($entity->host === '' || $entity->username === '') {
            throw new InvalidArgumentException('BNG host/username is incomplete.');
        }

        return $entity->toArray(true);
    }

    public function scanHostKey(): array
    {
        $row=$this->repo->get(); if(!$row)throw new InvalidArgumentException('BNG connection is not configured.');
        $binary='/usr/bin/ssh-keyscan'; if(!is_executable($binary))throw new InvalidArgumentException('ssh-keyscan is not installed.');
        $result=$this->networkRunner->run([$binary,'-T','8','-p',(string)(int)$row['port'],(string)$row['host']],'',15);
        $lines=array_values(array_filter(preg_split('/\R/',$result->stdout)?:[],static fn($line)=>$line!==''&&!str_starts_with($line,'#')));
        if(!$result->succeeded()||$lines===[])throw new InvalidArgumentException('Unable to retrieve the BNG SSH host key.');
        $line=(string)$lines[0];$parts=preg_split('/\s+/',trim($line));if(count($parts)<3)throw new InvalidArgumentException('The BNG returned an invalid SSH host key.');
        $decoded=base64_decode((string)$parts[2],true);if($decoded===false)throw new InvalidArgumentException('The BNG host key is malformed.');
        return ['key'=>$line,'fingerprint'=>'SHA256:'.rtrim(base64_encode(hash('sha256',$decoded,true)),'='),'host'=>$row['host'],'port'=>(int)$row['port']];
    }

    public function trustHostKey(string $expectedFingerprint): array
    {
        $scan=$this->scanHostKey(); if(!hash_equals($scan['fingerprint'],trim($expectedFingerprint)))throw new InvalidArgumentException('SSH host-key fingerprint changed before confirmation.');
        $row=$this->repo->get();$this->repo->trustHostKey((int)$row['id'],$scan['key'],$scan['fingerprint']);
        $this->auditLog('TRUST_BNG_HOST_KEY','Trusted BNG SSH host key '.$scan['fingerprint'].' for '.$scan['host'].':'.$scan['port'].'.');
        unset($scan['key']);return $scan+['trusted'=>true];
    }

    public function desiredInterfaces(): array
    {
        return $this->repo->desiredInterfaces();
    }
    public function forgetVlanInterface(string $type,int $vlanId,?int $parentSvlan): void
    {
        $setting=$this->getFullSetting();$parent=(string)$setting['bng_parent_interface'];$interface=strtoupper($type)==='S_VLAN'?$parent.'.'.$vlanId:$parent.'.'.(int)$parentSvlan.'.'.$vlanId;$this->repo->removeDesiredInterface($interface);$this->auditLog('REMOVE_BNG_DESIRED_INTERFACE','Removed '.$interface.' from BNG reboot desired state after VLAN deletion.');
    }

    public function runRemoteCommand(array $setting, string $command): array
    {
        return $this->runRemoteCommandWithInput($setting, $command, '');
    }

    public function runPrivilegedCommand(array $setting, string $command, string $stdin = ''): array
    {
        if (strtolower(trim((string)($setting['username'] ?? ''))) === 'root') {
            return $this->runRemoteCommandWithInput($setting, $command, $stdin);
        }
        $password = (string)($setting['password'] ?? '');
        if ($password === '') throw new InvalidArgumentException('The saved BNG password is required for privileged access.');
        return $this->runRemoteCommandWithInput(
            $setting,
            "sudo -S -p '' /bin/sh -c " . escapeshellarg($command),
            $password . "\n" . $stdin
        );
    }

    public function runRemoteCommandWithInput(array $setting, string $command, string $stdin): array
    {
        if (!is_executable($this->sshBinary)) {
            throw new InvalidArgumentException('SSH binary not found at /usr/bin/ssh.');
        }

        $parts = [];
        $extraInput = [];

        if (($setting['auth_type'] ?? 'PASSWORD') === 'PASSWORD') {
            if (empty($setting['password'])) {
                throw new InvalidArgumentException('BNG password is empty.');
            }

            if (!is_executable($this->sshpassBinary)) {
                throw new InvalidArgumentException('sshpass is required for PASSWORD auth but was not found at /usr/bin/sshpass.');
            }

            $parts[] = $this->sshpassBinary;
            $parts[] = '-d';
            $parts[] = '3';
            $extraInput[3] = (string)$setting['password'] . "\n";
        }

        $parts[] = $this->sshBinary;
        $knownHost=trim((string)($setting['known_host_key']??''));
        if($knownHost==='')throw new InvalidArgumentException('BNG SSH host key is not trusted. Scan and confirm its fingerprint first.');
        $runtimeUid=function_exists('posix_geteuid')?(int)posix_geteuid():(int)getmyuid();
        $knownHostsDir=sys_get_temp_dir().'/nexusbox-bng-ssh-'.$runtimeUid;
        if(!is_dir($knownHostsDir)&&!mkdir($knownHostsDir,0700,true)&&!is_dir($knownHostsDir))throw new InvalidArgumentException('Unable to prepare SSH known-host storage.');
        if((int)@fileowner($knownHostsDir)!==$runtimeUid)throw new InvalidArgumentException('SSH known-host storage has an invalid owner.');
        @chmod($knownHostsDir,0700);
        $knownHostsFile=$knownHostsDir.'/bng_known_hosts';if(@file_put_contents($knownHostsFile,$knownHost."\n",LOCK_EX)===false)throw new InvalidArgumentException('Unable to store the trusted BNG host key.');@chmod($knownHostsFile,0600);
        $parts[] = '-o';
        $parts[] = 'StrictHostKeyChecking=yes';
        $parts[] = '-o';
        $parts[] = 'UserKnownHostsFile='.$knownHostsFile;
        $parts[] = '-o';
        $parts[] = 'LogLevel=ERROR';
        $parts[] = '-o';
        $parts[] = 'ConnectTimeout=8';
        $parts[] = '-p';
        $parts[] = (string)$setting['port'];

        if (($setting['auth_type'] ?? 'KEY') === 'KEY' && !empty($setting['ssh_key_path'])) {
            $parts[] = '-i';
            $parts[] = (string)$setting['ssh_key_path'];
        }

        $parts[] = $setting['username'] . '@' . $setting['host'];
        $parts[] = $command;

        $execution = $this->networkRunner->run($parts, $stdin, 30, $extraInput);
        $joined = $execution->stdout !== '' ? $execution->stdout : $execution->stderr;

        return [
            'operation' => 'bng_remote_command',
            'stdout' => $execution->succeeded() ? $joined : '',
            'stderr' => $execution->succeeded() ? '' : $joined,
            'exit_code' => $execution->exitCode,
            'duration_ms' => $execution->durationMs,
            'timed_out' => $execution->timedOut,
        ];
    }

    private function buildRuntimeInterfaceMap(array $interfaces, array $addrMap, string $configuredParent): array
    {
        $subscriberMap = $this->getActiveSubscriberMap();

        $parentInterfaces = [];
        $bngInterfaces = [];
        $clientVlanInterfaces = [];
        $svlanGroups = [];

        foreach ($interfaces as $iface) {
            if ($iface === 'lo') {
                continue;
            }

            $interfaceAddresses = $addrMap[$iface]['addresses'] ?? [];
            $detectedPeerIp = $this->peerIpv4($interfaceAddresses);
            if (preg_match('/^(?:1WAN|BNG)\d+$/', $iface) || $detectedPeerIp !== null) {
                $localIp = $this->firstIpv4($interfaceAddresses);
                $peerIp = $detectedPeerIp;
                $ip = $peerIp ?: $localIp;

                $bngInterfaces[] = [
                    'interface' => $iface,
                    'ip' => $ip,
                    'local_ip' => $localIp,
                    'peer_ip' => $peerIp,
                    'local_address' => $this->firstIpv4Cidr($addrMap[$iface]['addresses'] ?? []),
                    'peer_address' => $this->peerIpv4Cidr($addrMap[$iface]['addresses'] ?? []),
                    'addresses' => $this->addressValues($addrMap[$iface]['addresses'] ?? []),
                    'status' => $addrMap[$iface]['status'] ?? 'UNKNOWN',
                    'subscriber_name' => $ip ? ($subscriberMap[$ip]['subscriber_name'] ?? null) : null,
                    'ppp_username' => $ip ? ($subscriberMap[$ip]['username'] ?? null) : null,
                    'service_id' => $ip ? ($subscriberMap[$ip]['service_id'] ?? null) : null,
                ];

                continue;
            }

            if (preg_match('/^(.+)\.(\d+)\.(\d+)$/', $iface, $m)) {
                $parent = $m[1];
                $svlan = (int)$m[2];
                $cvlan = (int)$m[3];

                $clientVlanInterfaces[] = [
                    'interface' => $iface,
                    'parent_interface' => $parent,
                    'svlan' => $svlan,
                    'cvlan' => $cvlan,
                    'status' => $addrMap[$iface]['status'] ?? 'UNKNOWN',
                    'ip' => $this->firstIpv4($addrMap[$iface]['addresses'] ?? []),
                    'addresses' => $this->addressValues($addrMap[$iface]['addresses'] ?? []),
                ];

                if (!isset($svlanGroups[$svlan])) {
                    $svlanGroups[$svlan] = [
                        'svlan' => $svlan,
                        'interface' => "{$parent}.{$svlan}",
                        'parent_interface' => $parent,
                        'status' => $addrMap["{$parent}.{$svlan}"]['status'] ?? 'UNKNOWN',
                        'addresses' => $this->addressValues($addrMap["{$parent}.{$svlan}"]['addresses'] ?? []),
                        'clients' => [],
                    ];
                }

                $svlanGroups[$svlan]['clients'][] = [
                    'cvlan' => $cvlan,
                    'interface' => $iface,
                    'status' => $addrMap[$iface]['status'] ?? 'UNKNOWN',
                    'ppp_interface' => null,
                    'ip' => null,
                    'local_ip' => null,
                    'peer_ip' => null,
                    'ppp_username' => null,
                    'subscriber_name' => null,
                    'service_id' => null,
                ];

                continue;
            }

            if (preg_match('/^(.+)\.(\d+)$/', $iface, $m)) {
                $parent = $m[1];
                $svlan = (int)$m[2];

                if (!isset($svlanGroups[$svlan])) {
                    $svlanGroups[$svlan] = [
                        'svlan' => $svlan,
                        'interface' => $iface,
                        'parent_interface' => $parent,
                        'status' => $addrMap[$iface]['status'] ?? 'UNKNOWN',
                        'addresses' => $this->addressValues($addrMap[$iface]['addresses'] ?? []),
                        'clients' => [],
                    ];
                }

                continue;
            }

            if ($this->looksLikePhysicalInterface($iface)) {
                $parentInterfaces[] = [
                    'interface' => $iface,
                    'role' => $iface === $configuredParent ? 'BNG_ACCESS_PARENT' : 'PHYSICAL',
                    'status' => $addrMap[$iface]['status'] ?? 'UNKNOWN',
                    'ip' => $this->firstIpv4($addrMap[$iface]['addresses'] ?? []),
                    'addresses' => $this->addressValues($addrMap[$iface]['addresses'] ?? []),
                ];
            }
        }

        ksort($svlanGroups);

        return [
            'parent_interfaces' => $parentInterfaces,
            'bng_interfaces' => $bngInterfaces,
            'client_vlan_interfaces' => $clientVlanInterfaces,
            'svlan_groups' => array_values($svlanGroups),
        ];
    }

    private function getActiveSubscriberMap(): array
    {
        try {
            return $this->repo->activeSubscriberMap();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function parseAddressMap(array $lines): array
    {
        $map = [];

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if (count($parts) < 2) {
                continue;
            }

            $iface = $parts[0];

            if (str_contains($iface, '@')) {
                $iface = explode('@', $iface)[0];
            }

            $status = $parts[1] ?? 'UNKNOWN';
            $addresses = array_slice($parts, 2);

            $map[$iface] = [
                'status' => $status,
                'addresses' => $addresses,
                'local_ip' => $this->firstIpv4($addresses),
                'peer_ip' => $this->peerIpv4($addresses),
            ];
        }

        return $map;
    }

    private function firstIpv4(array $addresses): ?string
    {
        foreach ($addresses as $addr) {
            if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})(?:\/\d+)?$/', (string)$addr, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function peerIpv4(array $addresses): ?string
    {
        $count = count($addresses);

        for ($i = 0; $i < $count; $i++) {
            if (($addresses[$i] ?? null) === 'peer' && isset($addresses[$i + 1])) {
                $peer = (string)$addresses[$i + 1];

                if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})(?:\/\d+)?$/', $peer, $m)) {
                    return $m[1];
                }
            }
        }

        return null;
    }

    private function firstIpv4Cidr(array $addresses): ?string
    {
        foreach ($addresses as $address) {
            if (preg_match('/^\d{1,3}(?:\.\d{1,3}){3}(?:\/\d+)?$/', (string)$address)) {
                return (string)$address;
            }
        }
        return null;
    }

    private function peerIpv4Cidr(array $addresses): ?string
    {
        foreach ($addresses as $index => $address) {
            if ($address === 'peer' && isset($addresses[$index + 1])) {
                return (string)$addresses[$index + 1];
            }
        }
        return null;
    }

    private function addressValues(array $addresses): array
    {
        return array_values(array_filter(
            array_map('strval', $addresses),
            static fn(string $value): bool => $value !== '' && $value !== 'peer'
        ));
    }

    private function withPrivilege(array $setting, string $command): string
    {
        $username = trim((string)($setting['username'] ?? ''));

        if ($username === '' || strtolower($username) === 'root') {
            return $command;
        }

        return 'sudo -n ' . $command;
    }

    private function lines(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($text)) ?: [],
            static fn(string $line) => trim($line) !== ''
        ));
    }

    private function parseInterfaces(array $lines): array
    {
        $interfaces = [];

        foreach ($lines as $line) {
            if (preg_match('/^\d+:\s*([^:]+):/', $line, $m)) {
                $name = trim($m[1]);

                if (str_contains($name, '@')) {
                    $name = explode('@', $name)[0];
                }

                if ($name !== 'lo') {
                    $interfaces[] = $name;
                }
            }
        }

        return array_values(array_unique($interfaces));
    }

    private function detectPreferredInterface(array $routes, array $interfaces = [], ?string $preferredInterface = null): string
    {
        if ($preferredInterface !== null && trim($preferredInterface) !== '') {
            return trim($preferredInterface);
        }

        foreach ($routes as $line) {
            if (preg_match('/^default(?: via \S+)? dev (\S+)/', $line, $m)) {
                return $m[1];
            }
        }

        foreach ($interfaces as $iface) {
            if ($this->looksLikePhysicalInterface($iface)) {
                return $iface;
            }
        }

        return $interfaces[0] ?? '';
    }

    private function looksLikePhysicalInterface(string $iface): bool
    {
        if ($iface === '' || $iface === 'lo' || str_contains($iface, '.')) {
            return false;
        }

        return (bool)preg_match('/^(ens|eno|enp|eth|bond|team|br|vmbr)\d*/', $iface);
    }

    private function isSafeInterfaceName(string $iface): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_.:-]+$/', $iface);
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
