<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$bng = file_get_contents($base . '/app/Modules/BngManagement/Services/BngConnectionService.php');
$vlan = file_get_contents($base . '/app/Modules/VlanManagement/Services/VlanManagementService.php');
$repo = file_get_contents($base . '/app/Modules/VlanManagement/Repositories/VlanManagementRepository.php');
$dto = file_get_contents($base . '/app/Modules/VlanManagement/DTOs/CreateVlanDTO.php');
$validator = file_get_contents($base . '/app/Modules/VlanManagement/Validators/CreateVlanValidator.php');
$controller = file_get_contents($base . '/app/Modules/BngManagement/Controllers/BngManagementApiController.php');
$vlanController = file_get_contents($base . '/app/Modules/VlanManagement/Controllers/VlanManagementApiController.php');
$vlanRoutes = file_get_contents($base . '/app/Modules/VlanManagement/Routes/api.php');
$vlanJs = file_get_contents($base . '/app/Modules/VlanManagement/Assets/js/VlanManagement.js');
$nextVlan = file_get_contents($base . '/frontend-next/src/modules/vlans/VlansPage.vue');
$deployScript = file_get_contents($base . '/app/Modules/VlanManagement/Scripts/deploy_vlan.py');
$deleteScript = file_get_contents($base . '/app/Modules/VlanManagement/Scripts/delete_vlan.py');
$mgmtMigration = file_get_contents($base . '/database/migrations/20260901_000001_mgmt_vlan_olt_port.sql');
$failures = [];

foreach (['ensureSvlanInterface', 'ensureCvlanInterface'] as $method) {
    if (!str_contains($bng, "function {$method}")) $failures[] = "missing {$method}";
}
foreach (['mergeDesiredVlanInterfaces', 'NOT PRESENT', 'desiredState->interfaces()'] as $runtimeContract) {
    if (!str_contains($bng, $runtimeContract)) $failures[] = "BNG runtime omits configured idle VLAN interfaces: {$runtimeContract}";
}
foreach (['(?:1WAN|BNG)', '$detectedPeerIp !== null'] as $pppRuntimeContract) {
    if (!str_contains($bng, $pppRuntimeContract)) $failures[] = "BNG runtime does not detect configurable PPP interface names: {$pppRuntimeContract}";
}
foreach (['ensureSvlanInterface', 'ensureCvlanInterface'] as $method) {
    $start = strpos($bng, "function {$method}");
    $end = strpos($bng, "\n    public function ", $start + 10);
    $body = substr($bng, $start, $end === false ? null : $end - $start);
    if (!str_contains($body, 'runPrivilegedCommand')) $failures[] = "{$method} bypasses password-backed privileged execution";
    if (str_contains($body, 'withPrivilege(')) $failures[] = "{$method} still relies on passwordless sudo";
}
foreach (['ensure_svlan', 'set_svlan_up', 'ensure_cvlan', 'set_cvlan_up', 'verify'] as $step) {
    if (!str_contains($bng, "'{$step}'")) $failures[] = "missing idempotent BNG step {$step}";
}

foreach ([
    'undo port vlan {vlan_id} {int(frame)}/{int(slot)} {int(port_no)}',
    'vlan forwarding {vlan_id} vlan-mac',
    'undo vlan attrib {vlan_id}',
    'undo vlan {vlan_id}',
] as $svlanQinqDeleteContract) {
    if (!str_contains($deleteScript, $svlanQinqDeleteContract)) {
        $failures[] = "S-VLAN QinQ deletion does not remove {$svlanQinqDeleteContract}";
    }
}
foreach (['display current-configuration', 'service-port', 'qinq_ranges', 'undo vlan attrib {start} to {end} q-in-q', 'vlan forwarding {vlan_id} vlan-mac', 'undo vlan {vlan_id}'] as $svlanDependencyContract) {
    if (!str_contains($deleteScript, $svlanDependencyContract)) {
        $failures[] = "S-VLAN QinQ deletion does not handle {$svlanDependencyContract}";
    }
}
if (!str_contains($deleteScript, 'port_no') || !str_contains($vlan, "'frame' =>") || !str_contains($vlan, "'slot' =>") || !str_contains($vlan, "'port_no' =>")) {
    $failures[] = 'S-VLAN OLT port coordinates are not passed to QinQ deletion';
}
foreach (['ensureSvlanInterface', 'ensureCvlanInterface'] as $method) {
    $start = strpos($bng, "function {$method}");
    $end = strpos($bng, "\n    public function ", $start + 10);
    $body = substr($bng, $start, $end === false ? null : $end - $start);
    foreach (['netplan set', '--origin-hint ispbox-network', 'netplan generate', 'netplan apply', "'persist_netplan'"] as $netplanContract) {
        if (!str_contains($body, $netplanContract)) $failures[] = "{$method} does not persist VLAN state with {$netplanContract}";
    }
}

