<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$service = file_get_contents($base . '/app/Modules/ServiceProvisioning/Services/ServiceProvisioningService.php');
$repository = file_get_contents($base . '/app/Modules/ServiceProvisioning/Repositories/ServiceProvisioningRepository.php');
$javascript = file_get_contents($base . '/app/Modules/ServiceProvisioning/Assets/js/ServiceProvisioning.js');
$view = file_get_contents($base . '/app/Modules/ServiceProvisioning/Views/index.php');
$failures = [];

$contracts = [
    [$service, "\$subscriberId,\n            \$planId,\n            \$plan", 'new subscriber service receives the plan ID'],
    [$service, "Only GPON subscriber ports", 'backend rejects non-GPON ports'],
    [$service, "ACS verification is only allowed after successful OLT provisioning", 'ACS stage guard exists'],
    [$service, "nexusbox:provisioning:allocation", 'allocation is serialized'],
    [$service, "nexusbox:provisioning:pon:", 'per-PON execution is serialized'],
    [$service, "releaseSplitterOutputPortReservation", 'safe cancellation releases splitter reservation'],
    [$service, "deleteUnactivatedBinding", 'safe cancellation releases VLAN binding'],
    [$repository, "GET_LOCK", 'repository supports named locks'],
    [$repository, "status = 'RESERVED'", 'splitter port is reserved before execution'],
    [$repository, "assignOntToSubscriber", 'completed provisioning assigns the inventory ONT'],
    [$service, "assignOntToSubscriber", 'activation synchronizes ONT inventory ownership'],
    [$repository, "max(1, (int)ceil", 'empty pagination still reports one page'],
    [$repository, "LIKE '%GPON%'", 'support endpoint lists GPON ports only'],
    [$javascript, "data-job-action=\"check-acs\"", 'job table exposes ACS retry'],
    [$javascript, "attempt <= 18", 'ACS polling is bounded'],
    [$view, '<th class="text-end">Actions</th>', 'job table contains an actions column'],
];

foreach ($contracts as [$contents, $needle, $label]) {
    if (!str_contains($contents, $needle)) {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'service_provisioning_safety_contract=PASS' . PHP_EOL;
