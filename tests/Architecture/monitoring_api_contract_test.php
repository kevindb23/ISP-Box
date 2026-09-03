<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$routes = file_get_contents($base . '/app/Modules/Api/v1/Routes/api.php');
$router = file_get_contents($base . '/framework/Router.php');
$tokenRepository = file_get_contents($base . '/app/Modules/Api/v1/Repositories/ApiTokenRepository.php');
$monitoringRepository = file_get_contents($base . '/app/Modules/Api/v1/Repositories/MonitoringRepository.php');

$failures = [];
foreach (['/api/v1/system/info', '/api/v1/monitoring/infrastructure/snapshot', '/api/v1/monitoring/infrastructure/history', '/api/v1/monitoring/{resource}/summary'] as $route) {
    if (!str_contains($routes, $route)) $failures[] = "missing route {$route}";
}
foreach (['tokenScopeCanAccess', 'infrastructure.monitoring.read', 'monitoring.read', 'subscribers.read', 'billing.summary.read', 'olt.status.read'] as $contract) {
    if (!str_contains($router, $contract)) $failures[] = "router omits {$contract}";
}
foreach (['token_name', 'purpose', 'scopes', 'revoked_at', 'last_used_at', 'last_used_ip'] as $field) {
    if (!str_contains($tokenRepository, $field)) $failures[] = "token identity omits {$field}";
}
foreach (['subscribers', 'subscriber_services', 'radius_accounting', 'service_provisioning_jobs',
             'ont_devices', 'olt_devices', 'invoices'] as $table) {
    if (!str_contains($monitoringRepository, $table)) $failures[] = "monitoring repository omits {$table}";
}
$monitoringService = file_get_contents($base . '/app/Modules/Api/v1/Services/MonitoringService.php');
$probeService = file_get_contents($base . '/app/Modules/Api/v1/Services/InfrastructureProbeService.php');
$deliveryService = file_get_contents($base . '/app/Modules/Api/v1/Services/MonitoringDeliveryService.php');
foreach (['collectAndStore','infrastructureHistory'] as $contract) if (!str_contains($monitoringService,$contract)) $failures[]="monitoring service omits {$contract}";
if (!is_file($base . '/VERSION') || trim((string) file_get_contents($base . '/VERSION')) === '') $failures[]='canonical application VERSION is missing';
if (!str_contains($monitoringRepository, "BASE_PATH . '/VERSION'")) $failures[]='monitoring does not use the canonical application VERSION';
foreach (['accel-ppp.service','frr.service','conntrack_percent','failed_systemd_units'] as $contract) if (!str_contains($probeService,$contract)) $failures[]="probe service omits {$contract}";
foreach (['application_version','memory_percentage','disk_percentage','failed_systemd_unit_count'] as $contract) if (!str_contains($deliveryService,$contract)) $failures[]="HQ delivery compatibility omits {$contract}";
if (str_contains($probeService, 'php_fpm') || str_contains($monitoringService, "['services']['php_fpm']")) $failures[]='unused PHP-FPM monitoring remains enabled';
foreach (['HQ_MONITORING_TOKEN','hq_token','X-NexusBox-Instance','X-NexusBox-Delivery','X-NexusBox-Payload-SHA256','X-NexusBox-Signature','hash_hmac','CURLOPT_SSL_VERIFYPEER','allow_insecure_http'] as $contract) if (!str_contains($deliveryService,$contract)) $failures[]="delivery service omits {$contract}";
if (str_contains($routes, 'ApiAuthMiddleware::class')) $failures[] = 'duplicated route-level API authentication remains';

require_once $base . '/framework/AuthenticatedApiIdentity.php';
require_once $base . '/framework/Router.php';
$routerClass = new ReflectionClass(Framework\Router::class);
$routerInstance = $routerClass->newInstanceWithoutConstructor();
$scopeCheck = $routerClass->getMethod('tokenScopeCanAccess');
$scopeCases = [
    ['GET', '/api/v1/system/info', ['scopes' => ['infrastructure.monitoring.read']], true],
    ['GET', '/api/v1/monitoring/infrastructure/snapshot', ['scopes' => ['infrastructure.monitoring.read']], true],
    ['GET', '/api/v1/monitoring/infrastructure/snapshot', ['scopes' => ['billing.summary.read']], false],
    ['GET', '/api/v1/monitoring/billing/summary', ['scopes' => ['billing.summary.read']], true],
    ['GET', '/api/v1/monitoring/billing/summary', ['scopes' => ['subscribers.read']], false],
    ['POST', '/api/v1/subscribers', ['scopes' => ['subscribers.read']], false],
];
foreach ($scopeCases as [$method, $uri, $identity, $expected]) {
    if ($scopeCheck->invoke($routerInstance, $method, $uri, $identity) !== $expected) {
        $failures[] = "scope decision mismatch for {$method} {$uri}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'monitoring_api_contract=PASS' . PHP_EOL;
