<?php

namespace App\Modules\Api\v1\Services;

use App\Modules\Api\v1\Repositories\MonitoringRepository;

final class MonitoringDeliveryService
{
    private const SCHEMA_VERSION = '1.0';
    private const HEARTBEAT_PATH = '/api/v1/instances/heartbeat';

    public function __construct(private MonitoringRepository $repository) {}

    public function deliverDue(): array
    {
        $settings = $this->repository->monitoringSettings();
        if (($settings['monitoring_hq_enabled'] ?? '0') !== '1') {
            return ['enabled' => false, 'processed' => 0, 'delivered' => 0, 'failed' => 0];
        }

        ['base' => $base, 'token' => $token, 'allow_insecure_http' => $allowHttp, 'verify_tls' => $verifyTls] = $this->resolvedConfiguration($settings);
        if (!$this->transportAllowed($base, $allowHttp) || $token === '') {
            return ['enabled' => true, 'ready' => false, 'processed' => 0, 'delivered' => 0, 'failed' => 0, 'error_code' => 'HQ_CONFIGURATION_INCOMPLETE'];
        }

        $delivered = 0;
        $failed = 0;
        $processed = 0;
        foreach ($this->repository->dueDeliveries() as $row) {
            $processed++;
            $payload = $this->prepareOutboundPayload((string) $row['payload_json']);
            [$ok, $code] = $this->send($base . self::HEARTBEAT_PATH, $token, $payload, (int) $row['snapshot_id'], $verifyTls);
            $this->repository->markDelivery((int) $row['id'], $ok, $code);
            $ok ? $delivered++ : $failed++;
        }

        return ['enabled' => true, 'ready' => true, 'processed' => $processed, 'delivered' => $delivered, 'failed' => $failed];
    }

    public function configurationStatus(): array
    {
        $settings = $this->repository->monitoringSettings();
        ['base' => $base, 'token' => $token, 'allow_insecure_http' => $allowHttp, 'verify_tls' => $verifyTls] = $this->resolvedConfiguration($settings);
        $endpointConfigured = $this->transportAllowed($base, $allowHttp);
        $tokenConfigured = $token !== '';
        return [
            'enabled' => ($settings['monitoring_hq_enabled'] ?? '0') === '1',
            'endpoint_configured' => $endpointConfigured,
            'token_configured' => $tokenConfigured,
            'ready' => $endpointConfigured && $tokenConfigured,
            'effective_url' => $base,
            'transport' => str_starts_with($base, 'https://') ? 'HTTPS' : (str_starts_with($base, 'http://') ? 'HTTP' : 'INVALID'),
            'secure_transport' => str_starts_with($base, 'https://'),
            'verify_tls' => $verifyTls,
        ];
    }

