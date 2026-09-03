<?php

$root = dirname(__DIR__, 2);
$js = file_get_contents($root . '/app/Modules/OntDevices/Assets/js/OntDevices.js');
$detailsJs = file_get_contents($root . '/app/Modules/OntDevices/Assets/js/OntDeviceDetails.js');
$css = file_get_contents($root . '/app/Modules/OntDevices/Assets/css/OntDevices.css');
$routes = file_get_contents($root . '/app/Modules/OntDevices/Routes/api.php');
$repo = file_get_contents($root . '/app/Modules/OntDevices/Repositories/OntDevicesRepository.php');
$acs = file_get_contents($root . '/app/Modules/OntDevices/Controllers/AcsActionsController.php');
$acsService = file_get_contents($root . '/app/Modules/OntDevices/Services/AcsService.php');
$opticalService = file_get_contents($root . '/app/Modules/OntDevices/Services/OltOpticalService.php');
$opticalScript = file_get_contents($root . '/app/Modules/OntDevices/Scripts/olt_optical_info.py');
$view = file_get_contents($root . '/app/Modules/OntDevices/Views/index.php');
$dto = file_get_contents($root . '/app/Modules/OntDevices/DTOs/CreateOntDevicesDTO.php');

$checks = [
    'nowrap table actions' => str_contains($js, 'class="ont-table-actions"'),
    'discovery OLT selector' => str_contains($js, 'id="ontDiscoveryOltSelect"'),
    'single ACS collection request' => !str_contains($js, "api.get('/api/v1/ont-devices/acs/devices')"),
    'optical refresh uses POST helper' => str_contains($detailsJs, 'api.urlEncoded('),
    'optical refresh unwraps API envelope' => str_contains($detailsJs, 'response?.data ?? response'),
    'connected clients exclude inactive hosts' => str_contains($detailsJs, 'if (!active) return;')
        && str_contains($acsService, 'FILTER_VALIDATE_BOOLEAN'),
    'WAN fields are role-aware' => str_contains($detailsJs, "role !== 'TR069'")
        && str_contains($detailsJs, "upper(iface.type) !== 'PPP' && role !== 'TR069'"),
    'optical route is POST' => str_contains($routes, "post('/api/v1/ont-devices/acs/device/{id}/optical'"),
    'provisioning-aware optical mapping' => str_contains($repo, 'findOpticalMappingBySerial'),
    'serial-based optical recovery' => str_contains($acs, 'getOpticalCandidateOlts')
        && str_contains($opticalService, 'fetchOpticalInfoBySerial')
        && str_contains($opticalScript, 'display ont info by-sn'),
    'ACS parameters sanitized' => str_contains($acs, 'getUiParameters($device)')
        && str_contains($acsService, 'sanitizeParameterTree')
        && !str_contains($acs, "jsonOk('Device parameters loaded.', \$device)"),
    'ACS mutations audited' => str_contains($acs, "'ACS_WIFI_UPDATE'") && str_contains($acs, "'ACS_WAN_CREATE'"),
    'canonical table layout' => str_contains($css, 'Canonical ONT table layout'),
    'inventory-derived form choices' => str_contains($view, 'ontVendorOptions')
        && str_contains($view, 'ontModelOptions'),
    'subscriber name matching' => str_contains($view, 'ontSubscriberSearchInput')
        && str_contains($js, 'syncSubscriberSelection'),
    'MAC removed from workflow' => !str_contains($view, 'name="mac_address"')
        && !str_contains($dto, "'mac_address'"),
    'equipment ID removed from workflow' => !str_contains($view, 'equipment_id')
        && !str_contains($dto, "'equipment_id'"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'ont_devices_consistency_contract=FAIL ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'ont_devices_consistency_contract=PASS' . PHP_EOL;
