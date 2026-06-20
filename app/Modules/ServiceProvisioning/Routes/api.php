<?php

use App\Modules\ServiceProvisioning\Controllers\ServiceProvisioningApiController;

/*
|--------------------------------------------------------------------------
| MAIN PROVISIONING FLOW
|--------------------------------------------------------------------------
|
| This follows your FINAL ISP workflow:
| VALIDATE → CREATE JOB → RUN → ACS CHECK
|
*/

$router->get('/api/v1/service-provisioning/list', [
    ServiceProvisioningApiController::class,
    'list'
]);

$router->get('/api/v1/service-provisioning/{id}', [
    ServiceProvisioningApiController::class,
    'show'
]);

/*
|--------------------------------------------------------------------------
| STEP 1: VALIDATION
|--------------------------------------------------------------------------
|
| - ONT must be KNOWN
| - Subscriber + Service must exist
| - NAP port must be available
| - VLAN availability check
|
*/

$router->post('/api/v1/service-provisioning/validate', [
    ServiceProvisioningApiController::class,
    'validate'
]);

/*
|--------------------------------------------------------------------------
| STEP 2: CREATE JOB (DRAFT)
|--------------------------------------------------------------------------
|
| - Reserve VLAN (C-VLAN auto)
| - Reserve NAP port
| - Store provisioning job
| - Status = PENDING
|
*/

$router->post('/api/v1/service-provisioning/create', [
    ServiceProvisioningApiController::class,
    'create'
]);

/*
|--------------------------------------------------------------------------
| STEP 3: RUN PROVISIONING (CORE ENGINE)
|--------------------------------------------------------------------------
|
| This triggers:
| - OLT provisioning (Python)
| - QinQ config (S-VLAN + C-VLAN)
| - ONT activation
| - Service-port creation
|
*/

$router->post('/api/v1/service-provisioning/run', [
    ServiceProvisioningApiController::class,
    'run'
]);

/*
|--------------------------------------------------------------------------
| STEP 4: ACS VALIDATION
|--------------------------------------------------------------------------
|
| - Check if ONT appears in ACS
| - Verify WAN IP / PPP session
| - Trigger post-config (PPP, WiFi)
|
*/

$router->post('/api/v1/service-provisioning/check-acs/{jobId}', [
    ServiceProvisioningApiController::class,
    'checkAcs'
]);

/*
|--------------------------------------------------------------------------
| LOGS / DEBUGGING
|--------------------------------------------------------------------------
|
| - Python execution logs
| - OLT command output
|
*/

$router->get('/api/v1/service-provisioning/logs/{jobId}', [
    ServiceProvisioningApiController::class,
    'logs'
]);

/*
|--------------------------------------------------------------------------
| 🚀 NEW: FULL AUTO PROVISION (ONE CLICK)
|--------------------------------------------------------------------------
|
| This is your new ENGINE endpoint:
|
| → VALIDATE
| → AUTO VLAN ASSIGN
| → AUTO NAP PORT
| → RUN OLT SCRIPT
| → UPDATE DB
|
*/

$router->post('/api/v1/service-provisioning/provision', [
    ServiceProvisioningApiController::class,
    'provision'
]);

/*
|--------------------------------------------------------------------------
| SUPPORT DATA (UI DROPDOWNS)
|--------------------------------------------------------------------------
|
| Used in NX.js provisioning page
|
*/

$router->get('/api/v1/service-provisioning/support/subscribers', [
    ServiceProvisioningApiController::class,
    'supportSubscribers'
]);

$router->get('/api/v1/service-provisioning/support/plans', [
    ServiceProvisioningApiController::class,
    'supportPlans'
]);

$router->get('/api/v1/service-provisioning/support/olts', [
    ServiceProvisioningApiController::class,
    'supportOlts'
]);

$router->get('/api/v1/service-provisioning/support/olt-ports', [
    ServiceProvisioningApiController::class,
    'supportOltPorts'
]);

$router->get('/api/v1/service-provisioning/support/network-boxes', [
    ServiceProvisioningApiController::class,
    'supportNetworkBoxes'
]);

$router->get('/api/v1/service-provisioning/support/splitters', [
    ServiceProvisioningApiController::class,
    'supportSplitters'
]);

$router->get('/api/v1/service-provisioning/support/splitter-output-ports', [
    ServiceProvisioningApiController::class,
    'supportSplitterOutputPorts'
]);

$router->get('/api/v1/service-provisioning/support/onts', [
    ServiceProvisioningApiController::class,
    'supportOnts'
]);

/*
|--------------------------------------------------------------------------
| FUTURE (READY)
|--------------------------------------------------------------------------
|
| These are placeholders for next phase:
|
| - billing integration
| - session monitoring
|
*/

$router->post('/api/v1/service-provisioning/retry/{jobId}', [
    ServiceProvisioningApiController::class,
    'retry'
]);

$router->post('/api/v1/service-provisioning/cancel/{jobId}', [
    ServiceProvisioningApiController::class,
    'cancel'
]);