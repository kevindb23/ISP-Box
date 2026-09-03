<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$failures = [];
$htaccess = (string)file_get_contents($base . '/public/.htaccess');
$routes = (string)file_get_contents($base . '/app/Modules/TechnicianPortal/Routes/api.php');
$controller = (string)file_get_contents($base . '/app/Modules/TechnicianPortal/Controllers/TechnicianPortalApiController.php');
$service = (string)file_get_contents($base . '/app/Modules/TechnicianPortal/Services/TechnicianPortalService.php');

if (!str_contains($htaccess, 'uploads/work-orders') || !str_contains($htaccess, '[F,L]')) {
    $failures[] = 'Legacy public work-order uploads are not denied by the web server.';
}
if (!str_contains($routes, '/attachments/{id}/download')) {
    $failures[] = 'Protected attachment download route is missing.';
}
if (!str_contains($controller, 'downloadPhoto') || !str_contains($service, "\$role === 'SUPERADMIN'")) {
    $failures[] = 'Attachment download authorization is missing.';
}
if (!str_contains($service, 'private:work-orders/')) {
    $failures[] = 'New work-order uploads are not stored as private references.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'upload_access_contract=PASS' . PHP_EOL;
