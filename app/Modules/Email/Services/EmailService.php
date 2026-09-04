<?php

namespace App\Modules\Email\Services;

use App\Modules\Email\Repositories\EmailRepository;

class EmailService
{
    private EmailRepository $repo;

    public function __construct(EmailRepository $repo)
    {
        $this->repo = $repo;
    }

    public function summary(): array
    {
        return ['module' => 'Email', 'settings' => $this->repo->all(), 'presets' => $this->presets()];
    }

    public function save(array $input): array
    {
        $data = $this->normalize($input);
        $errors = $this->validate($data);
        if ($errors !== []) throw new \InvalidArgumentException(reset($errors));
        return $this->repo->save($data);
    }

    public function testConnection(): array
    {
        $config = $this->repo->connectionConfig();
        if ($config['smtp_host'] === '') throw new \RuntimeException('Configure an SMTP host first.');
        $transport = strtoupper((string)$config['smtp_encryption']) === 'SSL' ? 'ssl://' : '';
        $socket = @fsockopen($transport . $config['smtp_host'], (int)$config['smtp_port'], $errno, $error, 8);
        if (!is_resource($socket)) throw new \RuntimeException('SMTP connection failed: ' . ($error ?: 'unable to reach server'));
        fclose($socket);
        return ['connected' => true, 'host' => $config['smtp_host'], 'port' => (int)$config['smtp_port']];
    }

    public function sendTestEmail(string $recipient): array
    {
        $recipient = trim($recipient);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid test email recipient.');
        }

        $this->sendMessage(
            $recipient,
            'NexusBox SMTP test email',
            'This is a test email from NexusBox SMTP settings.',
            '<p>This is a test email from <strong>NexusBox</strong> SMTP settings.</p>'
        );

        return ['sent' => true, 'recipient' => $recipient];
    }

    public function sendMessage(string $recipient, string $subject, string $text, string $html): void
    {
        $config = $this->repo->connectionConfig();
        foreach (['smtp_host', 'smtp_username', 'smtp_password'] as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                throw new \RuntimeException('Email OTP is not configured. An administrator must configure the SMTP settings first.');
            }
        }

        $from = trim((string)($config['from_email'] ?? '')) ?: (string)$config['smtp_username'];
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Configure a valid From email before sending email.');
        }

        $this->runMailer([
            'to' => $recipient,
            'from' => $from,
            'fromName' => trim((string)($config['from_name'] ?? '')) ?: 'NexusBox',
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ], $config);
    }

    private function normalize(array $input): array
    {
        $preset = strtoupper(trim((string)($input['preset'] ?? 'CUSTOM')));
        $presets = $this->presets();
        $selected = $presets[$preset] ?? [];
        return [
            'preset' => isset($presets[$preset]) ? $preset : 'CUSTOM',
            'smtp_host' => trim((string)($input['smtp_host'] ?? $selected['smtp_host'] ?? '')),
            'smtp_port' => (int)($input['smtp_port'] ?? $selected['smtp_port'] ?? 587),
            'smtp_encryption' => strtoupper(trim((string)($input['smtp_encryption'] ?? $selected['smtp_encryption'] ?? 'TLS'))),
            'smtp_username' => trim((string)($input['smtp_username'] ?? '')),
            'smtp_password' => (string)($input['smtp_password'] ?? ''),
            'from_name' => trim((string)($input['from_name'] ?? '')),
            'from_email' => trim((string)($input['from_email'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['smtp_host'] === '') $errors[] = 'SMTP host is required.';
        if ($data['smtp_port'] < 1 || $data['smtp_port'] > 65535) $errors[] = 'SMTP port must be between 1 and 65535.';
        if (!in_array($data['smtp_encryption'], ['NONE', 'TLS', 'SSL'], true)) $errors[] = 'Choose a valid SMTP encryption mode.';
        if ($data['from_email'] !== '' && filter_var($data['from_email'], FILTER_VALIDATE_EMAIL) === false) $errors[] = 'Sender email is invalid.';
        return $errors;
    }

    private function runMailer(array $message, array $config): void
    {
        $node = getenv('EMAIL_NODE_BINARY') ?: (getenv('MFA_NODE_BINARY') ?: 'node');
        $script = BASE_PATH . '/app/Modules/Email/Scripts/send-test-email.mjs';
        $environment = is_array($current = getenv()) ? $current : [];
        $environment['EMAIL_SMTP_HOST'] = (string)$config['smtp_host'];
        $environment['EMAIL_SMTP_PORT'] = (string)$config['smtp_port'];
        $environment['EMAIL_SMTP_ENCRYPTION'] = (string)$config['smtp_encryption'];
        $environment['EMAIL_SMTP_USER'] = (string)$config['smtp_username'];
        $environment['EMAIL_SMTP_PASSWORD'] = (string)$config['smtp_password'];
        $environment['EMAIL_MAIL_FROM'] = (string)$message['from'];

        $process = proc_open(
            escapeshellarg($node) . ' ' . escapeshellarg($script),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            BASE_PATH,
            $environment
        );
        if (!is_resource($process)) throw new \RuntimeException('Unable to start the email helper.');
        fwrite($pipes[0], json_encode($message, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 15);
        stream_set_timeout($pipes[2], 15);
        $output = stream_get_contents($pipes[1]);
        $error = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new \RuntimeException($error !== '' ? 'Test email could not be sent: ' . $error : 'Test email could not be sent.');
        }
    }

    private function presets(): array
    {
        return [
            'CUSTOM' => ['label' => 'Custom SMTP', 'smtp_host' => '', 'smtp_port' => 587, 'smtp_encryption' => 'TLS'],
            'GMAIL' => ['label' => 'Gmail', 'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 587, 'smtp_encryption' => 'TLS'],
            'EMAILSRVR' => ['label' => 'Email Hosting (Rackspace)', 'smtp_host' => 'secure.emailsrvr.com', 'smtp_port' => 465, 'smtp_encryption' => 'SSL', 'webmail' => 'https://webmail.emailsrvr.com'],
        ];
    }
}
