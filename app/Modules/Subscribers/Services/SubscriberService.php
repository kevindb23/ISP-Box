<?php

namespace App\Modules\Subscribers\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Subscribers\DTOs\CreateSubscriberDTO;
use App\Modules\Subscribers\DTOs\UpdateSubscriberDTO;
use App\Modules\Subscribers\Entities\Subscriber;
use App\Modules\Subscribers\Repositories\SubscriberRepository;
use App\Modules\Subscribers\Validators\CreateSubscriberValidator;
use App\Modules\Subscribers\Validators\UpdateSubscriberValidator;

class SubscriberService
{
    private SubscriberRepository $repo;
    private AuditService $audit;

    public function __construct(
        SubscriberRepository $repo,
        AuditService $audit
    ) {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    public function getAll(string $search = ''): array
    {
        $rows = $this->repo->all($search);

        return array_map(function ($row) {
            return (new Subscriber($row))->toArray();
        }, $rows);
    }

    public function getSessionStates(string $search = ''): array
    {
        return $this->repo->sessionStates($search);
    }

    public function getPlans(): array
    {
        return $this->repo->getActivePlans();
    }

    public function create(array $input): array
    {
        $dto = new CreateSubscriberDTO($input);
        $payload = $dto->toArray();

        $errors = CreateSubscriberValidator::validate($payload);
        if (!empty($errors)) {
            return ['ok' => false, 'message' => $errors[0]];
        }

        $plan = $this->repo->findPlanById((int)$payload['plan_id']);
        if (!$plan) {
            return ['ok' => false, 'message' => 'Invalid plan selected.'];
        }

        $email = trim((string)($payload['email'] ?? ''));

        if ($email === '') {
            return ['ok' => false, 'message' => 'Subscriber email is required for portal login.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Subscriber email is not valid.'];
        }

        if ($this->repo->portalUserExists($email)) {
            return ['ok' => false, 'message' => 'A portal user already exists with this email.'];
        }

        $serviceNumber = $this->repo->nextServiceNumber();
        $pppUsername = sprintf('CST%07d', $serviceNumber);
        $pppPassword = $this->genPassword(10);

        $portalUsername = $email;
        $portalPassword = $this->genPassword(12);

        $created = $this->repo->create(
            $payload,
            $plan,
            $pppUsername,
            $pppPassword,
            $portalUsername,
            $portalPassword
        );

        if (!$created['ok']) {
            return $created;
        }

        try {
            $this->repo->syncRadiusCreate(
                $pppUsername,
                $pppPassword,
                $plan['plan_name'],
                'ACTIVE',
                $created['expires_at'] ?? null
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Radius provisioning failed.'];
        }

        $subscriberName = $this->subscriberName($payload);

        $this->audit->log(
            'SUBSCRIBERS',
            'CREATE',
            "Created subscriber {$subscriberName} with account {$created['account_number']} and service {$created['service_number']} on plan {$plan['plan_name']}"
        );

        return [
            'ok' => true,
            'message' => 'Subscriber created.',
            'ppp_username' => $pppUsername,
            'ppp_password' => $pppPassword,
            'portal_username' => $portalUsername,
            'portal_password' => $portalPassword,
            'account_number' => $created['account_number'] ?? null,
            'service_number' => $created['service_number'] ?? null,
            'subscriber_id' => $created['subscriber_id'] ?? null,
            'user_id' => $created['user_id'] ?? null,
        ];
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'Subscriber not found.'];
        }

        $dto = new UpdateSubscriberDTO($input);
        $payload = $dto->toArray();

        $errors = UpdateSubscriberValidator::validate($payload);
        if (!empty($errors)) {
            return ['ok' => false, 'message' => $errors[0]];
        }

        $plan = $this->repo->findPlanById((int)$payload['plan_id']);
        if (!$plan) {
            return ['ok' => false, 'message' => 'Invalid plan selected.'];
        }

        $ok = $this->repo->updateProfileAndService($id, $payload, $plan);
        if (!$ok) {
            return ['ok' => false, 'message' => 'Failed to update subscriber.'];
        }

        try {
            $expiresAt = null;

            if (($plan['plan_type'] ?? 'POSTPAID') === 'PREPAID') {
                $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['validity_days'] . ' days'));
            }

            $this->repo->syncRadiusPlan(
                $existing['ppp_username'],
                $plan['plan_name'],
                $expiresAt
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Radius update failed.'];
        }

        $subscriberName = $this->subscriberName($existing);
        $changes = $this->describeSubscriberChanges($existing, $payload, $plan);

        $this->audit->log(
            'SUBSCRIBERS',
            'UPDATE',
            $changes !== ''
                ? "Updated subscriber {$subscriberName}: {$changes}"
                : "Updated subscriber {$subscriberName}"
        );

