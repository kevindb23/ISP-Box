<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$package = json_decode((string) file_get_contents($root . '/frontend-next/package.json'), true);
$entry = (string) file_get_contents($root . '/frontend-next/src/main.ts');
$readme = (string) file_get_contents($root . '/frontend-next/README.md');
$layout = (string) file_get_contents($root . '/app/UI/Views/layouts/app.php');
$plansApi = (string) file_get_contents($root . '/frontend-next/src/modules/plans/api.ts');
$plansView = (string) file_get_contents($root . '/app/Modules/SubscriberPlans/Views/index.php');
$subscribersApi = (string) file_get_contents($root . '/frontend-next/src/modules/subscribers/api.ts');
$subscribersView = (string) file_get_contents($root . '/app/Modules/Subscribers/Views/index.php');
$bngApi = (string) file_get_contents($root . '/frontend-next/src/modules/bng/api.ts');
$bngView = (string) file_get_contents($root . '/app/Modules/BngManagement/Views/index.php');
$routersApi = (string) file_get_contents($root . '/frontend-next/src/modules/routers/api.ts');
$routersView = (string) file_get_contents($root . '/app/Modules/RouterManagement/Views/index.php');
$cgnatApi = (string) file_get_contents($root . '/frontend-next/src/modules/cgnat/api.ts');
$cgnatView = (string) file_get_contents($root . '/app/Modules/CgnatManagement/Views/index.php');
$vlansApi = (string) file_get_contents($root . '/frontend-next/src/modules/vlans/api.ts');
$vlansView = (string) file_get_contents($root . '/app/Modules/VlanManagement/Views/index.php');
$radiusApi = (string) file_get_contents($root . '/frontend-next/src/modules/radius/api.ts');
$radiusView = (string) file_get_contents($root . '/app/Modules/Radius/Views/index.php');
$oltApi = (string) file_get_contents($root . '/frontend-next/src/modules/olt/api.ts');
$oltDevicesView = (string) file_get_contents($root . '/app/Modules/OltManagement/Views/index.php');
$oltPortsView = (string) file_get_contents($root . '/app/Modules/OltManagement/Views/ports.php');
$oltProfilesView = (string) file_get_contents($root . '/app/Modules/OltManagement/Views/profiles.php');
$napApi = (string) file_get_contents($root . '/frontend-next/src/modules/nap/api.ts');
$napView = (string) file_get_contents($root . '/app/Modules/NapManagement/Views/index.php');
$ontView = (string) file_get_contents($root . '/app/Modules/OntDevices/Views/index.php');
$apiClient = (string) file_get_contents($root . '/frontend-next/src/lib/api.ts');

