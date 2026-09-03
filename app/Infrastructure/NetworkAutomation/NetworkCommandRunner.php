<?php

namespace App\Infrastructure\NetworkAutomation;

use JsonException;
use RuntimeException;

final class NetworkCommandRunner
{
    private const DEFAULT_TIMEOUT_SECONDS = 45;
    private const MAX_OUTPUT_BYTES = 2_000_000;

    public function runPythonJson(
        string $scriptPath,
        array $payload,
        int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS
    ): CommandResult {
        if (!is_file($scriptPath)) {
            throw new RuntimeException('Network automation script was not found.');
        }

        try {
            $stdin = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode the network automation request.', 0, $e);
        }

        return $this->run(['python3', $scriptPath], $stdin, $timeoutSeconds);
    }

    /**
     * @param list<string> $command Arguments are passed directly to proc_open;
     *                              no shell command string is constructed.
     * @param array<int,string> $extraInput Data for additional child file descriptors.
     */
    public function run(
        array $command,
        string $stdin = '',
        int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        array $extraInput = []
    ): CommandResult {
        if ($command === [] || trim((string)$command[0]) === '') {
            throw new RuntimeException('Network automation executable is required.');
        }

        $timeoutSeconds = max(1, min(300, $timeoutSeconds));
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        foreach ($extraInput as $descriptor => $_value) {
            $descriptor = (int)$descriptor;
            if ($descriptor < 3) {
                throw new RuntimeException('Additional input descriptors must be 3 or greater.');
            }
            $descriptors[$descriptor] = ['pipe', 'r'];
        }

        $pipes = [];
        $startedAt = microtime(true);
        $process = proc_open($command, $descriptors, $pipes, BASE_PATH, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the network automation process.');
        }

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        foreach ($extraInput as $descriptor => $value) {
            fwrite($pipes[(int)$descriptor], $value);
            fclose($pipes[(int)$descriptor]);
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $exitCode = -1;

        while (true) {
            $stdout = $this->appendBounded($stdout, stream_get_contents($pipes[1]) ?: '');
            $stderr = $this->appendBounded($stderr, stream_get_contents($pipes[2]) ?: '');
            $status = proc_get_status($process);

            if (!$status['running']) {
                $exitCode = (int)$status['exitcode'];
                break;
            }

            if ((microtime(true) - $startedAt) >= $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process, 15);
                usleep(100_000);
                $status = proc_get_status($process);
                if ($status['running']) {
                    proc_terminate($process, 9);
                }
                break;
            }

            usleep(20_000);
        }

        $stdout = $this->appendBounded($stdout, stream_get_contents($pipes[1]) ?: '');
        $stderr = $this->appendBounded($stderr, stream_get_contents($pipes[2]) ?: '');
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return new CommandResult(
            exitCode: $timedOut ? 124 : $exitCode,
            stdout: trim($stdout),
            stderr: trim($stderr),
            durationMs: (int)round((microtime(true) - $startedAt) * 1000),
            timedOut: $timedOut
        );
    }

    public function redact(string $value, array $sensitiveValues): string
    {
        foreach ($sensitiveValues as $sensitiveValue) {
            $sensitiveValue = (string)$sensitiveValue;
            if ($sensitiveValue !== '') {
                $value = str_replace($sensitiveValue, '[REDACTED]', $value);
            }
        }

        return $value;
    }

    private function appendBounded(string $existing, string $chunk): string
    {
        if ($chunk === '' || strlen($existing) >= self::MAX_OUTPUT_BYTES) {
            return $existing;
        }

        return $existing . substr($chunk, 0, self::MAX_OUTPUT_BYTES - strlen($existing));
    }
}