        return ['ok' => true, 'message' => 'Subscriber updated.'];
    }

    public function suspend(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'Subscriber not found.'];
        }

        $this->repo->updateSubscriberStatus($id, 'INACTIVE');
        $this->repo->updateServiceStatus($id, 'SUSPENDED');

        try {
            $this->repo->syncRadiusStatus(
                $existing['ppp_username'],
                'SUSPENDED',
                $existing['expires_at'] ?? null
            );
            $this->disconnect($existing['ppp_username']);
        } catch (\Throwable $e) {
        }

        $this->audit->log(
            'SUBSCRIBERS',
            'SUSPEND',
            "Suspended subscriber {$this->subscriberName($existing)} with PPP username {$existing['ppp_username']}"
        );

        return ['ok' => true, 'message' => 'Subscriber suspended.'];
    }

    public function reactivate(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'Subscriber not found.'];
        }

        $this->repo->updateSubscriberStatus($id, 'ACTIVE');
        $this->repo->updateServiceStatus($id, 'ACTIVE');

        try {
            $this->repo->syncRadiusStatus(
                $existing['ppp_username'],
                'ACTIVE',
                $existing['expires_at'] ?? null
            );
        } catch (\Throwable $e) {
        }

        $this->audit->log(
            'SUBSCRIBERS',
            'REACTIVATE',
            "Reactivated subscriber {$this->subscriberName($existing)} with PPP username {$existing['ppp_username']}"
        );

        return ['ok' => true, 'message' => 'Subscriber reactivated.'];
    }

    public function resetPassword(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'Subscriber not found.'];
        }

        $newPassword = $this->genPassword(10);

        $this->repo->updatePppPassword($id, $newPassword);

        try {
            $this->repo->syncRadiusPassword($existing['ppp_username'], $newPassword);
            $this->disconnect($existing['ppp_username']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Radius password update failed.'];
        }

        $this->audit->log(
            'SUBSCRIBERS',
            'RESET_PPP_PASSWORD',
            "Reset PPP password for subscriber {$this->subscriberName($existing)} with username {$existing['ppp_username']}"
        );

        return [
            'ok' => true,
            'message' => 'PPP password reset.',
            'ppp_password' => $newPassword,
        ];
    }

    public function delete(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'Subscriber not found.'];
        }

        $this->repo->softDelete($id);

        try {
            $this->disconnect($existing['ppp_username']);
            $this->repo->radiusDelete($existing['ppp_username']);
        } catch (\Throwable $e) {
        }

        $this->audit->log(
            'SUBSCRIBERS',
            'DELETE',
            "Deleted subscriber {$this->subscriberName($existing)} with PPP username {$existing['ppp_username']}"
        );

        return ['ok' => true, 'message' => 'Subscriber deleted.'];
    }

    private function disconnect(string $username): void
    {
        $config = require __DIR__ . '/../../../../config/database.php';

        $coa = $config['coa'] ?? null;
        if (!$coa || empty($username)) {
            return;
        }

        $host = escapeshellarg((string)$coa['host']);
        $port = (int)($coa['port'] ?? 3799);
        $secret = escapeshellarg((string)$coa['secret']);
        $radclient = escapeshellcmd((string)$coa['radclient_path']);
        $payload = "User-Name={$username}\nAcct-Session-Id=1";

        $cmd = "printf %s " . escapeshellarg($payload) . " | {$radclient} -x {$host}:{$port} disconnect {$secret} >/dev/null 2>&1";
        @shell_exec($cmd);
    }

    private function genPassword(int $length = 10): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }

    public function getById(int $id): ?array
    {
        $row = $this->repo->findById($id);
        if (!$row) {
            return null;
        }

        return (new Subscriber($row))->toArray();
    }

    public function resetPortalPassword(int $id): array
    {
        $existing = $this->repo->findById($id);

        if (!$existing) {
            return ['ok' => false, 'message' => 'Subscriber not found.'];
        }

        $newPassword = $this->genPassword(12);

        $updated = $this->repo->resetPortalPassword($id, $newPassword);

        if (!$updated['ok']) {
            return $updated;
        }

        $this->audit->log(
            'SUBSCRIBERS',
            'RESET_PORTAL_PASSWORD',
            "Reset portal password for subscriber {$this->subscriberName($existing)}"
        );

        return [
            'ok' => true,
            'message' => 'Subscriber portal password reset.',
            'portal_username' => $updated['portal_username'] ?? null,
            'portal_password' => $newPassword,
        ];
    }

    private function subscriberName(array $row): string
    {
        $fullName = trim((string)($row['full_name'] ?? ''));

        if ($fullName !== '') {
            return $fullName;
        }

        $parts = array_filter([
            $row['first_name'] ?? '',
            $row['middle_name'] ?? '',
            $row['last_name'] ?? '',
        ]);

        $name = trim(implode(' ', $parts));

        if ($name !== '') {
            return $name;
        }

        return (string)($row['account_number'] ?? $row['ppp_username'] ?? 'Unknown Subscriber');
    }

    private function describeSubscriberChanges(array $old, array $new, array $plan): string
    {
        $changes = [];

        $fields = [
            'first_name' => 'first name',
            'middle_name' => 'middle name',
            'last_name' => 'last name',
            'email' => 'email',
            'mobile' => 'mobile',
            'address' => 'address',
        ];

        foreach ($fields as $key => $label) {
            $oldValue = trim((string)($old[$key] ?? ''));
            $newValue = trim((string)($new[$key] ?? ''));

            if ($newValue !== '' && $oldValue !== $newValue) {
                $changes[] = "{$label} from '{$oldValue}' to '{$newValue}'";
            }
        }

        $oldPlanId = (int)($old['plan_id'] ?? 0);
        $newPlanId = (int)($new['plan_id'] ?? 0);

        if ($newPlanId > 0 && $oldPlanId !== $newPlanId) {
            $changes[] = "plan changed to '{$plan['plan_name']}'";
        }

        return implode(', ', $changes);
    }
}