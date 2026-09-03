<?php

namespace App\Infrastructure\NetworkAutomation;

final class CommandResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int $durationMs,
        public readonly bool $timedOut = false
    ) {
    }

    public function succeeded(): bool
    {
        return !$this->timedOut && $this->exitCode === 0;
    }

    public function diagnostics(): array
    {
        return [
            'exit_code' => $this->exitCode,
            'duration_ms' => $this->durationMs,
            'timed_out' => $this->timedOut,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
        ];
    }
}
