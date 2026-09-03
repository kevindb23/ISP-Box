<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\DTOs\CreateUsersDTO;
use App\Modules\Users\Entities\Users;
use App\Modules\Users\Repositories\UsersRepository;
use App\Modules\Users\Validators\CreateUsersValidator;
use App\Modules\Audit\Services\AuditService;
use App\Core\Authorization\AuthorizationRepository;
use App\Core\Authorization\AuthorizationService;
use Framework\SessionManager;

class UsersService
{
    private UsersRepository $repo;
    private AuditService $audit;

    public function __construct(
        UsersRepository $repo,
        AuditService $audit,
        private CreateUsersValidator $validator,
        private AuthorizationRepository $authorizationRepository,
        private AuthorizationService $authorization
    ) {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    public function list(): array
    {
        return array_map(
            static fn(array $row): array => (new Users($row))->toArray(),
            $this->repo->all()
        );
    }

    public function find(int $id): ?array
    {
        $row = $this->repo->find($id);
        return $row ? (new Users($row))->toArray() : null;
    }

    public function create(array $input): int
    {
        $data = $this->validatedData(new CreateUsersDTO($input), true);
        if (strtoupper((string)$data['role']) === 'SUPERADMIN') $this->assertSuperadminActor();

        if ($this->repo->usernameExists($data['username'])) {
            throw new \InvalidArgumentException('Username already exists.');
        }

        $plainUsername = $data['username'];
        $plainRole = $data['role'];
        $plainStatus = $data['status'];

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        $id = $this->repo->create($data);

        $this->audit->log(
            'USERS',
            'CREATE',
            "Created user {$plainUsername} with role {$plainRole} and status {$plainStatus}"
        );

        return $id;
    }

    public function update(int $id, array $input): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid user ID.');
        }

        $existing = $this->repo->find($id);

        if (!$existing) {
            throw new \InvalidArgumentException('User not found.');
        }

        $data = $this->validatedData(new CreateUsersDTO($input), false);
        if (strtoupper((string)$existing['role']) === 'SUPERADMIN' || strtoupper((string)$data['role']) === 'SUPERADMIN') {
            $this->assertSuperadminActor();
        }

        $actorId = (int)(SessionManager::id() ?? 0);
        if ($actorId === $id && (
            strtoupper((string)$existing['role']) !== strtoupper((string)$data['role']) ||
            strtoupper((string)$existing['status']) !== strtoupper((string)$data['status'])
        )) {
            throw new \InvalidArgumentException('You cannot change your own role or account status.');
        }
        $this->protectLastSuperadmin($id, $existing, $data['role'], $data['status']);

        if ($this->repo->usernameExists($data['username'], $id)) {
            throw new \InvalidArgumentException('Username already exists.');
        }

        $this->repo->update($id, $data);

        $changes = $this->describeChanges($existing, $data);

        $description = $changes !== ''
            ? "Updated user {$existing['username']}: {$changes}"
            : "Updated user {$existing['username']} with no visible field changes";

