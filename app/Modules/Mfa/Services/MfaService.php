<?php

namespace App\Modules\Mfa\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Mfa\Repositories\MfaRepository;
use DateTimeImmutable;

final class MfaService
{
    public function __construct(
        private MfaRepository $repo,
        private TotpService $totp,
        private NodeIntegrationService $node,
        private AuditService $audit
    ) {}

    public function summary(): array
    {
        return ['users' => $this->repo->usersWithMfa()];
    }

    public function isEnabled(int $userId): bool
    {
        $settings = $this->repo->settings($userId);
        return $settings !== null && (int)$settings['enabled'] === 1 && $settings['verified_at'] !== null;
    }

    public function securityStatus(int $userId): array
    {
        $user = $this->requireUser($userId);
        $settings = $this->repo->settings($userId);
        $enabled = $settings !== null
            && (int)$settings['enabled'] === 1
            && $settings['verified_at'] !== null;

        return [
            'enabled' => $enabled,
            'method' => strtoupper((string)($settings['method'] ?? '')),
            'pending_method' => strtoupper((string)($settings['pending_method'] ?? '')),
            'verified_at' => $settings['verified_at'] ?? null,
            'email' => (string)($user['email'] ?? ''),
            'email_available' => filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL) !== false,
        ];
    }

    public function beginEnrollment(int $userId, string $method): array
    {
        $user = $this->requireUser($userId);
        $method = strtoupper(trim($method));
        if (!in_array($method, ['AUTHENTICATOR', 'EMAIL'], true)) throw new \InvalidArgumentException('Choose a supported MFA method.');

        $secret = $method === 'AUTHENTICATOR' ? $this->totp->generateSecret() : '';
        $this->repo->savePendingEnrollment($userId, $method, $secret !== '' ? $this->encrypt($secret) : '');

        $result = ['user_id' => $userId, 'method' => $method];
        if ($method === 'AUTHENTICATOR') {
            $uri = $this->totp->otpauthUri($secret, $user['email'] ?: $user['username'], 'ISP-IN-A-BOX');
            $result['secret'] = $secret;
            $result['qr_data_uri'] = $this->node->qrDataUri($uri);
        } else {
            $challenge = $this->issueChallenge($user, 'ENROLLMENT', 'EMAIL');
            $result['challenge_token'] = $challenge['token'];
            $result['message'] = 'A verification code was sent to the account email address.';
        }

        $this->audit->logEvent(new AuditEventDTO(
            module: 'MFA', action: 'ENROLLMENT_STARTED',
            description: "MFA enrollment started for {$user['username']} using {$method}.",
            userId: $userId, username: (string)$user['username'], result: 'SUCCESS', source: 'WEB', httpMethod: 'POST', route: '/api/v1/mfa'
        ));
        return $result;
    }

    public function verifyEnrollment(int $userId, string $code, ?string $challengeToken = null): void
    {
        $settings = $this->repo->settings($userId);
        if (!$settings) throw new \RuntimeException('MFA enrollment was not started.');

        $valid = false;
        $method = strtoupper((string)($settings['pending_method'] ?? $settings['method'] ?? ''));
        if ($method === 'AUTHENTICATOR') {
            $secret = (string)($settings['pending_secret_encrypted'] ?? $settings['secret_encrypted'] ?? '');
            $valid = $this->totp->verify($this->decrypt($secret), $code);
        } elseif ($challengeToken !== null) {
            $challenge = $this->validChallenge($challengeToken, 'ENROLLMENT');
            $valid = $this->verifyEmailCode($challenge, $code);
            if ($valid) $this->repo->consumeChallenge((int)$challenge['id']);
        }

        if (!$valid) {
            $this->auditMfaFailure($userId, 'ENROLLMENT_FAILED');
            throw new \RuntimeException('The verification code is invalid or expired.');
        }

        $this->repo->enable($userId);
        $user = $this->repo->user($userId);
        $this->audit->log('MFA', 'ENABLED', 'MFA was enabled after successful verification.');
        if ($user) {
            $this->audit->logEvent(new AuditEventDTO(
                module: 'MFA', action: 'ENROLLMENT_COMPLETED',
                description: "MFA enrollment completed for {$user['username']}.", userId: $userId,
                username: (string)$user['username'], result: 'SUCCESS', source: 'WEB', httpMethod: 'POST', route: '/api/v1/mfa'
            ));
        }
    }

    public function disable(int $userId): void
    {
        $this->requireUser($userId);
        $this->repo->disable($userId);
        $this->audit->log('MFA', 'DISABLED', "MFA was disabled for user #{$userId}.");
    }

    public function reset(int $userId): void
    {
        $user = $this->requireUser($userId);
        $this->repo->reset($userId);
        $this->audit->logEvent(new AuditEventDTO(
            module: 'MFA', action: 'RESET',
            description: "MFA was reset by an administrator for {$user['username']}.",
            userId: $userId, username: (string)$user['username'], result: 'SUCCESS',
            source: 'WEB', httpMethod: 'POST', route: '/api/v1/mfa/{id}/reset'
        ));
    }

    public function beginLoginChallenge(array $user): array
    {
        $settings = $this->repo->settings((int)$user['id']);
        $method = strtoupper((string)($settings['method'] ?? 'AUTHENTICATOR'));
        $challenge = $this->issueChallenge($user, 'LOGIN', $method);
        return ['token' => $challenge['token'], 'method' => $method, 'expires_in' => 600];
    }

    public function verifyLogin(string $token, string $code): int
    {
        $challenge = $this->validChallenge($token, 'LOGIN');
        $settings = $this->repo->settings((int)$challenge['user_id']);
        $valid = false;

        if ($settings && strtoupper((string)$challenge['method']) === 'AUTHENTICATOR') {
            $valid = $this->totp->verify($this->decrypt((string)$settings['secret_encrypted']), $code);
        } elseif ($settings) {
            $valid = $this->verifyEmailCode($challenge, $code);
        }

        if (!$valid) {
            $this->repo->registerFailedAttempt((int)$challenge['id']);
            $this->auditMfaFailure((int)$challenge['user_id'], 'LOGIN_MFA_FAILED');
            throw new \RuntimeException('The verification code is invalid or expired.');
        }

        $this->repo->consumeChallenge((int)$challenge['id']);
        $this->audit->log('MFA', 'LOGIN_VERIFIED', 'MFA login challenge verified successfully.');
        return (int)$challenge['user_id'];
    }

    public function beginPasswordReset(string $identifier): string
    {
        $identifier = trim($identifier);
        $user = $identifier === '' ? null : $this->findUserByIdentifier($identifier);
        $token = bin2hex(random_bytes(32));
        if ($user && $this->isEnabled((int)$user['id']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $this->issueChallenge($user, 'PASSWORD_RESET', strtoupper((string)$this->repo->settings((int)$user['id'])['method']), $token);
        }
        return $token;
    }

    public function resetPassword(string $token, string $code, string $password): int
    {
        $challenge = $this->validChallenge($token, 'PASSWORD_RESET');
        $settings = $this->repo->settings((int)$challenge['user_id']);
        $valid = false;
        if ($settings && strtoupper((string)$challenge['method']) === 'AUTHENTICATOR') {
            $valid = $this->totp->verify($this->decrypt((string)$settings['secret_encrypted']), $code);
        } elseif ($settings) {
            $valid = $this->verifyEmailCode($challenge, $code);
        }

        if (!$valid) {
            $this->repo->registerFailedAttempt((int)$challenge['id']);
            throw new \RuntimeException('The verification code is invalid or expired.');
        }
        if (strlen($password) < 12) throw new \InvalidArgumentException('Use a password with at least 12 characters.');

        $this->repo->consumeChallenge((int)$challenge['id']);
        $this->updatePassword((int)$challenge['user_id'], $password);
        $this->audit->log('MFA', 'PASSWORD_RESET', 'Password reset completed after MFA verification.');
        return (int)$challenge['user_id'];
    }

    private function issueChallenge(array $user, string $purpose, string $method, ?string $token = null): array
    {
        $token ??= bin2hex(random_bytes(32));
        $code = $method === 'EMAIL' ? (string)random_int(100000, 999999) : null;
        $this->repo->createChallenge([
            'user_id' => (int)$user['id'], 'purpose' => $purpose, 'method' => $method,
            'token_hash' => hash('sha256', $token), 'code_hash' => $code ? password_hash($code, PASSWORD_DEFAULT) : null,
            'expires_at' => (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        if ($method === 'EMAIL') {
            if (!filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('This account does not have a valid email address.');
            $this->node->sendEmail(
                (string)$user['email'], 'Your ISP-IN-A-BOX verification code',
                "Your verification code is {$code}. It expires in 10 minutes.",
                '<p>Your ISP-IN-A-BOX verification code is <strong>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>It expires in 10 minutes.</p>'
            );
        }
        return ['token' => $token, 'code' => $code];
    }

    private function validChallenge(string $token, string $purpose): array
    {
        $challenge = $this->repo->challengeByToken(hash('sha256', trim($token)), $purpose);
        if (!$challenge || $challenge['consumed_at'] !== null || $challenge['verified_at'] !== null && $purpose === 'LOGIN') throw new \RuntimeException('The MFA challenge is invalid or expired.');
        if ((int)$challenge['attempts'] >= (int)$challenge['max_attempts'] || strtotime((string)$challenge['expires_at']) < time()) throw new \RuntimeException('The MFA challenge is invalid or expired.');
        return $challenge;
    }

    private function verifyEmailCode(array $challenge, string $code): bool
    {
        return is_string($challenge['code_hash'] ?? null) && password_verify(preg_replace('/\D+/', '', $code) ?? '', $challenge['code_hash']);
    }

    private function findUserByIdentifier(string $identifier): ?array
    {
        foreach ($this->repo->usersWithMfa() as $user) {
            if (hash_equals(strtolower((string)$user['username']), strtolower($identifier)) || hash_equals(strtolower((string)$user['email']), strtolower($identifier))) return $user;
        }
        return null;
    }

    private function requireUser(int $userId): array
    {
        $user = $this->repo->user($userId);
        if (!$user) throw new \RuntimeException('User account not found.');
        return $user;
    }

    private function updatePassword(int $userId, string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->repo->updatePassword($userId, $hash);
    }

    private function encrypt(string $plain): string
    {
        $key = $this->key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    private function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new \RuntimeException('The stored MFA secret is invalid.');
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key());
        if ($plain === false) throw new \RuntimeException('The stored MFA secret cannot be decrypted.');
        return $plain;
    }

    private function key(): string
    {
        $configured = $this->configuredValue('MFA_ENCRYPTION_KEY', 'encryption_key');
        $key = base64_decode($configured, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new \RuntimeException('MFA_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
        return $key;
    }

    private function configuredValue(string $environmentKey, string $runtimeKey): string
    {
        $environmentValue = getenv($environmentKey);
        if ($environmentValue !== false && trim($environmentValue) !== '') return trim($environmentValue);
        return trim((string)($this->runtimeMfaConfiguration()[$runtimeKey] ?? ''));
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

    private function auditMfaFailure(int $userId, string $action): void
    {
        $user = $this->repo->user($userId);
        $this->audit->logEvent(new AuditEventDTO(
            module: 'MFA', action: $action, description: 'An MFA verification attempt failed.', userId: $userId,
            username: (string)($user['username'] ?? 'UNKNOWN'), result: 'FAILED', source: 'WEB', httpMethod: 'POST', route: '/login/mfa'
        ));
    }
}
