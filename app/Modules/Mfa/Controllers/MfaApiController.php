<?php

namespace App\Modules\Mfa\Controllers;

use App\Core\Security\Csrf;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Mfa\DTOs\CreateMfaDTO;
use App\Modules\Mfa\Services\MfaService;
use App\Modules\Mfa\Validators\CreateMfaValidator;
use App\Modules\ApiTokens\DTOs\CreateApiTokensDTO;
use App\Modules\ApiTokens\Services\ApiTokensService;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

final class MfaApiController extends ApiController
{
    public function __construct(private MfaService $service, private AuthService $auth, private ApiTokensService $tokens, private CreateMfaValidator $validator) {}

    public function index(): void
    {
        if (!$this->admin()) return;
        $this->success($this->service->summary(), 'MFA settings loaded.');
    }

    public function enroll($id): void
    {
        if (!$this->admin()) return;
        try {
            $input = $this->request()->input();
            $dto = CreateMfaDTO::fromArray($input);
            $errors = $this->validator->validate($dto->toArray());
            if ($errors !== []) { $this->error('Invalid MFA configuration.', 422, $errors); return; }
            $this->success($this->service->beginEnrollment((int)$id, (string)($dto->toArray()['method'] ?? '')), 'MFA enrollment started.');
        } catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function verifyEnrollment($id): void
    {
        if (!$this->admin()) return;
        try {
            $input = $this->request()->input();
            $this->service->verifyEnrollment((int)$id, (string)($input['code'] ?? ''), $input['challenge_token'] ?? null);
            $this->success([], 'MFA enabled successfully.');
        } catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function disable($id): void
    {
        if (!$this->admin()) return;
        try {
            $this->service->disable((int)$id);
            $this->success([], 'MFA disabled successfully.');
        } catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function reset($id): void
    {
        if (!$this->admin()) return;
        try {
            $this->service->reset((int)$id);
            $this->success([], 'MFA reset successfully. The user can set it up again.');
        } catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function verifyLogin(): void
    {
        try {
            $input = $this->request()->input();
            if (!empty($input['csrf_token']) && !Csrf::validate((string)$input['csrf_token'])) {
                $this->error('Invalid request.', 419); return;
            }
            $userId = $this->service->verifyLogin((string)($input['challenge_token'] ?? ''), (string)($input['code'] ?? ''));
            $this->auth->completeLoginById($userId);
            if (str_starts_with((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/api/')) {
                $created = $this->tokens->create(new CreateApiTokensDTO(
                    userId: $userId, expiresAt: date('Y-m-d H:i:s', time() + 86400),
                    name: 'API MFA login ' . date('Y-m-d H:i'), description: 'Short-lived token issued after MFA verification.',
                    purpose: 'EXTERNAL_INTEGRATION', scopes: ['monitoring.read']
                ));
                $this->success(['token_type' => 'Bearer', 'access_token' => $created['token'], 'expires_at' => $created['expires_at'], 'scopes' => $created['scopes']], 'API access token created.');
                return;
            }
            $this->success(['redirect_url' => $this->redirectPath()], 'Login successful.');
        } catch (Throwable $e) { $this->error('The verification code is invalid or expired.', 401); }
    }

    private function admin(): bool
    {
        $user = SessionManager::user();
        if (is_array($user) && in_array(strtoupper((string)($user['role'] ?? '')), ['ADMINISTRATOR', 'SUPERADMIN'], true)) return true;
        $this->error('Administrator access only.', 403);
        return false;
    }

    private function redirectPath(): string
    {
        $role = strtoupper((string)(SessionManager::user()['role'] ?? ''));
        return $role === 'SUBSCRIBER' ? '/subscriber-portal' : (in_array($role, ['TECHNICIAN', 'BILLING', 'NOC', 'SUPPORT'], true) ? '/staff-attendance' : '/dashboard');
    }
}
