<?php

$root = dirname(__DIR__, 2);

$requiredFiles = [
    'app/Modules/SystemMaintenance/Module.php',
    'app/Modules/SystemMaintenance/Controllers/SystemMaintenanceController.php',
    'app/Modules/SystemMaintenance/Controllers/SystemMaintenanceApiController.php',
    'app/Modules/SystemMaintenance/Services/SystemMaintenanceService.php',
    'app/Modules/SystemMaintenance/Repositories/SystemMaintenanceRepository.php',
    'app/Modules/SystemMaintenance/Routes/web.php',
    'app/Modules/SystemMaintenance/Routes/api.php',
    'app/Modules/SystemMaintenance/Views/index.php',
    'database/migrations/20260904_000006_system_maintenance.sql',
    'frontend-next/src/modules/system-maintenance/SystemMaintenancePage.vue',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        fwrite(STDERR, "missing required file: {$file}\n");
        exit(1);
    }
}

$migration = file_get_contents($root . '/database/migrations/20260904_000006_system_maintenance.sql');
foreach (['system_maintenance', 'enabled', 'message', 'starts_at', 'ends_at'] as $column) {
    if (stripos($migration, $column) === false) {
        fwrite(STDERR, "migration missing {$column}\n");
        exit(1);
    }
}

$service = file_get_contents($root . '/app/Modules/SystemMaintenance/Services/SystemMaintenanceService.php');
$repository = file_get_contents($root . '/app/Modules/SystemMaintenance/Repositories/SystemMaintenanceRepository.php');
$apiController = file_get_contents($root . '/app/Modules/SystemMaintenance/Controllers/SystemMaintenanceApiController.php');
$apiRoutes = file_get_contents($root . '/app/Modules/SystemMaintenance/Routes/api.php');
$portalController = file_get_contents($root . '/app/Modules/SubscriberPortal/Controllers/SubscriberPortalController.php');
$portalApiController = file_get_contents($root . '/app/Modules/SubscriberPortal/Controllers/SubscriberPortalApiController.php');

foreach (['activeState', 'DateTimeImmutable', 'starts_at', 'ends_at', 'message'] as $needle) {
    if (stripos($service, $needle) === false) {
        fwrite(STDERR, "service missing {$needle}\n");
        exit(1);
    }
}

foreach (['save', 'get', 'UPDATE', 'INSERT'] as $needle) {
    if (stripos($repository, $needle) === false) {
        fwrite(STDERR, "repository missing {$needle}\n");
        exit(1);
    }
}

foreach (['requireAdmin', 'GET', 'POST', 'system-maintenance'] as $needle) {
    if (stripos($apiController . $apiRoutes, $needle) === false) {
        fwrite(STDERR, "admin API contract missing {$needle}\n");
        exit(1);
    }
}

if (stripos($portalController . $portalApiController, 'maintenance') === false) {
    fwrite(STDERR, "subscriber portal maintenance guard missing\n");
    exit(1);
}

foreach (['index', 'account', 'services', 'invoices', 'payments', 'tickets', 'security'] as $view) {
    $contents = file_get_contents($root . '/app/Modules/SubscriberPortal/Views/' . $view . '.php');
    foreach (['maintenanceState', 'We are sorry for the interruption', 'return;'] as $needle) {
        if (stripos($contents, $needle) === false) {
            fwrite(STDERR, "subscriber {$view} view missing {$needle}\n");
            exit(1);
        }
    }
}

if (stripos($portalApiController, "activeState()") === false) {
    fwrite(STDERR, "subscriber API controller does not expose the read-only active state\n");
    exit(1);
}

require_once $root . '/app/Modules/SystemMaintenance/Entities/SystemMaintenance.php';
require_once $root . '/app/Modules/SystemMaintenance/Repositories/SystemMaintenanceRepository.php';
require_once $root . '/app/Modules/SystemMaintenance/Validators/CreateSystemMaintenanceValidator.php';
require_once $root . '/app/Modules/SystemMaintenance/Services/SystemMaintenanceService.php';

$service = (new ReflectionClass(\App\Modules\SystemMaintenance\Services\SystemMaintenanceService::class))
    ->newInstanceWithoutConstructor();
$inside = new DateTimeImmutable('2026-09-04 12:00:00');

if (!$service->isActive(['enabled' => 1, 'starts_at' => null, 'ends_at' => null], $inside)) {
    fwrite(STDERR, "immediate maintenance mode is not active\n");
    exit(1);
}
if (!$service->isActive(['enabled' => 1, 'starts_at' => '2026-09-04 11:00:00', 'ends_at' => '2026-09-04 13:00:00'], $inside)) {
    fwrite(STDERR, "scheduled in-window maintenance mode is not active\n");
    exit(1);
}
if ($service->isActive(['enabled' => 1, 'starts_at' => '2026-09-04 13:00:00', 'ends_at' => '2026-09-04 14:00:00'], $inside)) {
    fwrite(STDERR, "scheduled pre-start maintenance mode is active\n");
    exit(1);
}
if ($service->isActive(['enabled' => 1, 'starts_at' => '2026-09-04 09:00:00', 'ends_at' => '2026-09-04 10:00:00'], $inside)) {
    fwrite(STDERR, "expired maintenance mode is active\n");
    exit(1);
}
if ($service->isActive(['enabled' => 0, 'starts_at' => null, 'ends_at' => null], $inside)) {
    fwrite(STDERR, "disabled maintenance mode is active\n");
    exit(1);
}

$validator = new \App\Modules\SystemMaintenance\Validators\CreateSystemMaintenanceValidator();
if ($validator->validate(['message' => ' ', 'starts_at' => '', 'ends_at' => '']) === []) {
    fwrite(STDERR, "empty customer message was accepted\n");
    exit(1);
}
if ($validator->validate([
    'message' => 'Maintenance',
    'starts_at' => '2026-09-04T13:00',
    'ends_at' => '2026-09-04T12:00',
]) === []) {
    fwrite(STDERR, "invalid maintenance schedule was accepted\n");
    exit(1);
}

echo "system_maintenance_contract=PASS\n";
