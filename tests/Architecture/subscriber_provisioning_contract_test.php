<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$repository = file_get_contents($base . '/app/Modules/Subscribers/Repositories/SubscriberRepository.php');
$entity = file_get_contents($base . '/app/Modules/Subscribers/Entities/Subscriber.php');
$javascript = file_get_contents($base . '/app/Modules/Subscribers/Assets/js/subscribers.js');
$plansRepository = file_get_contents($base . '/app/Modules/SubscriberPlans/Repositories/SubscriberPlansRepository.php');

$failures = [];

foreach (['service_provisioning_bindings', 'service_provisioning_jobs', 'olt_devices',
             'olt_ports', 'ont_devices', 'network_boxes', 'box_splitters',
             'splitter_output_ports', 'ont_acs'] as $source) {
    if (!str_contains($repository, $source)) {
        $failures[] = "subscriber detail query omits {$source}";
    }
}

foreach (['provisioning_status', 'provisioning_job_no', 'olt_name', 'olt_port_name',
             'ont_serial', 'ont_status', 'nap_name', 'nap_code', 'nap_splitter_port',
             'cvlan', 'svlan', 'acs_status', 'acs_wan_ip'] as $field) {
    if (!str_contains($entity, "'{$field}'")) {
        $failures[] = "entity omits {$field}";
    }
    if (!str_contains($javascript, "data.{$field}")) {
        $failures[] = "modal does not map {$field}";
    }
}

preg_match('/public function all\\b(.*?)(?=\\n    public function|\\z)/s', $repository, $allMethod);
preg_match('/public function findById\\b(.*?)(?=\\n    public function|\\z)/s', $repository, $findMethod);
$publicReadQueries = ($allMethod[1] ?? '') . ($findMethod[1] ?? '');
if (str_contains($publicReadQueries, 'ppp_password') || str_contains($entity, "'ppp_password'")) {
    $failures[] = 'subscriber read contract exposes PPP password';
}

if (!str_contains($repository, 'findPppCredentialContext')) {
    $failures[] = 'subscriber password command lacks a dedicated internal credential query';
}

if (!str_contains($javascript, 'await closeModalFully(createModalEl)')) {
    $failures[] = 'subscriber create success can overlap Bootstrap and credential-dialog backdrops';
}

foreach ([$repository => 'subscriber', $plansRepository => 'plan'] as $source => $label) {
    preg_match('/public function __construct\b(.*?)(?=\n    (?:public|private) function|\z)/s', $source, $constructor);
    if (str_contains($constructor[1] ?? '', 'new PDO')) {
        $failures[] = "{$label} page construction eagerly connects to RADIUS";
    }
    if (!str_contains($source, 'private function radiusDb(): PDO')) {
        $failures[] = "{$label} repository does not lazily connect to RADIUS";
    }
}

if (!str_contains($repository, 'RADIUS online-state lookup unavailable')) {
    $failures[] = 'subscriber listing does not degrade safely when RADIUS telemetry is unavailable';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'subscriber_provisioning_contract=PASS' . PHP_EOL;