        $this->audit->log(
            'USERS',
            'UPDATE',
            $description
        );
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid user ID.');
        }

        $existing = $this->repo->find($id);

        if (!$existing) {
            throw new \InvalidArgumentException('User not found.');
        }

        if (strtoupper((string)$existing['role']) === 'SUPERADMIN') $this->assertSuperadminActor();
        if ((int)(SessionManager::id() ?? 0) === $id) {
            throw new \InvalidArgumentException('You cannot disable your own account.');
        }
        $this->protectLastSuperadmin($id, $existing, (string)$existing['role'], 'DISABLED');
        $this->repo->disable($id);

        $this->audit->log(
            'USERS',
            'DISABLE',
            "Disabled user {$existing['username']}"
        );
    }

    public function resetPassword(int $id, array $input): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid user ID.');
        }

        $existing = $this->repo->find($id);

        if (!$existing) {
            throw new \InvalidArgumentException('User not found.');
        }

        if (strtoupper((string)$existing['role']) === 'SUPERADMIN') $this->assertSuperadminActor();

        $password = trim((string)($input['password'] ?? ''));

        if ($password === '') {
            throw new \InvalidArgumentException('New password is required.');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }

        $this->repo->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));

        $this->audit->log(
            'USERS',
            'RESET_PASSWORD',
            "Reset password for user {$existing['username']}"
        );
    }

    public function permissionMatrix(int $id): array
    {
        $user = $this->find($id);
        if (!$user) throw new \InvalidArgumentException('User not found.');
        return ['user' => $user, 'permissions' => $this->authorization->matrix($id)];
    }

    public function updatePermissions(int $id, array $input): void
    {
        $user = $this->find($id);
        if (!$user) throw new \InvalidArgumentException('User not found.');
        if (strtoupper((string)$user['role']) === 'SUPERADMIN') {
            throw new \InvalidArgumentException('Superadmin access is protected and cannot be overridden.');
        }
        $effects = $input['effects'] ?? [];
        if (is_string($effects)) {
            $effects = json_decode($effects, true);
        }
        if (!is_array($effects)) throw new \InvalidArgumentException('Invalid permission overrides.');

        $valid = [];
        foreach ($effects as $permission => $effect) {
            if (!is_string($permission) || !preg_match('/^[a-z0-9-]+\.[a-z]+$/', $permission)) continue;
            $effect = strtoupper((string)$effect);
            if (!in_array($effect, ['INHERIT', 'ALLOW', 'DENY'], true)) continue;
            $valid[$permission] = $effect;
        }
        $actorId = (int)(SessionManager::id() ?? 0);
        $this->authorization->replaceOverrides($id, $valid, $actorId);
        $changed = array_filter($valid, static fn(string $effect): bool => $effect !== 'INHERIT');
        $this->audit->log('USERS', 'UPDATE_PERMISSIONS', sprintf(
            'Updated access overrides for user %s: %d explicit override(s)',
            $user['username'], count($changed)
        ));
    }

    private function protectLastSuperadmin(int $id, array $existing, string $newRole, string $newStatus): void
    {
        if (strtoupper((string)$existing['role']) !== 'SUPERADMIN' || strtoupper((string)$existing['status']) !== 'ACTIVE') return;
        if (strtoupper($newRole) === 'SUPERADMIN' && strtoupper($newStatus) === 'ACTIVE') return;
        if ($this->authorizationRepository->activeSuperadminCountExcluding($id) < 1) {
            throw new \InvalidArgumentException('The final active Superadmin cannot be disabled or downgraded.');
        }
    }

    private function assertSuperadminActor(): void
    {
        $actor = $this->authorizationRepository->userIdentity((int)(SessionManager::id() ?? 0));
        if (!$actor || strtoupper((string)$actor['role']) !== 'SUPERADMIN' || strtoupper((string)$actor['status']) !== 'ACTIVE') {
            throw new \InvalidArgumentException('Only an active Superadmin can create or modify Superadmin accounts.');
        }
    }

    private function validatedData(CreateUsersDTO $dto, bool $requirePassword): array
    {
        $errors = $this->validator->validate($dto, $requirePassword);
        if ($errors !== []) {
            throw new \InvalidArgumentException((string)reset($errors));
        }

        return $dto->toArray();
    }

    private function describeChanges(array $old, array $new): string
    {
        $fields = [
            'username' => 'username',
            'full_name' => 'full name',
            'email' => 'email',
            'role' => 'role',
            'status' => 'status',
        ];

        $changes = [];

        foreach ($fields as $key => $label) {
            $oldValue = (string)($old[$key] ?? '');
            $newValue = (string)($new[$key] ?? '');

            if ($oldValue !== $newValue) {
                $changes[] = "{$label} from '{$oldValue}' to '{$newValue}'";
            }
        }

        return implode(', ', $changes);
    }
}
