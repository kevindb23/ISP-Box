<?php

namespace App\Modules\Api\v1\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use Throwable;

final class MonitoringRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function systemInfo(): array
    {
        $stmt = $this->db->query(
            "SELECT config_key, config_value FROM system_config "
            . "WHERE config_key IN ('instance_id','system_name','company_name','timezone')"
        );
        $values = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $values[(string)$row['config_key']] = (string)($row['config_value'] ?? '');
        }
        if (empty($values['company_name'])) {
            $branding = $this->db->query('SELECT company_name FROM branding ORDER BY id ASC LIMIT 1');
            $values['company_name'] = (string)($branding->fetchColumn() ?: '');
        }
        return $values;
    }

    public function subscriberSummary(): array
    {
        $stmt = $this->db->query("SELECT COUNT(DISTINCT s.id) total,
            COUNT(DISTINCT CASE WHEN UPPER(ss.status)='ACTIVE' THEN s.id END) active,
            COUNT(DISTINCT CASE WHEN UPPER(ss.status)='SUSPENDED' THEN s.id END) suspended,
            COUNT(DISTINCT CASE WHEN ss.expires_at IS NOT NULL AND ss.expires_at < NOW() THEN s.id END) expired
            FROM subscribers s JOIN subscriber_services ss ON ss.subscriber_id=s.id WHERE s.deleted_at IS NULL");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function sessionSummary(): array
    {
        $stmt = $this->db->query("SELECT COUNT(*) total, SUM(CASE WHEN session_stop IS NULL THEN 1 ELSE 0 END) online
            FROM radius_accounting");
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $row['offline'] = max(0, (int)($this->subscriberSummary()['active'] ?? 0) - (int)($row['online'] ?? 0));
        return $row;
    }

    public function provisioningSummary(): array
    {
        $stmt = $this->db->query("SELECT COUNT(*) total,
            SUM(job_status IN ('DRAFT','VALIDATING','READY')) queued,
            SUM(job_status='PROVISIONING') provisioning,
            SUM(job_status='VERIFYING') verifying,
            SUM(job_status='SUCCESS') successful,
            SUM(job_status='FAILED') failed FROM service_provisioning_jobs");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function ontSummary(): array
    {
        $stmt = $this->db->query("SELECT COUNT(*) total,
            SUM(UPPER(status)='ASSIGNED') assigned,
            SUM(UPPER(status)='OFFLINE') offline,
            SUM(UPPER(status)='UNASSIGNED') unassigned FROM ont_devices");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function oltSummary(): array
    {
        $stmt = $this->db->query("SELECT COUNT(*) total FROM olt_devices");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function billingSummary(): array
    {
        $stmt = $this->db->query("SELECT COUNT(*) invoice_count,
            SUM(CASE WHEN UPPER(status)='PAID' THEN 1 ELSE 0 END) paid_count,
            SUM(CASE WHEN UPPER(status) IN ('UNPAID','PARTIAL','OVERDUE') THEN 1 ELSE 0 END) outstanding_count,
            COALESCE(SUM(CASE WHEN UPPER(status) IN ('UNPAID','PARTIAL','OVERDUE') THEN balance_amount ELSE 0 END),0) outstanding_balance
            FROM invoices");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Privacy-safe infrastructure snapshot. No subscriber or commercial rows are queried. */
    public function infrastructureSnapshot(): array
    {
        $started = microtime(true);
        $settings = $this->monitoringSettings();
        $identity = $this->monitoringIdentity();
        $system = $this->linuxHealth(
            (int) ($settings['monitoring_memory_warning_percent'] ?? 90),
            (int) ($settings['monitoring_disk_warning_percent'] ?? 85),
            (int) ($settings['monitoring_disk_critical_percent'] ?? 95),
        );
        $services = [
            'nexusbox' => ['status' => 'HEALTHY', 'php_version' => PHP_VERSION, 'latency_ms' => 0],
            'mariadb' => $this->databaseHealth(),
            'bng' => $this->configuredTcpHealth('bng_settings', 'host', 'port', 'enabled', 22),
            'radius' => $this->configuredTcpHealth('radius_settings', 'host', null, 'is_active', 3306),
            'acs' => $this->acsHealth(),
            'olt' => $this->oltHealth(),
            'cgnat' => $this->configuredTcpHealth('bng_settings', 'host', 'port', 'enabled', 22),
            'frr' => $this->configuredTcpHealth('bng_settings', 'host', 'port', 'enabled', 22),
            'core_router' => $this->configuredTcpHealth('router_core_settings', 'host', 'port', 'enabled', 22),
        ];
        $statuses = array_column($services, 'status');
        $overall = (($system['status'] ?? 'UNKNOWN') === 'CRITICAL' || in_array('CRITICAL', $statuses, true)) ? 'CRITICAL'
            : (($system['status'] ?? 'UNKNOWN') === 'DEGRADED' || in_array('DEGRADED', $statuses, true) ? 'DEGRADED' : 'HEALTHY');

        return [
            'schema_version' => '1.0',
            'instance' => $identity,
            'collected_at' => date(DATE_ATOM),
            'stale_after_seconds' => max(90, min(10800, (int) ($settings['monitoring_heartbeat_seconds'] ?? 60) * 3)),
            'overall_status' => $overall,
            'system' => $system,
            'services' => $services,
            'collection_duration_ms' => (int)round((microtime(true) - $started) * 1000),
            'data_classification' => ['profile' => 'INFRASTRUCTURE_ONLY', 'contains_sensitive_data' => false],
        ];
    }

    private function monitoringIdentity(): array
    {
        $keys = ['instance_id','monitoring_client_id','monitoring_client_name','monitoring_instance_name',
            'monitoring_location','monitoring_environment','system_name','timezone','maintenance_mode'];
        $quoted = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare("SELECT config_key,config_value FROM system_config WHERE config_key IN ({$quoted})");
        $stmt->execute($keys); $values = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $values[(string)$row['config_key']] = (string)$row['config_value'];
        $value = static fn(string $key): ?string => trim((string)($values[$key] ?? '')) !== '' ? trim((string)$values[$key]) : null;
        return [
            'client_id' => $value('monitoring_client_id'),
            'client_name' => $value('monitoring_client_name'),
            'instance_id' => $values['instance_id'] ?? null,
            'instance_name' => $value('monitoring_instance_name') ?? $value('system_name') ?? 'NexusBox',
            'location' => $value('monitoring_location'),
            'environment' => $values['monitoring_environment'] ?? 'PRODUCTION',
            'timezone' => $values['timezone'] ?? 'Asia/Manila',
            'maintenance_mode' => ($values['maintenance_mode'] ?? '0') === '1',
            'application_version' => $this->applicationVersion(),
        ];
    }

    private function applicationVersion(): string
    {
        $environment = trim((string) (getenv('APP_VERSION') ?: ''));
        if ($environment !== '') return $environment;

        $versionFile = BASE_PATH . '/VERSION';
        $release = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
        return $release !== '' ? $release : 'development';
    }

    private function linuxHealth(int $memoryWarning, int $diskWarning, int $diskCritical): array
    {
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0,0,0]) : [0,0,0];
        $memory = @file_get_contents('/proc/meminfo') ?: ''; $m = [];
        preg_match_all('/^(MemTotal|MemAvailable|SwapTotal|SwapFree):\s+(\d+)/m', $memory, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) $m[$match[1]] = (int)$match[2] * 1024;
        $total = $m['MemTotal'] ?? 0; $available = $m['MemAvailable'] ?? 0;
        $memoryPercent = $total > 0 ? round((1 - ($available / $total)) * 100, 1) : null;
        $diskTotal = @disk_total_space(BASE_PATH) ?: 0; $diskFree = @disk_free_space(BASE_PATH) ?: 0;
        $diskPercent = $diskTotal > 0 ? round((1 - ($diskFree / $diskTotal)) * 100, 1) : null;
        $uptime = (int)floor((float)trim((string)(@file_get_contents('/proc/uptime') ?: '0')));
        $critical = $diskPercent !== null && $diskPercent >= max(2, min(100, $diskCritical));
        $degraded = ($memoryPercent !== null && $memoryPercent >= max(1, min(100, $memoryWarning))) || ($diskPercent !== null && $diskPercent >= max(1, min(99, $diskWarning)));
        return ['status' => $critical ? 'CRITICAL' : ($degraded ? 'DEGRADED' : 'HEALTHY'), 'hostname' => gethostname() ?: null,
            'kernel' => php_uname('r'), 'uptime_seconds' => $uptime, 'load_1m' => round((float)($load[0] ?? 0), 2),
            'load_5m' => round((float)($load[1] ?? 0), 2), 'load_15m' => round((float)($load[2] ?? 0), 2),
            'memory_percent' => $memoryPercent, 'disk_percent' => $diskPercent];
    }

    private function databaseHealth(): array
    {
        $start = microtime(true);
        try { $this->db->query('SELECT 1')->fetchColumn(); return ['status'=>'HEALTHY','latency_ms'=>(int)round((microtime(true)-$start)*1000),'server_version'=>(string)$this->db->getAttribute(PDO::ATTR_SERVER_VERSION)]; }
        catch (Throwable $e) { return ['status'=>'CRITICAL','latency_ms'=>null,'error_code'=>'DATABASE_UNAVAILABLE']; }
    }

    private function configuredTcpHealth(string $table, string $hostColumn, ?string $portColumn, string $enabledColumn, int $defaultPort): array
    {
        try {
            $portSelect = $portColumn ? ", {$portColumn} AS port" : '';
            $row = $this->db->query("SELECT {$hostColumn} AS host{$portSelect} FROM {$table} WHERE {$enabledColumn}=1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$row || trim((string)($row['host'] ?? '')) === '') return ['status'=>'DISABLED','configured'=>false];
            return $this->tcpProbe((string)$row['host'], (int)($row['port'] ?? $defaultPort)) + ['configured'=>true];
        } catch (Throwable $e) { return ['status'=>'UNKNOWN','configured'=>false,'error_code'=>'CONFIGURATION_UNAVAILABLE']; }
    }

    private function acsHealth(): array
    {
        $url = getenv('ACS_URL') ?: ($_ENV['ACS_URL'] ?? 'http://10.0.10.156:7557');
        $parts = parse_url((string)$url); $host = (string)($parts['host'] ?? '');
        if ($host === '') return ['status'=>'DISABLED','configured'=>false];
        $port = (int)($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80));
        return $this->tcpProbe($host, $port) + ['configured'=>true];
    }

    private function oltHealth(): array
    {
        try { $rows = $this->db->query("SELECT ip_address FROM olt_devices WHERE ip_address IS NOT NULL AND ip_address<>'' ORDER BY id LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return ['status'=>'UNKNOWN','configured_count'=>0,'reachable_count'=>0]; }
        if (!$rows) return ['status'=>'DISABLED','configured_count'=>0,'reachable_count'=>0];
        $reachable = 0; foreach ($rows as $row) if (($this->tcpProbe((string)$row['ip_address'], 22)['status'] ?? '') === 'HEALTHY') $reachable++;
        return ['status'=>$reachable===count($rows)?'HEALTHY':($reachable>0?'DEGRADED':'CRITICAL'),'configured_count'=>count($rows),'reachable_count'=>$reachable];
    }

    private function tcpProbe(string $host, int $port): array
    {
        $started = microtime(true); $errno = 0; $error = '';
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $error, 0.35, STREAM_CLIENT_CONNECT);
        $latency = (int)round((microtime(true)-$started)*1000);
        if (is_resource($socket)) { fclose($socket); return ['status'=>'HEALTHY','latency_ms'=>$latency]; }
        return ['status'=>'CRITICAL','latency_ms'=>$latency,'error_code'=>'ENDPOINT_UNREACHABLE'];
    }

    public function storeInfrastructureSnapshot(array $snapshot, int $retentionDays = 30): int
    {
        $instance=(string)($snapshot['instance']['instance_id']??'');if($instance==='')throw new \RuntimeException('Instance ID is required.');
        $status=(string)($snapshot['overall_status']??'UNKNOWN');$collected=date('Y-m-d H:i:s',strtotime((string)($snapshot['collected_at']??'now'))?:time());
        $this->db->beginTransaction();
        try{
            $last=$this->db->prepare('SELECT overall_status FROM infrastructure_monitoring_snapshots WHERE instance_id=:instance ORDER BY id DESC LIMIT 1 FOR UPDATE');$last->execute(['instance'=>$instance]);$previous=$last->fetchColumn();
            $insert=$this->db->prepare('INSERT INTO infrastructure_monitoring_snapshots(instance_id,overall_status,payload_json,collected_at,collection_duration_ms) VALUES(:instance,:status,:payload,:collected,:duration)');$insert->execute(['instance'=>$instance,'status'=>$status,'payload'=>json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'collected'=>$collected,'duration'=>(int)($snapshot['collection_duration_ms']??0)]);$id=(int)$this->db->lastInsertId();
            if($previous!==$status){if($status==='HEALTHY'){$close=$this->db->prepare('UPDATE infrastructure_monitoring_events SET recovered_at=:at WHERE instance_id=:instance AND recovered_at IS NULL');$close->execute(['at'=>$collected,'instance'=>$instance]);}else{$event=$this->db->prepare('INSERT INTO infrastructure_monitoring_events(instance_id,previous_status,current_status,started_at) VALUES(:instance,:previous,:current,:started)');$event->execute(['instance'=>$instance,'previous'=>$previous!==false?$previous:null,'current'=>$status,'started'=>$collected]);}}
            $cutoff=date('Y-m-d H:i:s',time()-max(1,min(365,$retentionDays))*86400);$cleanup=$this->db->prepare('DELETE FROM infrastructure_monitoring_snapshots WHERE collected_at<:cutoff');$cleanup->execute(['cutoff'=>$cutoff]);$this->db->commit();return$id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }

    public function infrastructureHistory(int $hours = 24): array
    {
        $since=date('Y-m-d H:i:s',time()-max(1,min(720,$hours))*3600);
        $stmt=$this->db->prepare('SELECT id,instance_id,overall_status,collected_at,collection_duration_ms FROM infrastructure_monitoring_snapshots WHERE collected_at>=:since ORDER BY collected_at DESC LIMIT 1000');$stmt->execute(['since'=>$since]);
        $events=$this->db->prepare('SELECT id,instance_id,previous_status,current_status,started_at,recovered_at FROM infrastructure_monitoring_events WHERE started_at>=:since OR recovered_at IS NULL ORDER BY started_at DESC LIMIT 200');$events->execute(['since'=>$since]);
        return ['hours'=>$hours,'snapshots'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[],'events'=>$events->fetchAll(PDO::FETCH_ASSOC)?:[]];
    }

    public function monitoringSettings(): array
    {
        $keys=['monitoring_hq_enabled','monitoring_hq_url','monitoring_allow_insecure_http','monitoring_verify_tls','monitoring_retention_days','monitoring_disk_warning_percent','monitoring_disk_critical_percent','monitoring_memory_warning_percent','monitoring_heartbeat_seconds'];$quoted=implode(',',array_fill(0,count($keys),'?'));$stmt=$this->db->prepare("SELECT config_key,config_value FROM system_config WHERE config_key IN ({$quoted})");$stmt->execute($keys);$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[]as$row)$out[(string)$row['config_key']]=(string)$row['config_value'];return$out;
    }

    public function enqueueSnapshot(int $snapshotId,array $snapshot): void
    {
        $stmt=$this->db->prepare("INSERT IGNORE INTO infrastructure_monitoring_delivery_queue(snapshot_id,payload_hash,status,next_attempt_at) VALUES(:id,:hash,'PENDING',NOW())");$stmt->execute(['id'=>$snapshotId,'hash'=>hash('sha256',json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))]);
    }

    public function dueDeliveries(int $limit=10): array
    {
        $limit=max(1,min(50,$limit));return$this->db->query("SELECT q.id,q.snapshot_id,q.attempts,s.payload_json FROM infrastructure_monitoring_delivery_queue q JOIN infrastructure_monitoring_snapshots s ON s.id=q.snapshot_id WHERE q.status='PENDING' AND q.next_attempt_at<=NOW() ORDER BY q.id ASC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    public function markDelivery(int $id,bool $delivered,?string $errorCode=null): void
    {
        if($delivered){$stmt=$this->db->prepare("UPDATE infrastructure_monitoring_delivery_queue SET status='DELIVERED',attempts=attempts+1,delivered_at=NOW(),last_error_code=NULL WHERE id=:id");$stmt->execute(['id'=>$id]);return;}
        $stmt=$this->db->prepare("UPDATE infrastructure_monitoring_delivery_queue SET attempts=attempts+1,status=IF(attempts+1>=20,'FAILED','PENDING'),next_attempt_at=DATE_ADD(NOW(),INTERVAL LEAST(900,POW(2,LEAST(attempts+1,9))*15) SECOND),last_error_code=:error WHERE id=:id");$stmt->execute(['id'=>$id,'error'=>substr((string)$errorCode,0,64)]);
    }

    public function deliverySummary(): array
    {
        $row=$this->db->query("SELECT SUM(status='PENDING') pending,SUM(status='DELIVERED') delivered,SUM(status='FAILED') failed,MAX(delivered_at) last_delivered_at FROM infrastructure_monitoring_delivery_queue")->fetch(PDO::FETCH_ASSOC)?:[];return['pending'=>(int)($row['pending']??0),'delivered'=>(int)($row['delivered']??0),'failed'=>(int)($row['failed']??0),'last_delivered_at'=>$row['last_delivered_at']??null];
    }

    public function latestInfrastructureSnapshot(): ?array
    {
        $row = $this->db->query('SELECT id,payload_json,collected_at FROM infrastructure_monitoring_snapshots ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $payload = json_decode((string) $row['payload_json'], true);
        if (!is_array($payload)) return null;
        $payload['snapshot_id'] = (int) $row['id'];
        $payload['collected_at'] = (string) $row['collected_at'];
        return $payload;
    }

    public function infrastructureDashboardHistory(int $hours = 24, int $limit = 120): array
    {
        $hours = max(1, min(168, $hours));
        $limit = max(12, min(500, $limit));
        $since = date('Y-m-d H:i:s', time() - $hours * 3600);
        $stmt = $this->db->prepare("SELECT id,payload_json,collected_at FROM infrastructure_monitoring_snapshots WHERE collected_at>=:since ORDER BY id DESC LIMIT {$limit}");
        $stmt->execute(['since' => $since]); $trend = [];
        foreach (array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $payload = json_decode((string) $row['payload_json'], true);
            if (!is_array($payload)) continue;
            $system = is_array($payload['system'] ?? null) ? $payload['system'] : [];
            $trend[] = [
                'snapshot_id' => (int) $row['id'],
                'collected_at' => (string) $row['collected_at'],
                'overall_status' => (string) ($payload['overall_status'] ?? 'UNKNOWN'),
                'memory_percent' => isset($system['memory_percent']) ? (float) $system['memory_percent'] : null,
                'disk_percent' => isset($system['disk_percent']) ? (float) $system['disk_percent'] : null,
                'load_1m' => isset($system['load_1m']) ? (float) $system['load_1m'] : null,
            ];
        }
        $events = $this->infrastructureHistory($hours)['events'];
        return ['hours' => $hours, 'trend' => $trend, 'events' => $events];
    }

    public function retryFailedDeliveries(): int
    {
        $stmt = $this->db->prepare("UPDATE infrastructure_monitoring_delivery_queue SET status='PENDING',attempts=0,next_attempt_at=NOW(),last_error_code=NULL WHERE status='FAILED'");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
