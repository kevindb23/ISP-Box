<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$provisioning = file_get_contents($base . '/app/Modules/ServiceProvisioning/Services/ServiceProvisioningService.php');
$workOrders = file_get_contents($base . '/app/Modules/WorkOrders/Services/WorkOrdersService.php');
$repository = file_get_contents($base . '/app/Modules/WorkOrders/Repositories/WorkOrdersRepository.php');
$settingsDto = file_get_contents($base . '/app/Modules/SystemSettings/DTOs/UpdateGeneralSettingsDTO.php');
$settingsView = file_get_contents($base . '/app/Modules/SystemSettings/Views/index.php');
$failures = [];

foreach ([
    [$provisioning, 'createFromProvisioning($job)', 'successful provisioning does not generate an installation work order'],
    [$workOrders, "'source_type' => 'SERVICE_PROVISIONING'", 'work order is not linked to its provisioning source'],
    [$workOrders, "'work_order_type' => 'ONT_INSTALLATION'", 'generated work order is not an ONT installation'],
    [$workOrders, "installation_assignment_mode", 'assignment mode is not applied'],
    [$repository, 'findNearestAvailableTechnician', 'nearest-technician selection is missing'],
    [$repository, 'SET installed_at = COALESCE(installed_at, NOW())', 'completion does not stamp installation time'],
    [$repository, 'GET_LOCK', 'idempotent source lock is missing'],
    [$settingsDto, 'installationAssignmentMode', 'settings DTO omits installation assignment mode'],
    [$settingsView, 'AUTO_NEAREST', 'settings UI omits automatic nearest assignment'],
] as [$contents, $needle, $message]) {
    if (!str_contains($contents, $needle)) $failures[] = $message;
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'provisioning_work_order_contract=PASS' . PHP_EOL;
