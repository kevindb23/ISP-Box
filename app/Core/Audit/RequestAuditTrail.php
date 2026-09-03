<?php

namespace App\Core\Audit;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use Framework\Request;
use Framework\SessionManager;
use Framework\AuthenticatedApiIdentity;
use Throwable;

class RequestAuditTrail
{
    private bool $registered = false;

    public function __construct(
        private AuditService $audit,
        private Request $request
    ) {
    }

    public function begin(string $method, string $uri): void
    {
        if ($this->registered || !$this->shouldAudit($method, $uri)) {
            return;
        }

        $this->registered = true;
        $startedAt = microtime(true);
        $apiIdentity = AuthenticatedApiIdentity::get();
        $actor = $apiIdentity ?: (SessionManager::user() ?: []);
        if ($apiIdentity !== null) {
            $actor['id'] = $apiIdentity['user_id'] ?? 0;
            $actor['username'] = $apiIdentity['token_name'] ?? 'API_TOKEN';
        }
        $requestId = $this->requestId();
        $input = $this->sanitize($this->request->all());
        $module = $this->moduleFromUri($uri);
        $action = $this->actionFromRequest($method, $uri);
        $objectType = $this->objectTypeFromUri($uri);
        $objectId = $this->objectId($uri, $input);
        $source = $this->sourceFromUri($uri);

        header('X-Request-ID: ' . $requestId);

        register_shutdown_function(function () use (
            $method,
            $uri,
            $startedAt,
            $actor,
            $requestId,
            $input,
            $module,
            $action,
            $objectType,
            $objectId,
            $source
        ): void {
            $status = http_response_code();
            $status = is_int($status) && $status > 0 ? $status : 200;
            $error = error_get_last();
            $result = $this->result($status, $error);
            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

            try {
                $this->audit->logEvent(new AuditEventDTO(
                    module: $module,
                    action: $action,
                    description: sprintf(
                        '%s %s completed with HTTP %d (%s)%s.',
                        $method,
                        $uri,
                        $status,
                        $result,
                        $this->identifierSummary($input)
                    ),
                    userId: (int)($actor['id'] ?? 0),
                    username: (string)($actor['username'] ?? $input['username'] ?? 'SYSTEM'),
                    actorRole: isset($actor['role']) ? (string)$actor['role'] : null,
                    ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
                    objectType: $objectType,
                    objectId: $objectId,
                    result: $result,
                    source: $source,
                    httpMethod: $method,
                    route: $uri,
                    requestId: $requestId,
                    metadata: [
                        'input' => $input,
                        'duration_ms' => $durationMs,
                        'status_code' => $status,
                        'token_id' => $apiIdentity['token_id'] ?? null,
                        'token_name' => $apiIdentity['token_name'] ?? null,
                        'token_source' => $apiIdentity['source'] ?? null,
                        'fatal_error' => $error !== null && in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true),
                        'fatal_error_type' => $error !== null ? (int)($error['type'] ?? 0) : null,
                    ]
                ));
            } catch (Throwable $e) {
                error_log('[Audit] Unable to persist request audit: ' . $e->getMessage());
            }
        });
    }

    private function shouldAudit(string $method, string $uri): bool
    {
        $method = strtoupper($method);

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return false;
        }

        $sensitivePrefixes = [
            '/audit', '/users', '/billing', '/payment-gateway', '/radius',
            '/olt-management', '/ont-devices', '/bng', '/cgnat', '/vlan-management',
            '/service-provisioning', '/api-tokens',
            '/subscribers', '/subscriber-plans', '/subscriber-portal',
            '/tickets', '/work-orders', '/staff-attendance',
            '/technician-management', '/technician-portal', '/nap-management',
            '/branding',
            '/monitoring', '/system',
        ];

        $normalized = preg_replace('#^/api/v1#', '', $uri) ?: $uri;

        foreach ($sensitivePrefixes as $prefix) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function requestId(): string
    {
        $provided = trim((string)($this->request->header('X-Request-ID') ?? ''));

        if ($provided !== '' && preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $provided)) {
            return $provided;
        }

        return bin2hex(random_bytes(16));
    }

    private function moduleFromUri(string $uri): string
    {
        $path = preg_replace('#^/api/v1/#', '/', $uri) ?: $uri;
        $segment = explode('/', trim($path, '/'))[0] ?? 'SYSTEM';

        if (in_array($segment, ['', 'login', 'logout'], true)) {
            return 'AUTH';
        }

        return strtoupper(str_replace('-', '_', $segment));
    }

    private function actionFromRequest(string $method, string $uri): string
    {
        $path = preg_replace('#^/api/v1/#', '/', $uri) ?: $uri;
        $path = preg_replace('#/\d+(?=/|$)#', '/{ID}', $path) ?: $path;
        $action = strtoupper($method . '_' . trim(str_replace(['-', '/', '{', '}'], '_', $path), '_'));
        $action = preg_replace('/_+/', '_', $action) ?: strtoupper($method);

        return substr($action, 0, 100);
    }

    private function objectTypeFromUri(string $uri): ?string
    {
        $path = preg_replace('#^/api/v1/#', '/', $uri) ?: $uri;
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        return isset($segments[1])
            ? strtoupper(str_replace('-', '_', $segments[1]))
            : (isset($segments[0]) ? strtoupper(str_replace('-', '_', $segments[0])) : null);
    }

    private function objectId(string $uri, array $input): ?int
    {
        if (preg_match('#/(\d+)(?:/|$)#', $uri, $matches)) {
            return (int)$matches[1];
        }

        foreach ([
            'id', 'object_id', 'job_id', 'work_order_id', 'ticket_id', 'task_id',
            'invoice_id', 'payment_id', 'subscriber_id', 'service_id', 'user_id',
            'olt_id', 'ont_id', 'vlan_id', 'network_box_id', 'splitter_id',
        ] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                return (int)$input[$key];
            }
        }

        return null;
    }

    private function identifierSummary(array $input): string
    {
        $parts = [];

        foreach ([
            'work_order_no', 'ticket_no', 'invoice_no', 'reference_no',
            'work_order_id', 'ticket_id', 'task_id', 'invoice_id', 'payment_id',
            'subscriber_id', 'service_id', 'user_id', 'olt_id', 'ont_id', 'vlan_id',
        ] as $key) {
            if (!isset($input[$key]) || is_array($input[$key]) || is_object($input[$key])) {
                continue;
            }

            $value = trim((string)$input[$key]);
            if ($value !== '') {
                $parts[] = $key . '=' . substr($value, 0, 80);
            }
        }

        return $parts === [] ? '' : ' [' . implode(', ', $parts) . ']';
    }

    private function sourceFromUri(string $uri): string
    {
        if (str_contains($uri, '/webhook')) {
            return 'WEBHOOK';
        }

        return str_starts_with($uri, '/api/') ? 'API' : 'WEB';
    }

    private function result(int $status, ?array $error): string
    {
        if ($error !== null && in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return 'FAILED';
        }

        if (in_array($status, [401, 403, 419], true)) {
            return 'DENIED';
        }

        if ($status >= 500 || $status === 422 || $status === 400) {
            return 'FAILED';
        }

        if ($status >= 300 && $status < 400) {
            return 'REDIRECT';
        }

        return 'SUCCESS';
    }

    private function sanitize(array $data): array
    {
        $sensitive = '/(?:password|passwd|secret|token|authorization|cookie|private[_-]?key|api[_-]?key|ppp[_-]?password|credential)/i';
        $clean = [];

        foreach ($data as $key => $value) {
            if (preg_match($sensitive, (string)$key)) {
                $clean[$key] = '[REDACTED]';
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }
}