    /** Performs an authenticated GET health probe and never transmits a monitoring snapshot. */
    public function testConnectivity(): array
    {
        $settings = $this->repository->monitoringSettings();
        ['base' => $base, 'token' => $token, 'allow_insecure_http' => $allowHttp, 'verify_tls' => $verifyTls] = $this->resolvedConfiguration($settings);
        if (!$this->transportAllowed($base, $allowHttp) || $token === '') return ['reachable' => false, 'error_code' => 'HQ_CONFIGURATION_INCOMPLETE'];
        $latest = $this->repository->latestInfrastructureSnapshot();
        $instanceId = trim((string) ($latest['instance']['instance_id'] ?? ''));
        if ($instanceId === '') return ['reachable' => false, 'error_code' => 'INSTANCE_IDENTITY_UNAVAILABLE'];
        $ch = curl_init($base . '/api/v1/health');
        if ($ch === false) return ['reachable' => false, 'error_code' => 'CURL_INIT_FAILED'];
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => $verifyTls, CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0, CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token, 'X-NexusBox-Instance: ' . $instanceId]]);
        curl_exec($ch); $errno = curl_errno($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($errno !== 0) return ['reachable' => false, 'http_status' => 0, 'error_code' => 'NETWORK_ERROR_' . $errno];
        return ['reachable' => $status >= 200 && $status < 300, 'http_status' => $status, 'transport' => str_starts_with($base, 'https://') ? 'HTTPS' : 'HTTP', 'secure_transport' => str_starts_with($base, 'https://'), 'error_code' => $status >= 200 && $status < 300 ? null : 'HQ_HTTP_' . $status];
    }

    private function send(string $url, string $token, string $payload, int $snapshotId, bool $verifyTls): array
    {
        $decoded = json_decode($payload, true);
        $instanceId = is_array($decoded) ? trim((string) ($decoded['instance']['instance_id'] ?? '')) : '';
        if ($instanceId === '') return [false, 'INVALID_PAYLOAD_IDENTITY'];

        $timestamp = (string) time();
        $payloadHash = hash('sha256', $payload);
        $deliveryId = $instanceId . ':' . $snapshotId;
        $signature = hash_hmac('sha256', $timestamp . '.' . $deliveryId . '.' . $payloadHash, $token);
        $ch = curl_init($url);
        if ($ch === false) return [false, 'CURL_INIT_FAILED'];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $verifyTls,
            CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-NexusBox-Schema: ' . self::SCHEMA_VERSION,
                'X-NexusBox-Instance: ' . $instanceId,
                'X-NexusBox-Timestamp: ' . $timestamp,
                'X-NexusBox-Delivery: ' . $deliveryId,
                'X-NexusBox-Payload-SHA256: ' . $payloadHash,
                'X-NexusBox-Signature: v1=' . $signature,
            ],
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) return [false, 'NETWORK_ERROR_' . $errno];
        if ($status >= 200 && $status < 300) return [true, null];
        return [false, 'HQ_HTTP_' . $status];
    }

    /** Compatibility envelope for the HQ schema; the stored local snapshot remains immutable. */
    private function prepareOutboundPayload(string $payload): string
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) return $payload;
        $decoded['instance_uuid'] = (string) ($decoded['instance']['instance_id'] ?? '');
        $decoded['application_version'] = (string) ($decoded['instance']['application_version'] ?? '');
        $system = is_array($decoded['system'] ?? null) ? $decoded['system'] : [];
        $failedUnits = is_array($system['failed_systemd_units'] ?? null)
            ? $system['failed_systemd_units']
            : [];
        unset($system['failed_systemd_units']);
        $decoded['resources'] = $system + [
            'memory_percentage' => $system['memory_percent'] ?? null,
            'disk_percentage' => $system['disk_percent'] ?? null,
            'failed_systemd_unit_count' => $failedUnits['count'] ?? null,
        ];
        foreach (($decoded['services'] ?? []) as $key => $service) {
            if (!is_array($service) || ($service['status'] ?? '') !== 'DISABLED') continue;
            $decoded['services'][$key]['status'] = 'UNKNOWN';
            $decoded['services'][$key]['configured'] = false;
        }
        return (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Cron does not inherit the web server environment, so secrets may live in the protected runtime file. */
    private function runtimeMonitoringConfiguration(): array
    {
        $path = BASE_PATH . '/.env.runtime.php';
        if (!is_file($path)) return [];
        $runtime = require $path;
        return is_array($runtime['monitoring'] ?? null) ? $runtime['monitoring'] : [];
    }

    private function resolvedConfiguration(array $settings): array
    {
        $runtime = $this->runtimeMonitoringConfiguration();
        return [
            'base' => rtrim(trim((string) (getenv('HQ_MONITORING_URL') ?: ($runtime['hq_url'] ?? $settings['monitoring_hq_url'] ?? ''))), '/'),
            'token' => trim((string) (getenv('HQ_MONITORING_TOKEN') ?: ($runtime['hq_token'] ?? ''))),
            'allow_insecure_http' => filter_var(getenv('HQ_MONITORING_ALLOW_INSECURE_HTTP') ?: ($runtime['allow_insecure_http'] ?? $settings['monitoring_allow_insecure_http'] ?? false), FILTER_VALIDATE_BOOLEAN),
            'verify_tls' => filter_var(getenv('HQ_MONITORING_VERIFY_TLS') !== false ? getenv('HQ_MONITORING_VERIFY_TLS') : ($runtime['verify_tls'] ?? $settings['monitoring_verify_tls'] ?? true), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function transportAllowed(string $base, bool $allowInsecureHttp): bool
    {
        return str_starts_with($base, 'https://') || ($allowInsecureHttp && str_starts_with($base, 'http://'));
    }
}