foreach (['SecretCipher', '->decrypt('] as $credentialContract) {
    if (!str_contains($repo, $credentialContract)) {
        fwrite(STDERR, "vlan_bng_integration_contract=FAIL missing encrypted OLT credential handling: {$credentialContract}\n");
        exit(1);
    }
}
foreach (['deployVlanToBng', 'ensureSvlanInterface', 'ensureCvlanInterface'] as $call) {
    if (!str_contains($vlan, $call)) $failures[] = "VLAN deployment omits {$call}";
}
foreach ([$dto, $validator, $repo, $vlan] as $source) {
    if (!str_contains($source, 'parent_svlan_id')) $failures[] = 'C-VLAN flow omits its explicit parent S-VLAN';
}
if (!str_contains($repo, 'findParentSvlan')) $failures[] = 'C-VLAN parent cannot be ownership-validated';
if (str_contains($bng, 'attachPppSessionsToClients')) $failures[] = 'PPP sessions are still positionally attached to C-VLANs';
if (!str_contains($bng, 'StrictHostKeyChecking=yes') || str_contains($bng, 'StrictHostKeyChecking=no')) $failures[] = 'SSH host identity is not strictly verified';
if (!str_contains($controller, '$this->request()->input()') || str_contains($controller, 'getInputData(') || str_contains($controller, '$this->respond(')) $failures[] = 'BNG controller request/response contract regressed';
if (strpos($vlan, '$bngDeployment = $this->deployVlanToBng') > strpos($vlan, "'DEPLOYED'")) {
    $failures[] = 'VLAN is marked deployed before BNG interface creation';
}
foreach (['retryVlan', '/retry'] as $retryContract) {
    if (!str_contains($vlanController . $vlanRoutes . $vlanJs, $retryContract)) $failures[] = "failed VLAN deployment cannot be retried: {$retryContract}";
}
foreach (['assignedSvlanPortIds', 'assignedPortIds', 'sVlanPortExists'] as $portOwnershipContract) {
    if (!str_contains($vlan . $repo . $vlanJs . file_get_contents($base . '/frontend-next/src/modules/vlans/VlansPage.vue'), $portOwnershipContract)) $failures[] = "S-VLAN port ownership is not enforced consistently: {$portOwnershipContract}";
}
foreach (['H901MPSA', 'olt_port_id'] as $mgmtPortContract) {
    if (!str_contains($vlan . $repo . $vlanJs . file_get_contents($base . '/frontend-next/src/modules/vlans/VlansPage.vue') . $mgmtMigration, $mgmtPortContract)) $failures[] = "MGMT-VLAN port selection is incomplete: {$mgmtPortContract}";
}
foreach (["tab==='qinq'", 'qinqRows', 'Q-in-Q'] as $qinqUiContract) {
    if (!str_contains($vlanJs . file_get_contents($base . '/app/Modules/VlanManagement/Views/index.php') . $nextVlan, $qinqUiContract)) {
        $failures[] = "Q-in-Q VLAN view is missing {$qinqUiContract}";
    }
}
if (!str_contains($deployScript, 'port vlan {vlan_id}') || !str_contains($deleteScript, 'undo port vlan {vlan_id}')) $failures[] = 'MGMT-VLAN deployment does not apply/remove its H901MPSA port binding';

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'vlan_bng_integration_contract=PASS' . PHP_EOL;