$check(isset($package['dependencies']['vue']), 'UI Next does not declare Vue.');
$check(isset($package['devDependencies']['tailwindcss']), 'UI Next does not declare Tailwind.');
$check(str_contains($entry, '[data-nx-next-root]'), 'UI Next lacks an explicit opt-in mount boundary.');
$check(str_contains($entry, "addEventListener('nx:page-load'") && str_contains($entry, 'mountNextRoots'), 'UI Next does not remount after shell AJAX navigation.');
$check(str_contains($entry, "foundation-preview"), 'UI Next does not restrict its initial component mount.');
$check(!str_contains($layout, '/build-next/'), 'The live PHP layout loads the isolated UI Next bundle prematurely.');
$check(str_contains($readme, 'no services, repositories, DTOs, validators, entities, routes, database code'), 'The protected backend boundary is undocumented.');
$check(is_file($root . '/public/build-next/.vite/manifest.json'), 'The isolated UI Next production build is missing.');
$check(str_contains($plansView, 'data-nx-next-root="plans"'), 'Plans does not opt in to the isolated UI Next mount.');
$check(str_contains($plansView, 'legacy_ui'), 'Plans lacks an immediate legacy rollback path.');
$check(str_contains($subscribersView, 'data-nx-next-root="subscribers"'), 'Subscribers does not opt in to UI Next.');
$check(str_contains($subscribersView, 'legacy_ui'), 'Subscribers lacks an immediate legacy rollback path.');
$check(str_contains($bngView, 'data-nx-next-root="bng"'), 'BNG does not opt in to UI Next.');
$check(str_contains($bngView, 'legacy_ui'), 'BNG lacks an immediate legacy rollback path.');
$check(str_contains($routersView, 'data-nx-next-root="routers"'), 'Routers does not opt in to UI Next.');
$check(str_contains($routersView, 'legacy_ui'), 'Routers lacks an immediate legacy rollback path.');
$check(str_contains($cgnatView, 'data-nx-next-root="cgnat"'), 'CGNAT does not opt in to UI Next.');
$check(str_contains($cgnatView, 'legacy_ui'), 'CGNAT lacks an immediate legacy rollback path.');
$check(str_contains($vlansView, 'data-nx-next-root="vlans"'), 'VLAN Management does not opt in to UI Next.');
$check(str_contains($vlansView, 'legacy_ui'), 'VLAN Management lacks an immediate legacy rollback path.');
$check(str_contains($radiusView, 'data-nx-next-root="radius"'), 'RADIUS does not opt in to UI Next.');
$check(str_contains($radiusView, 'legacy_ui'), 'RADIUS lacks an immediate legacy rollback path.');
foreach ([$oltDevicesView, $oltPortsView, $oltProfilesView] as $oltView) { $check(str_contains($oltView, 'data-nx-next-root="olt"'), 'An OLT workspace does not opt in to UI Next.'); $check(str_contains($oltView, 'legacy_ui'), 'An OLT workspace lacks a legacy rollback path.'); }
$check(str_contains($napView, 'data-nx-next-root="nap"'), 'NAP Management does not opt in to UI Next.'); $check(str_contains($napView, 'legacy_ui'), 'NAP Management lacks a legacy rollback path.');
$check(str_contains($ontView, 'data-nx-next-root="ont"'), 'ONT Devices does not opt in to UI Next.'); $check(str_contains($ontView, 'legacy_ui'), 'ONT Devices lacks a legacy rollback path.');
foreach (['listSubscriberPlans', 'createSubscriberPlan', 'updateSubscriberPlan', 'deleteSubscriberPlan'] as $method) {
    $check(str_contains($plansApi, $method), "Plans adapter omits {$method}.");
}
foreach (['listSubscribers', 'createSubscriber', 'updateSubscriber', 'suspendSubscriber', 'reactivateSubscriber', 'resetPppPassword', 'resetPortalPassword', 'deleteSubscriber'] as $method) {
    $check(str_contains($subscribersApi, $method), "Subscribers adapter omits {$method}.");
}
foreach (['getBngSetting', 'getBngRuntime', 'getAccelConfig', 'saveBngSetting', 'saveAccelConfig', 'trustBngHostKey', 'installBootRecovery', 'stageAccelConfig', 'activateAccelConfig', 'deleteBngSetting'] as $method) {
    $check(str_contains($bngApi, $method), "BNG adapter omits {$method}.");
}
foreach (['getRouters', 'saveCoreRouter', 'saveFrrRouter', 'scanCoreHostKey', 'trustCoreHostKey', 'getCoreRuntime', 'getFrrRuntime', 'applyCoreRouter', 'applyFrrRouter'] as $method) {
    $check(str_contains($routersApi, $method), "Routers adapter omits {$method}.");
}
foreach (['getCgnat', 'saveCgnat', 'applyCgnat', 'removePostroutingRules'] as $method) {
    $check(str_contains($cgnatApi, $method), "CGNAT adapter omits {$method}.");
}
foreach (['getVlanSummary', 'listVlans', 'listMgmtVlans', 'listOltOptions', 'listOltPorts', 'createVlan', 'updateVlan', 'deleteVlan', 'saveMgmtVlan', 'deleteMgmtVlan'] as $method) {
    $check(str_contains($vlansApi, $method), "VLAN adapter omits {$method}.");
}
foreach (['listRadiusSettings', 'getRadiusSetting', 'createRadiusSetting', 'updateRadiusSetting', 'deleteRadiusSetting'] as $method) {
    $check(str_contains($radiusApi, $method), "RADIUS adapter omits {$method}.");
}
foreach (['listOltDevices', 'getOltDevice', 'createOltDevice', 'updateOltDevice', 'deleteOltDevice', 'listOltPorts', 'listOltProfiles', 'getOltProfile', 'createOltProfile', 'updateOltProfile', 'deleteOltProfile', 'previewOltProfile'] as $method) { $check(str_contains($oltApi, $method), "OLT adapter omits {$method}."); }
foreach (['listOdfs', 'listLcps', 'listNaps', 'getOdf', 'getLcp', 'getNap', 'getNapTopology'] as $method) { $check(str_contains($napApi, $method), "NAP adapter omits {$method}."); }
$check(str_contains($apiClient, 'X-CSRF-TOKEN') && str_contains($apiClient, "credentials: 'same-origin'"), 'UI Next mutations do not preserve CSRF/session handling.');

$liveNextViews = [];
foreach (glob($root . '/app/Modules/*/Views/*.php') ?: [] as $viewPath) {
    if (str_contains((string)file_get_contents($viewPath), '/build-next/')) $liveNextViews[] = $viewPath;
}
// The isolated UI Next bundle is now opted into across the complete admin
// surface, including the original 12 migration targets plus the remaining
// operational modules. Keep this count explicit so duplicate mounts or
// accidental legacy regressions are still detected.
$check(count($liveNextViews) === 30, 'UI Next live mount count is unexpected.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/SubscriberPlans/'))) === 1, 'Plans UI Next mount is missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/Subscribers/'))) === 1, 'Subscribers UI Next mount is missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/BngManagement/'))) === 1, 'BNG UI Next mount is missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/RouterManagement/'))) === 1, 'Routers UI Next mount is missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/CgnatManagement/'))) === 1, 'CGNAT UI Next mount is missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/VlanManagement/'))) === 1, 'VLAN UI Next mount is missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/Radius/'))) === 1, 'RADIUS UI Next mount is missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/OltManagement/'))) === 3, 'OLT UI Next workspace mounts are missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/NapManagement/'))) === 1, 'NAP UI Next mount is missing or duplicated.');
$check(count(array_filter($liveNextViews, static fn(string $path): bool => str_contains($path, '/OntDevices/'))) === 1, 'ONT UI Next mount is missing or duplicated.');

if ($failures !== []) {
    fwrite(STDERR, "UI Next boundary contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "ui_next_boundary_contract=PASS live_mount=plans_subscribers_bng_routers_cgnat_vlans_radius_olt_nap\n";
