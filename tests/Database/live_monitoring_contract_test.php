<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
define('BASE_PATH', $base);
spl_autoload_register(static function (string $class) use ($base): void {
    foreach (['App\\' => $base . '/app/', 'Framework\\' => $base . '/framework/'] as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    }
});

$repository = new App\Modules\Api\v1\Repositories\MonitoringRepository(
    new App\Infrastructure\Database\DatabaseConnection()
);
$service = new App\Modules\Api\v1\Services\MonitoringService($repository);
$info = $service->systemInfo();
$failures = [];

foreach (['instance_id','system_name','version','timezone'] as $key) {
    if (trim((string)($info[$key] ?? '')) === '') $failures[] = "system info omits {$key}";
}
foreach (['subscribers','sessions','provisioning','onts','olts','billing'] as $resource) {
    $summary = $service->summary($resource);
    if (!array_key_exists('total', $summary) && !array_key_exists('invoice_count', $summary)) {
        $failures[] = "{$resource} summary has no aggregate count";
    }
}
if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'live_monitoring_contract=PASS instance_id=' . $info['instance_id'] . PHP_EOL;
