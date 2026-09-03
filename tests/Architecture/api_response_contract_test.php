<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$failures = [];
$response = (string)file_get_contents($base . '/framework/Response.php');

foreach (['ok', 'success', 'status', 'message', 'data', 'errors'] as $field) {
    if (!preg_match("/['\"]" . preg_quote($field, '/') . "['\"]\s*=>/", $response)) {
        $failures[] = "Framework response envelope is missing {$field}.";
    }
}

foreach (glob($base . '/app/Modules/*/Controllers/*Controller.php') ?: [] as $file) {
    $source = (string)file_get_contents($file);
    if (str_contains($source, 'echo json_encode') || preg_match('/header\(["\']Content-Type:\s*application\/json/i', $source)) {
        $failures[] = str_replace($base . '/', '', $file) . ': bypasses the shared Response implementation.';
    }
}

foreach ([
    'app/Modules/Auth/Controllers/AuthController.php',
    'app/Modules/Branding/Controllers/BrandingApiController.php',
    'app/Modules/OntDevices/Controllers/AcsActionsController.php',
    'app/Modules/OntDevices/Controllers/OntDevicesController.php',
    'app/Modules/SubscriberPlans/Controllers/SubscriberPlansApiController.php',
] as $relative) {
    $source = (string)file_get_contents($base . '/' . $relative);
    if (str_contains($source, 'echo json_encode') || str_contains($source, "header('Content-Type: application/json")) {
        $failures[] = "{$relative}: bypasses the shared response implementation.";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'api_response_contract=PASS' . PHP_EOL;
