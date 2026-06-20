<?php

namespace App\Modules\OntDevices\Services;

use PDO;
use Throwable;

class AcsService
{
    private string $acsBaseUrl;
    private ?PDO $pdo = null;

    public function __construct()
    {
        $this->acsBaseUrl = rtrim(
            getenv('ACS_URL') ?: ($_ENV['ACS_URL'] ?? 'http://10.0.10.156:7557'),
            '/'
        );

        try {
            if (class_exists(\App\Infrastructure\Database\DatabaseConnection::class)) {
                $this->pdo = (new \App\Infrastructure\Database\DatabaseConnection())->get();
            } elseif (class_exists(\Framework\DatabaseConnection::class)) {
                $this->pdo = (new \Framework\DatabaseConnection())->get();
            } else {
                $this->pdo = null;
                error_log('ACS PDO INIT ERROR: No supported DatabaseConnection class found.');
            }
        } catch (Throwable $e) {
            $this->pdo = null;
            error_log('ACS PDO INIT ERROR: ' . $e->getMessage());
        }
    }

    public function getDevices(): array
    {
        $data = $this->request('GET', '/devices');
        return is_array($data) ? $data : [];
    }

    public function getDevice(string $deviceId): ?array
    {
        $deviceId = trim($deviceId);

        if ($deviceId === '') {
            return null;
        }

        $queryById = urlencode(json_encode([
            '_id' => $deviceId
        ]));

        $data = $this->request('GET', '/devices/?query=' . $queryById);

        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        $queryByAltId = urlencode(json_encode([
            'DeviceID.ID' => $deviceId
        ]));

        $data = $this->request('GET', '/devices/?query=' . $queryByAltId);

        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return null;
    }

    public function normalizeDevices(array $devices): array
    {
        $rows = [];

        foreach ($devices as $device) {
            if (!is_array($device)) {
                continue;
            }

            $rows[] = $this->normalizeDevice($device);
        }

        usort($rows, function (array $a, array $b) {
            return strcmp((string)($b['last_seen'] ?? ''), (string)($a['last_seen'] ?? ''));
        });

        return $rows;
    }

    public function normalizeDevice(array $device): array
    {
        $deviceId = (string)($device['_id'] ?? $this->firstValue($device, [
            ['DeviceID', 'ID', '_value'],
            ['DeviceID.ID'],
        ]) ?? '');

        $serial = $this->extractSerialNumber($device);
        $vendor = $this->extractVendor($device);
        $model = $this->extractModel($device);
        $firmware = $this->extractFirmware($device);

        $uptimeSeconds = $this->extractUptimeSeconds($device);
        $lastSeen = $this->extractLastSeen($device);

        $wanInfo = $this->extractWanFullInfo($device);
        $wanIp = $wanInfo['ip'] ?? null;
        $wanStatus = $wanInfo['status'] ?? 'UNKNOWN';
        $wanMode = $wanInfo['mode'] ?? 'UNKNOWN';
        $wanUsername = $wanInfo['username'] ?? null;
        $wanService = $wanInfo['service'] ?? null;
        $wanVlan = $wanInfo['vlan'] ?? null;
        $wanEditable = strtoupper((string)$wanMode) !== 'PPPOE';

        $managementIp = $this->extractManagementIp($device);
        $ssid = $this->extractPrimarySsid($device);
        $clientCount = count($this->extractWifiClients($device));
        $inventory = $this->findInventoryBySerial((string)$serial);

        return [
            'id' => $deviceId,
            'device_id' => $deviceId,
            'serial_number' => $serial ?: '-',
            'vendor' => $vendor ?: '-',
            'model' => $model ?: '-',
            'firmware_version' => $firmware ?: '-',
            'wan_ip' => $wanIp ?: '-',
            'wan_status' => $wanStatus,
            'wan_mode' => $wanMode,
            'wan_username' => $wanUsername,
            'wan_service' => $wanService,
            'wan_vlan' => $wanVlan,
            'wan_editable' => $wanEditable,
            'management_ip' => $managementIp ?: '-',
            'ssid' => $ssid ?: '-',
            'uptime_seconds' => $uptimeSeconds,
            'uptime_days' => $this->formatUptimeDays($uptimeSeconds),
            'uptime' => $this->formatDuration($uptimeSeconds),
            'last_seen' => $lastSeen,
            'acs_status' => $this->isRecentTimestamp($lastSeen, 1800) ? 'ONLINE' : 'OFFLINE',
            'client_count' => $clientCount,
            'inventory_id' => $inventory['id'] ?? null,
            'inventory_state' => $inventory ? 'MATCHED' : 'UNMATCHED',
            'inventory_info' => $inventory,
        ];
    }

    public function isRecentlyOnline(array $device, int $seconds = 1800): bool
    {
        $lastSeen = $this->extractLastSeen($device);

        if (!$lastSeen) {
            return false;
        }

        $ts = strtotime($lastSeen);
        if ($ts === false) {
            return false;
        }

        return (time() - $ts) <= $seconds;
    }

