<?php

namespace App\Modules\CgnatManagement\Services;

use App\Infrastructure\Database\DatabaseConnection;
use App\Modules\Audit\Services\AuditService;
use App\Modules\CgnatManagement\DTOs\UpdateBngSettingDTO;
use App\Modules\CgnatManagement\Entities\BngSetting;
use App\Modules\CgnatManagement\Repositories\BngSettingRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

class BngConnectionService
{
    private BngSettingRepository $repo;
    private ?PDO $db = null;
    private ?AuditService $audit;

    private string $sshBinary = '/usr/bin/ssh';
    private string $sshpassBinary = '/usr/bin/sshpass';

    public function __construct(
        BngSettingRepository $repo,
        ?AuditService $audit = null
    ) {
        $this->repo = $repo;
        $this->audit = $audit;

        try {
            $this->db = (new DatabaseConnection())->get();
        } catch (Throwable $e) {
            $this->db = null;
        }
    }

    public function getSetting(): ?array
    {
        $row = $this->repo->get();
        return $row ? (new BngSetting($row))->toArray(false) : null;
    }

    public function saveSetting(UpdateBngSettingDTO $dto): array
    {
        $id = $this->repo->save($dto->toArray());
        $row = $this->repo->get();

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
            $result = $this->runRemoteCommand($setting, $this->withPrivilege($setting, $command));
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

        return [
            'ok' => true,
            'message' => 'BNG S-VLAN interface is ready.',
            'parent_interface' => $parent,
            'svlan' => $svlan,
            'interface' => $iface,
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

    public function runRemoteCommand(array $setting, string $command): array
    {
        if (!is_executable($this->sshBinary)) {
            throw new InvalidArgumentException('SSH binary not found at /usr/bin/ssh.');
        }

        $parts = [];

        if (($setting['auth_type'] ?? 'PASSWORD') === 'PASSWORD') {
            if (empty($setting['password'])) {
                throw new InvalidArgumentException('BNG password is empty.');
            }

            if (!is_executable($this->sshpassBinary)) {
                throw new InvalidArgumentException('sshpass is required for PASSWORD auth but was not found at /usr/bin/sshpass.');
            }

            $parts[] = escapeshellcmd($this->sshpassBinary);
            $parts[] = '-p';
            $parts[] = escapeshellarg($setting['password']);
        }

        $parts[] = escapeshellcmd($this->sshBinary);
        $parts[] = '-o';
        $parts[] = 'StrictHostKeyChecking=no';
        $parts[] = '-o';
        $parts[] = 'UserKnownHostsFile=/dev/null';
        $parts[] = '-o';
        $parts[] = 'LogLevel=ERROR';
        $parts[] = '-o';
        $parts[] = 'ConnectTimeout=8';
        $parts[] = '-p';
        $parts[] = escapeshellarg((string)$setting['port']);

        if (($setting['auth_type'] ?? 'KEY') === 'KEY' && !empty($setting['ssh_key_path'])) {
            $parts[] = '-i';
            $parts[] = escapeshellarg($setting['ssh_key_path']);
        }

        $parts[] = escapeshellarg($setting['username'] . '@' . $setting['host']);
        $parts[] = escapeshellarg($command);

        $shellCommand = implode(' ', $parts) . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($shellCommand, $output, $exitCode);

        $joined = trim(implode("\n", $output));

        return [
            'command' => $shellCommand,
            'stdout' => $exitCode === 0 ? $joined : '',
            'stderr' => $exitCode === 0 ? '' : $joined,
            'exit_code' => $exitCode,
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

            if (preg_match('/^1WAN\d+$/', $iface)) {
                $localIp = $this->firstIpv4($addrMap[$iface]['addresses'] ?? []);
                $peerIp = $this->peerIpv4($addrMap[$iface]['addresses'] ?? []);
                $ip = $peerIp ?: $localIp;

                $bngInterfaces[] = [
                    'interface' => $iface,
                    'ip' => $ip,
                    'local_ip' => $localIp,
                    'peer_ip' => $peerIp,
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
                ];

                if (!isset($svlanGroups[$svlan])) {
                    $svlanGroups[$svlan] = [
                        'svlan' => $svlan,
                        'interface' => "{$parent}.{$svlan}",
                        'parent_interface' => $parent,
                        'status' => $addrMap["{$parent}.{$svlan}"]['status'] ?? 'UNKNOWN',
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
                ];
            }
        }

        $this->attachPppSessionsToClients($svlanGroups, $bngInterfaces);

        ksort($svlanGroups);

        return [
            'parent_interfaces' => $parentInterfaces,
            'bng_interfaces' => $bngInterfaces,
            'client_vlan_interfaces' => $clientVlanInterfaces,
            'svlan_groups' => array_values($svlanGroups),
        ];
    }

    private function attachPppSessionsToClients(array &$svlanGroups, array $bngInterfaces): void
    {
        if (empty($svlanGroups) || empty($bngInterfaces)) {
            return;
        }

        usort($bngInterfaces, static function (array $a, array $b) {
            return strnatcmp((string)($a['interface'] ?? ''), (string)($b['interface'] ?? ''));
        });

        $i = 0;

        foreach ($svlanGroups as &$group) {
            usort($group['clients'], static function (array $a, array $b) {
                return ((int)($a['cvlan'] ?? 0)) <=> ((int)($b['cvlan'] ?? 0));
            });

            foreach ($group['clients'] as &$client) {
                if (!isset($bngInterfaces[$i])) {
                    continue;
                }

                $ppp = $bngInterfaces[$i];

                $client['ppp_interface'] = $ppp['interface'] ?? null;
                $client['ip'] = $ppp['ip'] ?? null;
                $client['local_ip'] = $ppp['local_ip'] ?? null;
                $client['peer_ip'] = $ppp['peer_ip'] ?? null;
                $client['ppp_username'] = $ppp['ppp_username'] ?? null;
                $client['subscriber_name'] = $ppp['subscriber_name'] ?? null;
                $client['service_id'] = $ppp['service_id'] ?? null;

                $i++;
            }
        }
    }

    private function getActiveSubscriberMap(): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->query("
                SELECT
                    ra.username,
                    ra.framed_ip,
                    ss.id AS service_id,
                    s.full_name AS subscriber_name
                FROM radius_accounting ra
                LEFT JOIN subscriber_services ss
                    ON ss.ppp_username = ra.username
                LEFT JOIN subscribers s
                    ON s.id = ss.subscriber_id
                WHERE ra.framed_ip IS NOT NULL
                  AND ra.framed_ip <> ''
                  AND (ra.session_stop IS NULL OR ra.session_stop = '')
                ORDER BY COALESCE(ra.session_start, ra.created_at) DESC
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $map = [];

            foreach ($rows as $row) {
                $ip = trim((string)($row['framed_ip'] ?? ''));

                if ($ip === '') {
                    continue;
                }

                $map[$ip] = [
                    'username' => $row['username'] ?? null,
                    'service_id' => isset($row['service_id']) ? (int)$row['service_id'] : null,
                    'subscriber_name' => $row['subscriber_name'] ?? null,
                ];
            }

            return $map;
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
        }
    }
}