<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$module = $base . '/app/Modules/ScheduledDowntime';
$migration = file_get_contents($base . '/database/migrations/20260904_000005_scheduled_downtime.sql');
$routes = file_get_contents($module . '/Routes/api.php');
$controller = file_get_contents($module . '/Controllers/ScheduledDowntimeApiController.php');
$service = file_get_contents($module . '/Services/ScheduledDowntimeService.php');
$repository = file_get_contents($module . '/Repositories/ScheduledDowntimeRepository.php');
$validator = file_get_contents($module . '/Validators/CreateScheduledDowntimeValidator.php');
$view = file_get_contents($module . '/Views/index.php');
$main = file_get_contents($base . '/frontend-next/src/main.ts');

$requiredFiles = [
    $migration,
    $routes,
    $controller,
    $service,
    $repository,
    $validator,
    $view,
    $main,
];

if (in_array(false, $requiredFiles, true)) {
    fwrite(STDERR, "scheduled_downtime_contract=FAIL missing module file\n");
    exit(1);
}

require_once $module . '/Validators/CreateScheduledDowntimeValidator.php';
$validatorInstance = new \App\Modules\ScheduledDowntime\Validators\CreateScheduledDowntimeValidator();
$validPayload = [
    'title' => 'Core router maintenance',
    'message' => 'Subscribers may briefly lose access.',
    'starts_at' => '2026-09-10T22:00',
    'ends_at' => '2026-09-11T01:00',
];
$invalidPayload = [
    'title' => ' ',
    'message' => ' ',
    'starts_at' => '2026-09-10T22:00',
    'ends_at' => '2026-09-10T21:00',
];
if ($validatorInstance->validate($validPayload) !== []) {
    fwrite(STDERR, "scheduled_downtime_contract=FAIL valid payload rejected\n");
    exit(1);
}
$invalidErrors = $validatorInstance->validate($invalidPayload);
if (!isset($invalidErrors['message'], $invalidErrors['ends_at'])) {
    fwrite(STDERR, "scheduled_downtime_contract=FAIL invalid payload accepted\n");
    exit(1);
}
$malformedErrors = $validatorInstance->validate([
    ...$validPayload,
    'starts_at' => 'not-a-timestamp',
]);
if (!isset($malformedErrors['starts_at'])) {
    fwrite(STDERR, "scheduled_downtime_contract=FAIL malformed timestamp accepted\n");
    exit(1);
}

$checks = [
    'schema id' => str_contains($migration, 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'),
    'schema title' => str_contains($migration, 'title VARCHAR(160) NOT NULL'),
    'schema message' => str_contains($migration, 'message TEXT NOT NULL'),
    'schema starts_at' => str_contains($migration, 'starts_at DATETIME NOT NULL'),
    'schema ends_at' => str_contains($migration, 'ends_at DATETIME NOT NULL'),
    'schema enabled' => str_contains($migration, 'enabled TINYINT(1) NOT NULL DEFAULT 1'),
    'schema creator' => str_contains($migration, 'created_by BIGINT UNSIGNED NULL'),
    'list route' => str_contains($routes, "get('/api/v1/scheduled-downtime'"),
    'create route' => str_contains($routes, "post('/api/v1/scheduled-downtime'"),
    'update route' => str_contains($routes, "post('/api/v1/scheduled-downtime/{id}'"),
    'delete route' => str_contains($routes, "post('/api/v1/scheduled-downtime/{id}/delete'"),
    'toggle route' => str_contains($routes, "post('/api/v1/scheduled-downtime/{id}/toggle'"),
    'controller create' => str_contains($controller, 'function store'),
    'controller update' => str_contains($controller, 'function update($id)'),
    'controller delete' => str_contains($controller, 'function delete($id)'),
    'controller toggle' => str_contains($controller, 'function toggle($id)'),
    'controller api base' => str_contains($controller, 'extends ApiController'),
    'administrator auth' => substr_count($controller, 'requireAdmin') >= 5,
    'service active window signature' => str_contains($service, 'activeWindow(?DateTimeImmutable $now = null): ?array'),
    'service active query' => str_contains($service, 'new DateTimeImmutable') && str_contains($service, 'repo->activeWindow'),
    'prepared SQL' => str_contains($repository, '$this->db->prepare('),
    'repository CRUD' => str_contains($repository, 'create(array $data, int $createdBy)')
        && str_contains($repository, 'update(int $id, array $data)')
        && str_contains($repository, 'public function delete(int $id)'),
    'validator message' => str_contains($validator, 'trim((string)($input[\'message\'] ?? \'\'))'),
    'validator timestamp format' => str_contains($validator, 'DateTimeImmutable::createFromFormat'),
    'validator time ordering' => str_contains($validator, '$endsAt <= $startsAt'),
    'nuxt view root' => str_contains($view, 'data-nx-next-root="scheduled-downtime"'),
    'nuxt mount import' => str_contains($main, "./modules/scheduled-downtime/ScheduledDowntimePage.vue"),
    'nuxt mount branch' => str_contains($main, "root.dataset.nxNextRoot === 'scheduled-downtime'"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'scheduled_downtime_contract=FAIL ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'scheduled_downtime_contract=PASS checks=' . count($checks) . PHP_EOL;
