<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$repository = file_get_contents($base . '/app/Modules/ServiceProvisioning/Repositories/ServiceProvisioningRepository.php');
$controller = file_get_contents($base . '/app/Modules/ServiceProvisioning/Controllers/ServiceProvisioningApiController.php');
$javascript = file_get_contents($base . '/app/Modules/ServiceProvisioning/Assets/js/ServiceProvisioning.js');
$ontRepository = file_get_contents($base . '/app/Modules/OntDevices/Repositories/OntDevicesRepository.php');
$failures = [];

foreach (['getSupportPlans', 'getSupportOlts', 'findJobById'] as $method) {
    if (!preg_match('/public function ' . preg_quote($method, '/') . '\\b(.*?)(?=\\n    public function|\\z)/s', $repository, $match)) {
        $failures[] = "missing provisioning read method {$method}";
        continue;
    }
    if (str_contains($match[1], 'ppp_password') || preg_match('/\\b(?:od\\.)?password\\b/i', $match[1])) {
        $failures[] = "{$method} exposes a provisioning or OLT password";
    }
}

if (str_contains($controller, "'ppp_password'") || str_contains($javascript, 'ppp_password')) {
    $failures[] = 'provisioning API/UI contract exposes PPP password';
}

foreach ([$repository, $ontRepository] as $source) {
    if (!str_contains($source, '$this->secrets->decrypt')) {
        $failures[] = 'internal OLT credential lookup does not decrypt encrypted storage';
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'provisioning_secret_contract=PASS' . PHP_EOL;
