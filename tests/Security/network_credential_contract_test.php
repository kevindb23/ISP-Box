<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$failures = [];
$automationModules = [
    'CgnatManagement',
    'OltManagement',
    'OntDevices',
    'RouterManagement',
    'ServiceProvisioning',
    'Subscribers',
    'VlanManagement',
];

foreach ($automationModules as $module) {
    $root = $base . '/app/Modules/' . $module;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($iterator as $file) {
        if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'py'], true)) {
            continue;
        }

        $source = (string)file_get_contents($file->getPathname());
        $relative = str_replace($base . '/', '', $file->getPathname());

        if (preg_match('/\b(?:shell_exec|system|passthru)\s*\(/', $source)) {
            $failures[] = $relative . ': uses a shell execution primitive.';
        }
        if ($file->getExtension() === 'py' && str_contains($source, 'sys.argv')) {
            $failures[] = $relative . ': accepts automation credentials or payload through argv.';
        }
        if (preg_match('/sshpass\s+(?:["\']?)-p\b/', $source)) {
            $failures[] = $relative . ': exposes an SSH password in process arguments.';
        }
    }
}

$provisioning = (string)file_get_contents(
    $base . '/app/Modules/ServiceProvisioning/Services/ServiceProvisioningService.php'
);
if (preg_match('/["\']command["\']\s*=>/', $provisioning)) {
    $failures[] = 'ServiceProvisioningService persists a command in its job log payload.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, array_unique($failures)) . PHP_EOL);
    exit(1);
}

echo 'network_credential_contract=PASS' . PHP_EOL;