    public function refreshDevice(string $deviceId): array
    {
        return $this->request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request', [
            'name' => 'refreshObject',
            'objectName' => ''
        ]);
    }

    public function rebootDevice(string $deviceId): array
    {
        return $this->request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request', [
            'name' => 'reboot'
        ]);
    }

    public function factoryResetDevice(string $deviceId): array
    {
        return $this->request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request', [
            'name' => 'factoryReset'
        ]);
    }

    public function setPppCredentials(string $deviceId, string $username, string $password): array
    {
        $deviceId = trim($deviceId);
        $username = trim($username);
        $password = trim($password);

        if ($deviceId === '') {
            throw new \RuntimeException('Device ID is required.');
        }

        if ($username === '' || $password === '') {
            throw new \RuntimeException('PPP username and password are required.');
        }

        // Refresh WAN tree first so PPP objects are visible before path selection
        $initialRefresh = $this->request(
            'POST',
            '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request',
            [
                'name' => 'refreshObject',
                'objectName' => 'InternetGatewayDevice.WANDevice.1'
            ]
        );

        $device = $this->getDevice($deviceId);

        if (!$device) {
            throw new \RuntimeException('ACS device not found.');
        }

        $pppPath = $this->findPppoeConnectionPath($device);

        if (!$pppPath) {
            throw new \RuntimeException('PPP WAN path not found on ACS device.');
        }

        $setTask = $this->request(
            'POST',
            '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request',
            [
                'name' => 'setParameterValues',
                'parameterValues' => [
                    [$pppPath . '.Username', $username, 'xsd:string'],
                    [$pppPath . '.Password', $password, 'xsd:string'],
                    [$pppPath . '.Enable', true, 'xsd:boolean'],
                    [$pppPath . '.ConnectionTrigger', 'AlwaysOn', 'xsd:string'],
                ],
            ]
        );

        // Refresh exact PPP object after writing credentials
        $refreshPpp = $this->request(
            'POST',
            '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request',
            [
                'name' => 'refreshObject',
                'objectName' => $pppPath
            ]
        );

        // Refresh whole WAN tree so UI sees updated ConnectionStatus / IP if device reports it
        $refreshWan = $this->request(
            'POST',
            '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request',
            [
                'name' => 'refreshObject',
                'objectName' => 'InternetGatewayDevice.WANDevice.1'
            ]
        );

        return [
            'ok' => true,
            'message' => 'PPP credentials pushed to ACS.',
            'ppp_path' => $pppPath,
            'task' => $setTask,
            'refresh_ppp' => $refreshPpp,
            'refresh_wan' => $refreshWan,
            'initial_refresh' => $initialRefresh,
        ];
    }

    public function wifiConfig(
        string $deviceId,
        string $ssid,
        ?string $password = null,
        ?bool $enable = null,
        ?bool $radioEnabled = null,
        ?bool $hideSsid = null,
        ?bool $autoChannel = null,
        ?string $channel = null,
        ?string $txPower = null,
        ?string $beaconType = null,
        ?string $encryption = null,
        ?bool $wpsEnable = null,
        ?bool $wmmEnable = null,
        ?bool $macFilterEnable = null
    ): array {
        $deviceId = trim($deviceId);
        $ssid = trim($ssid);

        if ($deviceId === '') {
            throw new \RuntimeException('Device ID missing.');
        }

        if ($ssid === '') {
            throw new \RuntimeException('SSID is required.');
        }

        $results = [];

        $results['ssid_task'] = $this->request(
            'POST',
            '/devices/' . rawurlencode($deviceId) . '/tasks',
            [
                'name' => 'setParameterValues',
                'parameterValues' => [
                    [
                        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
                        $ssid,
                        'xsd:string'
                    ]
                ]
            ]
        );

        sleep(1);

        if ($password !== null && trim($password) !== '') {
            $results['password_task'] = $this->request(
                'POST',
                '/devices/' . rawurlencode($deviceId) . '/tasks',
                [
                    'name' => 'setParameterValues',
                    'parameterValues' => [
                        [
                            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
                            $password,
                            'xsd:string'
                        ]
                    ]
                ]
            );

            sleep(1);
        }

        $optionalTasks = [];

        if ($enable !== null) {
            $optionalTasks[] = [
                'key' => 'enable_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable',
                'value' => $enable,
                'type' => 'xsd:boolean',
            ];
        }

        if ($radioEnabled !== null) {
            $optionalTasks[] = [
                'key' => 'radio_enabled_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.RadioEnabled',
                'value' => $radioEnabled,
                'type' => 'xsd:boolean',
            ];
        }

        if ($hideSsid !== null) {
            $optionalTasks[] = [
                'key' => 'hide_ssid_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSIDAdvertisementEnabled',
                'value' => !$hideSsid,
                'type' => 'xsd:boolean',
            ];
        }

        if ($autoChannel !== null) {
            $optionalTasks[] = [
                'key' => 'auto_channel_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.AutoChannelEnable',
                'value' => $autoChannel,
                'type' => 'xsd:boolean',
            ];
        }

        if ($channel !== null && trim($channel) !== '') {
            $optionalTasks[] = [
                'key' => 'channel_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel',
                'value' => (int)$channel,
                'type' => 'xsd:unsignedInt',
            ];
        }

        if ($txPower !== null && trim($txPower) !== '') {
            $optionalTasks[] = [
                'key' => 'tx_power_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TransmitPower',
                'value' => (int)$txPower,
                'type' => 'xsd:unsignedInt',
            ];
        }

        if ($beaconType !== null && trim($beaconType) !== '') {
            $optionalTasks[] = [
                'key' => 'beacon_type_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BeaconType',
                'value' => $beaconType,
                'type' => 'xsd:string',
            ];
        }

        if ($encryption !== null && trim($encryption) !== '') {
            if ($beaconType === 'WPAand11i') {
                $optionalTasks[] = [
                    'key' => 'wpa_encryption_task',
                    'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.WPAEncryptionModes',
                    'value' => $encryption,
                    'type' => 'xsd:string',
                ];
                $optionalTasks[] = [
                    'key' => 'ieee11i_encryption_task',
                    'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.IEEE11iEncryptionModes',
                    'value' => $encryption,
                    'type' => 'xsd:string',
                ];
            } elseif ($beaconType === '11i') {
                $optionalTasks[] = [
                    'key' => 'ieee11i_encryption_task',
                    'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.IEEE11iEncryptionModes',
                    'value' => $encryption,
                    'type' => 'xsd:string',
                ];
            } elseif ($beaconType !== 'Basic') {
                $optionalTasks[] = [
                    'key' => 'wpa_encryption_task',
                    'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.WPAEncryptionModes',
                    'value' => $encryption,
                    'type' => 'xsd:string',
                ];
            }
        }

        if ($wpsEnable !== null) {
            $optionalTasks[] = [
                'key' => 'wps_enable_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.WPS.Enable',
                'value' => $wpsEnable,
                'type' => 'xsd:boolean',
            ];
        }

        if ($wmmEnable !== null) {
            $optionalTasks[] = [
                'key' => 'wmm_enable_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.WMMEnable',
                'value' => $wmmEnable,
                'type' => 'xsd:boolean',
            ];
        }

        if ($macFilterEnable !== null) {
            $optionalTasks[] = [
                'key' => 'mac_filter_enable_task',
                'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.MACAddressControlEnabled',
                'value' => $macFilterEnable,
                'type' => 'xsd:boolean',
            ];
        }

        foreach ($optionalTasks as $task) {
            $results[$task['key']] = $this->request(
                'POST',
                '/devices/' . rawurlencode($deviceId) . '/tasks',
                [
                    'name' => 'setParameterValues',
                    'parameterValues' => [
                        [
                            $task['path'],
                            $task['value'],
                            $task['type']
                        ]
                    ]
                ]
            );

            sleep(1);
        }

        $results['refresh_task'] = $this->request(
            'POST',
            '/devices/' . rawurlencode($deviceId) . '/tasks',
            [
                'name' => 'refreshObject',
                'objectName' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1'
            ]
        );

        return [
            'ok' => true,
            'message' => 'WiFi configuration pushed.',
            'tasks' => $results,
        ];
    }

    public function findDeviceBySerial(string $serial): ?array
    {
        $serial = strtoupper(trim($serial));

        if ($serial === '') {
            return null;
        }

        $devices = $this->getDevices();
        $normalized = $this->normalizeDevices($devices);

        foreach ($normalized as $device) {
            $deviceSerial = strtoupper(trim((string)($device['serial_number'] ?? '')));
            if ($deviceSerial !== '' && $deviceSerial === $serial) {
                return $device;
            }
        }

        return null;
    }

    public function createWanInterface(array $payload): array
    {
        $deviceId = trim((string)($payload['deviceId'] ?? ''));
        $name = trim((string)($payload['name'] ?? ''));
        $type = strtoupper(trim((string)($payload['type'] ?? 'DHCP')));
        $role = strtoupper(trim((string)($payload['role'] ?? 'OTHER')));
        $vlanId = trim((string)($payload['vlan_id'] ?? ''));
        $serviceList = trim((string)($payload['service_list'] ?? $role));
        $enabled = array_key_exists('enabled', $payload) ? (bool)$payload['enabled'] : true;
        $ipAddress = trim((string)($payload['ip_address'] ?? ''));
        $subnetMask = trim((string)($payload['subnet_mask'] ?? ''));
        $gateway = trim((string)($payload['gateway'] ?? ''));
        $dns = trim((string)($payload['dns'] ?? ''));

        $this->assertEditableWanPayload($type, $role);

        if ($deviceId === '' || $name === '' || $vlanId === '') {
            throw new \RuntimeException('Device ID, WAN name, and VLAN ID are required.');
        }

        $taskPayload = [
            'name' => 'addObject',
            'objectName' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.'
        ];

        $result = $this->request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request', $taskPayload);

        return [
            'task' => 'createWanInterface',
            'acs_response' => $result,
            'requested' => [
                'name' => $name,
                'type' => $type,
                'role' => $role,
                'vlan_id' => $vlanId,
                'service_list' => $serviceList,
                'enabled' => $enabled,
                'ip_address' => $ipAddress,
                'subnet_mask' => $subnetMask,
                'gateway' => $gateway,
                'dns' => $dns,
            ]
        ];
    }

    public function updateWanInterface(array $payload): array
    {
        $deviceId = trim((string)($payload['deviceId'] ?? ''));
        $originalName = trim((string)($payload['original_name'] ?? ''));
        $name = trim((string)($payload['name'] ?? ''));
        $type = strtoupper(trim((string)($payload['type'] ?? 'DHCP')));
        $role = strtoupper(trim((string)($payload['role'] ?? 'OTHER')));
        $vlanId = trim((string)($payload['vlan_id'] ?? ''));
        $serviceList = trim((string)($payload['service_list'] ?? $role));
        $enabled = array_key_exists('enabled', $payload) ? (bool)$payload['enabled'] : true;
        $ipAddress = trim((string)($payload['ip_address'] ?? ''));
        $subnetMask = trim((string)($payload['subnet_mask'] ?? ''));
        $gateway = trim((string)($payload['gateway'] ?? ''));
        $dns = trim((string)($payload['dns'] ?? ''));

        $this->assertEditableWanPayload($type, $role);

        if ($deviceId === '' || $originalName === '' || $name === '' || $vlanId === '') {
            throw new \RuntimeException('Device ID, original WAN name, WAN name, and VLAN ID are required.');
        }

        $sets = [
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Name', $name, 'xsd:string'],
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Enable', $enabled, 'xsd:boolean'],
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_HW_ServiceList', $serviceList, 'xsd:string'],
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_HW_VLAN', (int)$vlanId, 'xsd:unsignedInt'],
        ];

        if ($type === 'STATIC') {
            if ($ipAddress === '' || $subnetMask === '' || $gateway === '') {
                throw new \RuntimeException('Static WAN requires IP address, subnet mask, and gateway.');
            }

            $sets[] = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.AddressingType', 'Static', 'xsd:string'];
            $sets[] = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress', $ipAddress, 'xsd:string'];
            $sets[] = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.SubnetMask', $subnetMask, 'xsd:string'];
            $sets[] = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DefaultGateway', $gateway, 'xsd:string'];
            if ($dns !== '') {
                $sets[] = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DNSServers', $dns, 'xsd:string'];
            }
        } else {
            $sets[] = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.AddressingType', 'DHCP', 'xsd:string'];
        }

        $result = $this->request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request', [
            'name' => 'setParameterValues',
            'parameterValues' => $sets
        ]);

        return [
            'task' => 'updateWanInterface',
            'acs_response' => $result,
            'requested' => [
                'original_name' => $originalName,
                'name' => $name,
                'type' => $type,
                'role' => $role,
                'vlan_id' => $vlanId,
                'service_list' => $serviceList,
                'enabled' => $enabled,
                'ip_address' => $ipAddress,
                'subnet_mask' => $subnetMask,
                'gateway' => $gateway,
                'dns' => $dns,
            ]
        ];
    }

    public function deleteWanInterface(array $payload): array
    {
        $deviceId = trim((string)($payload['deviceId'] ?? ''));
        $name = trim((string)($payload['name'] ?? ''));
        $type = strtoupper(trim((string)($payload['type'] ?? 'DHCP')));
        $role = strtoupper(trim((string)($payload['role'] ?? 'OTHER')));

        $this->assertEditableWanPayload($type, $role);

        if ($deviceId === '' || $name === '') {
            throw new \RuntimeException('Device ID and WAN name are required.');
        }

        $result = $this->request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks?connection_request', [
            'name' => 'setParameterValues',
            'parameterValues' => [
                ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Enable', false, 'xsd:boolean']
            ]
        ]);

        return [
            'task' => 'deleteWanInterface',
            'acs_response' => $result,
            'requested' => [
                'name' => $name,
                'type' => $type,
                'role' => $role,
                'mode' => 'disable-placeholder'
            ]
        ];
    }

    public function pingDevice(string $deviceId): array
    {
        $device = $this->getDevice($deviceId);

        if (!$device) {
            throw new \RuntimeException('Device not found.');
        }

        $connectionRequestUrl = $this->firstValue($device, [
            ['InternetGatewayDevice', 'ManagementServer', 'ConnectionRequestURL', '_value'],
            ['Device', 'ManagementServer', 'ConnectionRequestURL', '_value'],
        ]);

        if (!$connectionRequestUrl) {
            throw new \RuntimeException('Connection Request URL not found.');
        }

        $parsed = @parse_url((string)$connectionRequestUrl);

        if (!is_array($parsed) || empty($parsed['host'])) {
            throw new \RuntimeException('Invalid Connection Request URL.');
        }

        $host = (string)$parsed['host'];
        $port = isset($parsed['port']) ? (int)$parsed['port'] : 7547;
        $scheme = isset($parsed['scheme']) ? (string)$parsed['scheme'] : 'http';

        $probeUrl = $scheme . '://' . $host . ':' . $port . '/';

        $probe = $this->probeHttpReachability($probeUrl);

        return [
            'host' => $host,
            'port' => $port,
            'url' => (string)$connectionRequestUrl,
            'reachable' => $probe['reachable'],
            'ping_ms' => $probe['ping_ms'],
            'http_code' => $probe['http_code'],
            'status_text' => $probe['reachable'] ? 'ONLINE' : 'UNREACHABLE',
        ];
    }

    public function extractWifiClients(array $device): array
    {
        $clients = [];

        $paths = [
            ['InternetGatewayDevice', 'LANDevice', '1', 'Hosts', 'Host'],
            ['Device', 'Hosts', 'Host'],
        ];

        foreach ($paths as $path) {
            $hosts = $this->dig($device, $path);
            if (!is_array($hosts)) {
                continue;
            }

            foreach ($hosts as $key => $host) {
                if (!is_array($host) || !ctype_digit((string)$key)) {
                    continue;
                }

                $clients[] = [
                    'host_name' => $this->valueFromNode($host['HostName'] ?? null) ?: '-',
                    'ip_address' => $this->valueFromNode($host['IPAddress'] ?? null) ?: '-',
                    'mac_address' => $this->valueFromNode($host['MACAddress'] ?? null) ?: '-',
                    'interface_type' => $this->valueFromNode($host['InterfaceType'] ?? null) ?: '-',
                    'active' => $this->valueFromNode($host['Active'] ?? null),
                    'lease_time_remaining' => $this->valueFromNode($host['LeaseTimeRemaining'] ?? null),
                ];
            }

            if (!empty($clients)) {
                break;
            }
        }

        return $clients;
    }

    private function probeHttpReachability(string $url): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $start = microtime(true);
        curl_exec($ch);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);

        $reachableCodes = [200, 204, 401, 403, 405, 503];

        return [
            'reachable' => in_array($httpCode, $reachableCodes, true),
            'ping_ms' => $elapsedMs > 0 ? $elapsedMs : null,
            'http_code' => $httpCode,
            'error' => $curlErr ?: null,
        ];
    }

    private function extractPrimarySsid(array $device): ?string
    {
        return $this->firstValue($device, [
            ['InternetGatewayDevice', 'LANDevice', '1', 'WLANConfiguration', '1', 'SSID', '_value'],
            ['Device', 'WiFi', 'SSID', '1', 'SSID', '_value'],
            ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID'],
        ]);
    }

    private function extractWanFullInfo(array $device): array
    {
        $result = [
            'ip' => null,
            'status' => 'UNKNOWN',
            'mode' => 'UNKNOWN',
            'username' => null,
            'service' => null,
            'vlan' => null,
        ];

        $wanDevices = $device['InternetGatewayDevice']['WANDevice'] ?? null;

        if (!is_array($wanDevices)) {
            return $result;
        }

        $internetCandidates = [];
        $fallbackCandidates = [];

        foreach ($wanDevices as $wanDeviceKey => $wanDevice) {
            if (!is_array($wanDevice) || !ctype_digit((string)$wanDeviceKey)) {
                continue;
            }

            $connectionDevices = $wanDevice['WANConnectionDevice'] ?? null;

            if (!is_array($connectionDevices)) {
                continue;
            }

            foreach ($connectionDevices as $connDevKey => $connDev) {
                if (!is_array($connDev) || !ctype_digit((string)$connDevKey)) {
                    continue;
                }

                foreach (['WANPPPConnection', 'WANIPConnection'] as $connType) {
                    $connections = $connDev[$connType] ?? null;

                    if (!is_array($connections)) {
                        continue;
                    }

                    foreach ($connections as $connKey => $conn) {
                        if (!is_array($conn) || !ctype_digit((string)$connKey)) {
                            continue;
                        }

                        $name = strtoupper((string)($this->valueFromNode($conn['Name'] ?? null) ?: ''));

                        $service = strtoupper((string)(
                        $this->valueFromNode($conn['X_HW_SERVICELIST'] ?? null)
                            ?: $this->valueFromNode($conn['X_HW_ServiceList'] ?? null)
                            ?: $this->valueFromNode($conn['ServiceList'] ?? null)
                                ?: ''
                        ));

                        $status = strtoupper((string)(
                        $this->valueFromNode($conn['ConnectionStatus'] ?? null)
                            ?: $this->valueFromNode($conn['X_HW_ConnectionStatus'] ?? null)
                            ?: 'UNKNOWN'
                        ));

                        $ip =
                            $this->valueFromNode($conn['ExternalIPAddress'] ?? null)
                                ?: $this->valueFromNode($conn['IPAddress'] ?? null)
                                ?: $this->valueFromNode($conn['X_HW_IPAddress'] ?? null)
                                    ?: $this->valueFromNode($conn['X_HW_ExternalIPAddress'] ?? null)
                                        ?: $this->valueFromNode($conn['X_HW_EXTIPAddress'] ?? null);

                        if ($ip === '0.0.0.0' || $ip === '') {
                            $ip = null;
                        }

                        $username = $this->valueFromNode($conn['Username'] ?? null);

                        $vlan =
                            $this->valueFromNode($conn['X_HW_VLAN'] ?? null)
                                ?: $this->valueFromNode($conn['X_HW_VLANID'] ?? null)
                                ?: $this->valueFromNode($conn['VLANIDMark'] ?? null);

                        $mode = $connType === 'WANPPPConnection' ? 'PPPOE' : 'DHCP';

                        $isManagement =
                            str_contains($service, 'TR069') ||
                            str_contains($service, 'VOIP') ||
                            str_contains($name, 'TR069') ||
                            str_contains($name, 'DHCP_WAN') ||
                            str_contains($name, 'OLT_C_TR069') ||
                            str_contains($name, 'OLT_C_1_TR069') ||
                            str_contains($name, 'OLT_C_3_TR069');

                        $isInternet =
                            str_contains($service, 'INTERNET') ||
                            str_contains($name, 'INTERNET') ||
                            $connType === 'WANPPPConnection';

                        $row = [
                            'ip' => $ip,
                            'status' => $status ?: 'UNKNOWN',
                            'mode' => $mode,
                            'username' => $username,
                            'service' => $service ?: ($isInternet ? 'INTERNET' : 'OTHER'),
                            'vlan' => $vlan,
                            '_score' => 0,
                        ];

                        if ($connType === 'WANPPPConnection') {
                            $row['_score'] += 1000;
                        }

                        if ($isInternet) {
                            $row['_score'] += 500;
                        }

                        if (in_array($status, ['CONNECTED', 'UP'], true)) {
                            $row['_score'] += 300;
                        }

                        if ($ip) {
                            $row['_score'] += 200;
                        }

                        if ($username) {
                            $row['_score'] += 100;
                        }

                        if (!$isManagement && $isInternet) {
                            $internetCandidates[] = $row;
                        } elseif (!$isManagement) {
                            $fallbackCandidates[] = $row;
                        }
                    }
                }
            }
        }

        $candidates = $internetCandidates ?: $fallbackCandidates;

        if (!$candidates) {
            return $result;
        }

        usort($candidates, function (array $a, array $b) {
            return (int)$b['_score'] <=> (int)$a['_score'];
        });

        unset($candidates[0]['_score']);

        return $candidates[0];
    }

    private function extractManagementIp(array $device): ?string
    {
        $url = $this->firstValue($device, [
            ['InternetGatewayDevice', 'ManagementServer', 'ConnectionRequestURL', '_value'],
            ['Device', 'ManagementServer', 'ConnectionRequestURL', '_value'],
            ['VirtualParameters', 'management_ip', '_value'],
        ]);

        if (!$url) {
            return null;
        }

        $parts = @parse_url((string)$url);

        if (is_array($parts) && !empty($parts['host'])) {
            return (string)$parts['host'];
        }

        return (string)$url;
    }

    private function extractUptimeSeconds(array $device): ?int
    {
        $raw = $this->firstValue($device, [
            ['InternetGatewayDevice', 'DeviceInfo', 'UpTime', '_value'],
            ['Device', 'DeviceInfo', 'UpTime', '_value'],
            ['VirtualParameters', 'uptime', '_value'],
            ['InternetGatewayDevice.DeviceInfo.UpTime'],
            ['Device.DeviceInfo.UpTime'],
        ]);

        if ($raw !== null && $raw !== '' && is_numeric($raw)) {
            return (int)$raw;
        }

        $lastBoot = $this->firstValue($device, [
            ['_lastBoot'],
        ]);

        $lastInform = $this->firstValue($device, [
            ['_lastInform'],
        ]);

        if ($lastBoot && $lastInform) {
            $bootTs = strtotime((string)$lastBoot);
            $informTs = strtotime((string)$lastInform);

            if ($bootTs !== false && $informTs !== false && $informTs >= $bootTs) {
                return $informTs - $bootTs;
            }
        }

        return null;
    }

    private function extractLastSeen(array $device): ?string
    {
        $lastInform = $this->firstValue($device, [
            ['_lastInform'],
            ['Events', 'Inform', '_lastInform'],
            ['InternetGatewayDevice', 'DeviceInfo', 'X_ZTE-COM_LastInformTime', '_value'],
        ]);

        if (!$lastInform) {
            return null;
        }

        $ts = strtotime((string)$lastInform);
        if ($ts === false) {
            return (string)$lastInform;
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }

    private function extractSerialNumber(array $device): ?string
    {
        $serial = $this->firstValue($device, [
            ['_deviceId', '_SerialNumber'],
            ['_deviceId', 'SerialNumber'],
            ['DeviceID', 'SerialNumber', '_value'],
            ['InternetGatewayDevice', 'DeviceInfo', 'SerialNumber', '_value'],
            ['Device', 'DeviceInfo', 'SerialNumber', '_value'],
            ['VirtualParameters', 'serialNumber', '_value'],
            ['DeviceID.SerialNumber'],
            ['InternetGatewayDevice.DeviceInfo.SerialNumber'],
            ['Device.DeviceInfo.SerialNumber'],
        ]);

        $serial = $this->normalizeSerial($serial);

        if ($serial !== null) {
            return $serial;
        }

        $deviceId = $this->firstValue($device, [
            ['DeviceID', 'ID', '_value'],
            ['DeviceID.ID'],
            ['_id'],
        ]);

        return $this->extractSerialFromDeviceId((string)$deviceId);
    }

    private function extractVendor(array $device): ?string
    {
        return $this->firstValue($device, [
            ['_deviceId', '_Manufacturer'],
            ['_deviceId', 'Manufacturer'],
            ['DeviceID', 'Manufacturer', '_value'],
            ['InternetGatewayDevice', 'DeviceInfo', 'Manufacturer', '_value'],
            ['Device', 'DeviceInfo', 'Manufacturer', '_value'],
            ['VirtualParameters', 'manufacturer', '_value'],
            ['DeviceID.Manufacturer'],
            ['InternetGatewayDevice.DeviceInfo.Manufacturer'],
        ]);
    }

    private function extractModel(array $device): ?string
    {
        return $this->firstValue($device, [
            ['_deviceId', '_ProductClass'],
            ['_deviceId', 'ProductClass'],
            ['DeviceID', 'ProductClass', '_value'],
            ['InternetGatewayDevice', 'DeviceInfo', 'ProductClass', '_value'],
            ['InternetGatewayDevice', 'DeviceInfo', 'ModelName', '_value'],
            ['Device', 'DeviceInfo', 'ProductClass', '_value'],
            ['Device', 'DeviceInfo', 'ModelName', '_value'],
            ['VirtualParameters', 'model', '_value'],
            ['DeviceID.ProductClass'],
            ['InternetGatewayDevice.DeviceInfo.ProductClass'],
            ['InternetGatewayDevice.DeviceInfo.ModelName'],
        ]);
    }

    private function extractFirmware(array $device): ?string
    {
        return $this->firstValue($device, [
            ['InternetGatewayDevice', 'DeviceInfo', 'SoftwareVersion', '_value'],
            ['Device', 'DeviceInfo', 'SoftwareVersion', '_value'],
            ['VirtualParameters', 'firmwareVersion', '_value'],
            ['InternetGatewayDevice.DeviceInfo.SoftwareVersion'],
            ['Device.DeviceInfo.SoftwareVersion'],
        ]);
    }

    private function extractSerialFromDeviceId(string $deviceId): ?string
    {
        $deviceId = trim($deviceId);
        if ($deviceId === '') {
            return null;
        }

        $parts = explode('-', $deviceId);
        $last = end($parts);

        return $this->normalizeSerial($last);
    }

    private function normalizeSerial($serial): ?string
    {
        $serial = strtoupper(trim((string)$serial));
        return $serial !== '' ? $serial : null;
    }

    private function findPppoeConnectionPath(array $device): ?string
    {
        $wanDevices = $device['InternetGatewayDevice']['WANDevice'] ?? null;

        if (!is_array($wanDevices)) {
            return null;
        }

        $candidates = [];

        foreach ($wanDevices as $wanDeviceKey => $wanDevice) {
            if (!is_array($wanDevice) || !ctype_digit((string)$wanDeviceKey)) {
                continue;
            }

            $connectionDevices = $wanDevice['WANConnectionDevice'] ?? null;
            if (!is_array($connectionDevices)) {
                continue;
            }

            foreach ($connectionDevices as $connDevKey => $connDev) {
                if (!is_array($connDev) || !ctype_digit((string)$connDevKey)) {
                    continue;
                }

                $pppConnections = $connDev['WANPPPConnection'] ?? null;
                if (!is_array($pppConnections)) {
                    continue;
                }

                $hasInstantiatedChild = false;

                foreach ($pppConnections as $pppKey => $pppConn) {
                    if (!ctype_digit((string)$pppKey) || !is_array($pppConn)) {
                        continue;
                    }

                    $hasInstantiatedChild = true;

                    $service = strtoupper((string)(
                    $this->valueFromNode($pppConn['X_HW_ServiceList'] ?? null)
                        ?: $this->valueFromNode($pppConn['ServiceList'] ?? null)
                        ?: ''
                    ));

                    $name = strtoupper((string)($this->valueFromNode($pppConn['Name'] ?? null) ?: ''));

                    $score = ((int)$connDevKey * 100) + (int)$pppKey;

                    if (
                        str_contains($service, 'INTERNET') ||
                        str_contains($name, 'INTERNET')
                    ) {
                        $score += 10000;
                    }

                    $candidates[] = [
                        'score' => $score,
                        'path' => sprintf(
                            'InternetGatewayDevice.WANDevice.%s.WANConnectionDevice.%s.WANPPPConnection.%s',
                            $wanDeviceKey,
                            $connDevKey,
                            $pppKey
                        ),
                    ];
                }

                if (!$hasInstantiatedChild) {
                    $candidates[] = [
                        'score' => ((int)$connDevKey * 100) + 1,
                        'path' => sprintf(
                            'InternetGatewayDevice.WANDevice.%s.WANConnectionDevice.%s.WANPPPConnection.1',
                            $wanDeviceKey,
                            $connDevKey
                        ),
                    ];
                }
            }
        }

        if (!$candidates) {
            return null;
        }

        usort($candidates, function (array $a, array $b) {
            return $b['score'] <=> $a['score'];
        });

        return $candidates[0]['path'];
    }

    private function findInventoryBySerial(string $serial): ?array
    {
        if (!$this->pdo || $serial === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM ont_devices
                WHERE UPPER(TRIM(serial_number)) = UPPER(TRIM(:serial))
                LIMIT 1
            ");
            $stmt->execute(['serial' => $serial]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function formatUptimeDays(?int $seconds): string
    {
        if (!$seconds || $seconds < 1) {
            return '-';
        }

        $days = floor($seconds / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '');
    }

    private function formatDuration(?int $seconds): string
    {
        if (!$seconds || $seconds < 1) {
            return '-';
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $mins = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = $days . 'd';
        if ($hours > 0) $parts[] = $hours . 'h';
        if ($mins > 0) $parts[] = $mins . 'm';
        if ($secs > 0 && $days === 0) $parts[] = $secs . 's';

        return $parts ? implode(' ', $parts) : '0s';
    }

    private function firstValue(array $source, array $paths)
    {
        foreach ($paths as $path) {
            $value = $this->dig($source, $path);

            if (is_array($value) && array_key_exists('_value', $value)) {
                $value = $value['_value'];
            }

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function valueFromNode($node)
    {
        if (is_array($node) && array_key_exists('_value', $node)) {
            return $node['_value'];
        }

        return is_scalar($node) ? $node : null;
    }

    private function dig(array $source, array $path)
    {
        if (count($path) === 1 && str_contains((string)$path[0], '.')) {
            $flatKey = (string)$path[0];
            if (array_key_exists($flatKey, $source)) {
                return $source[$flatKey];
            }
        }

        $current = $source;

        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists((string)$segment, $current)) {
                return null;
            }

            $current = $current[(string)$segment];
        }

        return $current;
    }

    private function isRecentTimestamp(?string $lastSeen, int $seconds = 1800): bool
    {
        if (!$lastSeen) {
            return false;
        }

        try {
            $dt = new \DateTime($lastSeen, new \DateTimeZone('UTC'));
            $ts = $dt->getTimestamp();
        } catch (\Throwable $e) {
            return false;
        }

        return (time() - $ts) <= $seconds;
    }

    private function assertEditableWanPayload(string $type, string $role): void
    {
        $safeType = strtoupper(trim($type));
        $safeRole = strtoupper(trim($role));

        if ($safeType === 'PPPOE' || $safeRole === 'INTERNET') {
            throw new \RuntimeException('Subscriber PPPoE WAN must be managed from Service Provisioning.');
        }
    }

    private function request(string $method, string $path, ?array $payload = null)
    {
        $url = $this->acsBaseUrl . $path;

        $ch = curl_init($url);

        $headers = ['Accept: application/json'];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $body = json_encode($payload ?? [], JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('ACS request failed: ' . $curlErr);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $message = is_array($decoded) && isset($decoded['message'])
                ? (string)$decoded['message']
                : ('ACS HTTP error ' . $httpCode);

            throw new \RuntimeException($message);
        }

        return $decoded;
    }
}