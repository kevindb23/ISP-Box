<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Repositories\UsersRepository;
use App\Modules\Audit\Services\AuditService;

class UsersService
{
    private UsersRepository $repo;
    private AuditService $audit;

    private array $allowedRoles = [
        'SUPERADMIN',
        'NOC',
        'SUPPORT',
        'BILLING',
        'TECHNICIAN',
    ];

    private array $allowedStatuses = [
        'ACTIVE',
        'DISABLED',
    ];

    public function __construct(
        UsersRepository $repo,
        AuditService $audit
    ) {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    public function list(): array
    {
        return $this->repo->all();
    }

    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function create(array $input): int
    {
        $data = $this->validate($input, true);

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

        $data = $this->validate($input, false);

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

    private function validate(array $input, bool $requirePassword): array
    {
        $username = trim((string)($input['username'] ?? ''));
        $fullName = trim((string)($input['full_name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $role = strtoupper(trim((string)($input['role'] ?? 'SUPPORT')));
        $status = strtoupper(trim((string)($input['status'] ?? 'ACTIVE')));
        $password = trim((string)($input['password'] ?? ''));

        if ($username === '') {
            throw new \InvalidArgumentException('Username is required.');
        }

        if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username)) {
            throw new \InvalidArgumentException('Username must be 3-64 characters and may contain letters, numbers, dot, dash, or underscore only.');
        }

        if ($fullName === '') {
            throw new \InvalidArgumentException('Full name is required.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        if (!in_array($role, $this->allowedRoles, true)) {
            throw new \InvalidArgumentException('Invalid system role.');
        }

        if (!in_array($status, $this->allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid user status.');
        }

        if ($requirePassword) {
            if ($password === '') {
                throw new \InvalidArgumentException('Password is required.');
            }

            if (strlen($password) < 8) {
                throw new \InvalidArgumentException('Password must be at least 8 characters.');
            }
        }

        return [
            'username' => $username,
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'role' => $role,
            'status' => $status,
            'password' => $password,
        ];
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