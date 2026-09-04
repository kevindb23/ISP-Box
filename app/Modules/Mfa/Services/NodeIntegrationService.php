<?php

namespace App\Modules\Mfa\Services;

final class NodeIntegrationService
{
    public function qrDataUri(string $otpauthUri): string
    {
        return trim($this->run('qr-code.mjs', $otpauthUri));
    }

    public function sendEmail(string $to, string $subject, string $text, string $html): void
    {
        $this->run('send-email.mjs', json_encode([
            'to' => $to,
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ], JSON_THROW_ON_ERROR));
    }

    private function run(string $script, string $input): string
    {
        $node = getenv('MFA_NODE_BINARY') ?: 'node';
        $path = BASE_PATH . '/app/Modules/Mfa/Scripts/' . $script;
        $command = escapeshellarg($node) . ' ' . escapeshellarg($path);
        $pipes = [];
        $environment = is_array($currentEnvironment = getenv()) ? $currentEnvironment : [];
        $runtime = $this->runtimeMfaConfiguration();
        foreach ([
            'MFA_ENCRYPTION_KEY' => 'encryption_key',
            'MFA_SMTP_HOST' => 'smtp_host',
            'MFA_SMTP_PORT' => 'smtp_port',
            'MFA_SMTP_USER' => 'smtp_user',
            'MFA_SMTP_PASSWORD' => 'smtp_password',
            'MFA_MAIL_FROM' => 'mail_from',
        ] as $environmentKey => $runtimeKey) {
            if ((!isset($environment[$environmentKey]) || trim((string)$environment[$environmentKey]) === '')
                && isset($runtime[$runtimeKey])) {
                $environment[$environmentKey] = (string)$runtime[$runtimeKey];
            }
        }

        if ($script === 'send-email.mjs') {
            foreach (['MFA_SMTP_HOST', 'MFA_SMTP_USER', 'MFA_SMTP_PASSWORD', 'MFA_MAIL_FROM'] as $required) {
                if (!isset($environment[$required]) || trim((string)$environment[$required]) === '') {
                    throw new \RuntimeException('Email OTP is not configured. An administrator must configure the SMTP settings first.');
                }
            }
        }

        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH, $environment);

        if (!is_resource($process)) throw new \RuntimeException('Unable to start the MFA Node helper.');
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 15);
        stream_set_timeout($pipes[2], 15);
        $output = stream_get_contents($pipes[1]);
        $error = trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            if ($script === 'send-email.mjs') {
                throw new \RuntimeException('Email OTP could not be sent. Check the SMTP settings and try again.');
            }
            throw new \RuntimeException($error !== '' ? $error : 'The MFA Node helper failed.');
        }
        return (string)$output;
    }

    private function runtimeMfaConfiguration(): array
    {
        foreach ([BASE_PATH . '/storage/runtime/mfa.php', BASE_PATH . '/.env.runtime.php'] as $path) {
            if (!is_file($path)) continue;
            $runtime = require $path;
            $mfa = $runtime['mfa'] ?? $runtime;
            if (is_array($mfa) && $mfa !== []) return $mfa;
        }
        return [];
    }
}
