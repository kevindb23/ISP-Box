<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$migration = file_get_contents($base . '/database/migrations/20260815_000005_api_token_integration_foundation.sql');
$service = file_get_contents($base . '/app/Modules/ApiTokens/Services/ApiTokensService.php');
$view = file_get_contents($base . '/app/Modules/ApiTokens/Views/index.php');
$javascript = file_get_contents($base . '/app/Modules/ApiTokens/Assets/js/ApiTokens.js');
$routes = file_get_contents($base . '/app/Modules/ApiTokens/Routes/web.php');
$failures = [];

foreach (['name', 'description', 'purpose', 'scopes', 'created_by', 'last_used_at', 'last_used_ip', 'revoked_at'] as $field) {
    if (!str_contains($migration, $field)) $failures[] = "migration omits {$field}";
}
foreach (['random_bytes(32)', "hash('sha256', \$rawToken)", 'CreateApiTokensDTO'] as $contract) {
    if (!str_contains($service, $contract)) $failures[] = "token service omits {$contract}";
}
foreach (['CENTRAL_MONITORING', 'infrastructure.monitoring.read'] as $option) {
    if (!str_contains($view, $option)) $failures[] = "token UI omits {$option}";
}
if (str_contains($javascript, 'fetch(')) $failures[] = 'token UI bypasses shared NX API client';
foreach (['monitoring-operations','monitoring-settings','monitoring-test','monitoring-retry'] as $route) {
    if (!str_contains($routes, $route)) $failures[] = "monitoring operations route missing: {$route}";
}
foreach (['UPDATE_MONITORING_SETTINGS','TEST_HQ_CONNECTIVITY','RETRY_MONITORING_DELIVERIES'] as $action) {
    if (!str_contains($service, $action)) $failures[] = "monitoring audit action missing: {$action}";
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'api_token_integration_contract=PASS' . PHP_EOL;
