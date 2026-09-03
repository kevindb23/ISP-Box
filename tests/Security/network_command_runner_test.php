<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require BASE_PATH . '/app/Infrastructure/NetworkAutomation/CommandResult.php';
require BASE_PATH . '/app/Infrastructure/NetworkAutomation/NetworkCommandRunner.php';

use App\Infrastructure\NetworkAutomation\NetworkCommandRunner;

$runner = new NetworkCommandRunner();
$failures = [];

$stdin = $runner->run(
    [PHP_BINARY, '-r', 'echo stream_get_contents(STDIN);'],
    'stdin-ok',
    5
);

if (!$stdin->succeeded() || $stdin->stdout !== 'stdin-ok') {
    $failures[] = 'The runner did not transmit standard input correctly.';
}

$secretPipe = $runner->run(
    [PHP_BINARY, '-r', '$h=fopen("php://fd/3","r"); echo stream_get_contents($h);'],
    '',
    5,
    [3 => 'pipe-secret']
);

if (!$secretPipe->succeeded() || $secretPipe->stdout !== 'pipe-secret') {
    $failures[] = 'The runner did not transmit the protected extra input pipe correctly.';
}

if ($runner->redact('before pipe-secret after', ['pipe-secret']) !== 'before [REDACTED] after') {
    $failures[] = 'The runner did not redact a supplied sensitive value.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'network_command_runner=PASS' . PHP_EOL;
