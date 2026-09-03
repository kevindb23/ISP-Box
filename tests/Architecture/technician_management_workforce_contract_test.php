<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$service = file_get_contents($base . '/app/Modules/TechnicianManagement/Services/TechnicianManagementService.php');
$repository = file_get_contents($base . '/app/Modules/TechnicianManagement/Repositories/TechnicianManagementRepository.php');
$view = file_get_contents($base . '/app/Modules/TechnicianManagement/Views/index.php');
$js = file_get_contents($base . '/app/Modules/TechnicianManagement/Assets/js/TechnicianManagement.js');

$checks = [
    'canonical assignment service' => str_contains($service, '$this->workOrders->assign('),
    'canonical status service' => str_contains($service, '$this->workOrders->updateStatus('),
    'manager role restriction' => str_contains($service, "['SUPERADMIN', 'NOC', 'SUPPORT']"),
    'available-only dispatch' => str_contains($repository, "HAVING availability_status = 'AVAILABLE'"),
    'clock-in guard' => str_contains($repository, 'Technician must be clocked in before availability can be changed.'),
    'technician and dispatch tabs' => str_contains($view, 'techniciansListPane') && str_contains($view, 'technicianDispatchPane'),
    'terminal note requirement' => str_contains($service, "['COMPLETED', 'FAILED', 'CANCELLED']"),
    'offline manual option removed' => !str_contains($js, "renderStatusOption(row.id, 'OFFLINE'"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'Technician management workforce contract failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'technician_management_workforce_contract=PASS checks=' . count($checks) . PHP_EOL;
