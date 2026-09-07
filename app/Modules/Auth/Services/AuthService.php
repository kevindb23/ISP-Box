<?php

namespace App\Modules\Auth\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Auth\DTOs\LoginDTO;
use App\Modules\Auth\Repositories\AdminRepository;
use App\Modules\Auth\Repositories\LoginAttemptRepository;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Auth\Services\LoginSecurityDetector;
use Framework\SessionManager;
use App\Modules\Mfa\Services\MfaService;

class AuthService
{
    private AdminRepository $users;
    private LoginAttemptRepository $attempts;
    private AuditService $audit;

    public function __construct(
        AdminRepository $users,
        LoginAttemptRepository $attempts,
        AuditService $audit,
        MfaService $mfa
    ) {
        $this->users = $users;
        $this->attempts = $attempts;
        $this->audit = $audit;
        $this->mfa = $mfa;
    }

    private MfaService $mfa;

    public function login(LoginDTO $credentials): bool
    {
        $result = $this->authenticate($credentials);
        if (($result['status'] ?? '') !== 'success') return false;
        $this->completeLoginById((int)$result['user_id']);
        return true;
    }

    public function authenticate(LoginDTO $credentials): array
    {
        $username = $credentials->username;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($this->attempts->isLocked($username)) {
            $this->auditAuthentication($username, 'LOGIN_LOCKED', 'DENIED', 'Login denied because the account is temporarily locked.');
            return ['status' => 'invalid'];
        }

        $admin = $this->users->findByUsername($username);

        if (!$admin || !$admin->isActive() || !$admin->passwordMatches($credentials->password)) {
            $this->attempts->recordFailure($username, $ip);
            $this->auditAuthentication($username, 'LOGIN_FAILED', 'FAILED', 'Login failed because the supplied credentials were invalid.');
            return ['status' => 'invalid'];
        }

        $this->attempts->clear($username);

        if ($this->mfa->isEnabled($admin->id())) {
            $challenge = $this->mfa->beginLoginChallenge($admin->toSessionArray() + ['id' => $admin->id()]);
            return ['status' => 'mfa_required', 'user_id' => $admin->id(), 'challenge' => $challenge];
        }

        return ['status' => 'success', 'user_id' => $admin->id()];
    }

    public function completeLoginById(int $userId): void
    {
        $admin = $this->users->findById($userId);
        if (!$admin || !$admin->isActive()) throw new \RuntimeException('The account is no longer active.');

        $this->users->updateLastLogin($admin->id());
        // updateLastLogin may advance users.updated_at through the database
        // timestamp rule. Reload the identity before storing its session
        // version so a fresh login is not rejected as stale immediately.
        $admin = $this->users->findById($userId);
        if (!$admin || !$admin->isActive()) throw new \RuntimeException('The account is no longer active.');
        $sessionUser = $admin->toSessionArray();
        $sessionUser['last_login'] = date('Y-m-d H:i:s');

        SessionManager::login($sessionUser);

        $this->audit->log(
            'USERS',
            'LOGIN',
            "User {$admin->username()} logged in"
        );

        unset($_SESSION['csrf_token']);
    }

    public function recordSuspiciousInput(LoginDTO $credentials): void
    {
        if (!LoginSecurityDetector::isSuspicious($credentials->username, $credentials->password)) {
            return;
        }

        $this->auditAuthentication(
            $this->safeUsername($credentials->username),
            'LOGIN_SECURITY_ALERT',
            'CRITICAL',
            'Suspicious login input detected and rejected.'
        );
    }

    public function passwordResetChallenge(string $identifier): string
    {
        return $this->mfa->beginPasswordReset($identifier);
    }

    public function resetPassword(string $token, string $code, string $password): void
    {
        $this->mfa->resetPassword($token, $code, $password);
    }

    private function auditAuthentication(
        string $username,
        string $action,
        string $result,
        string $description
    ): void {
        $this->audit->logEvent(new AuditEventDTO(
            module: 'AUTH',
            action: $action,
            description: $description,
            username: $username !== '' ? $username : 'UNKNOWN',
            ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            objectType: 'USER_ACCOUNT',
            result: $result,
            source: 'WEB',
            httpMethod: 'POST',
            route: '/login'
        ));
    }

    private function safeUsername(string $username): string
    {
        $safe = preg_replace('/[^\p{L}\p{N}_.@-]+/u', '_', $username) ?: 'UNKNOWN';
        return substr($safe, 0, 120) ?: 'UNKNOWN';
    }
}
