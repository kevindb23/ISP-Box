<?php

namespace App\Modules\Subscribers\Repositories;

use Framework\DatabaseConnection;
use PDO;
use PDOException;

class SubscriberRepository
{
    private PDO $db;
    private PDO $radiusDb;

    public function __construct(DatabaseConnection $database)
    {
        $this->db = $database->get();

        $config = require __DIR__ . '/../../../../config/database.php';
        $radius = $config['radius_db'];

        $this->radiusDb = new PDO(
            "mysql:host={$radius['host']};dbname={$radius['name']};charset=utf8mb4",
            $radius['user'],
            $radius['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function getActivePlans(): array
    {
        $stmt = $this->db->query("
            SELECT id, plan_name, plan_type, validity_days, speed_mbps, price
            FROM plans
            WHERE is_active = 1
            ORDER BY plan_name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findPlanById(int $planId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plans
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$planId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function portalUserExists(string $email): bool
    {
        $email = trim($email);

        if ($email === '') {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE username = :email
               OR email = :email
        ");

        $stmt->execute([
            ':email' => $email,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function all(string $search = ''): array
    {
        $search = trim($search);

        $baseSelect = "
            SELECT
                s.id,
                s.account_number,
                s.full_name,
                s.address,
                s.contact_number,
                s.email,
                s.status,
                s.created_at,
                s.updated_at,
                s.deleted_at,

                ss.id AS service_id,
                ss.ppp_username,
                ss.ppp_password,
                ss.plan_id,
                ss.account_type,
                ss.status AS service_status,
                ss.next_due_date,
                ss.expires_at,
                ss.service_number,

                p.plan_name,

                sp.id AS provisioning_id,
                sp.nap_splitter_port,
                sp.ont_serial,
                sp.installed_at,
                sp.nap_port_id,
                sp.ont_id,
                sp.olt_port_id,
                sp.assigned_at,
                sp.cvlan,
                sp.svlan

            FROM subscribers s
            INNER JOIN subscriber_services ss
                ON ss.subscriber_id = s.id
            INNER JOIN plans p
                ON p.id = ss.plan_id
            LEFT JOIN subscriber_provisioning sp
                ON sp.service_id = ss.id

            WHERE s.deleted_at IS NULL
              AND ss.plan_id IS NOT NULL
              AND ss.status IS NOT NULL
        ";

        if ($search !== '') {
            $like = '%' . $search . '%';

            $stmt = $this->db->prepare($baseSelect . "
                AND (
                        CAST(s.account_number AS CHAR) LIKE ?
                     OR s.full_name LIKE ?
                     OR s.email LIKE ?
                     OR s.contact_number LIKE ?
                     OR ss.ppp_username LIKE ?
                     OR CAST(ss.service_number AS CHAR) LIKE ?
                )
                ORDER BY s.id DESC
                LIMIT 500
            ");

            $stmt->execute([$like, $like, $like, $like, $like, $like]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stmt = $this->db->query($baseSelect . "
                ORDER BY s.id DESC
                LIMIT 500
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($rows)) {
            return [];
        }

        return $this->attachOnlineState($rows);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.account_number,
                s.full_name,
                s.address,
                s.contact_number,
                s.email,
                s.status,
                s.created_at,
                s.updated_at,
                s.deleted_at,

                ss.id AS service_id,
                ss.ppp_username,
                ss.ppp_password,
                ss.plan_id,
                ss.account_type,
                ss.status AS service_status,
                ss.next_due_date,
                ss.expires_at,
                ss.service_number,

                p.plan_name,

                sp.id AS provisioning_id,
                sp.nap_splitter_port,
                sp.ont_serial,
                sp.installed_at,
                sp.nap_port_id,
                sp.ont_id,
                sp.olt_port_id,
                sp.assigned_at,
                sp.cvlan,
                sp.svlan

            FROM subscribers s
            INNER JOIN subscriber_services ss
                ON ss.subscriber_id = s.id
            INNER JOIN plans p
                ON p.id = ss.plan_id
            LEFT JOIN subscriber_provisioning sp
                ON sp.service_id = ss.id

            WHERE s.id = ?
              AND s.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $rows = $this->attachOnlineState([$row]);
        return $rows[0] ?? null;
    }

    public function sessionStates(string $search = ''): array
    {
        $search = trim($search);

        $baseSelect = "
            SELECT
                s.id,
                ss.ppp_username
            FROM subscribers s
            INNER JOIN subscriber_services ss
                ON ss.subscriber_id = s.id
            INNER JOIN plans p
                ON p.id = ss.plan_id
            WHERE s.deleted_at IS NULL
              AND ss.plan_id IS NOT NULL
              AND ss.status IS NOT NULL
        ";

        if ($search !== '') {
            $like = '%' . $search . '%';

            $stmt = $this->db->prepare($baseSelect . "
                AND (
                        CAST(s.account_number AS CHAR) LIKE ?
                     OR s.full_name LIKE ?
                     OR s.email LIKE ?
                     OR s.contact_number LIKE ?
                     OR ss.ppp_username LIKE ?
                     OR CAST(ss.service_number AS CHAR) LIKE ?
                )
                ORDER BY s.id DESC
                LIMIT 500
            ");
            $stmt->execute([$like, $like, $like, $like, $like, $like]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stmt = $this->db->query($baseSelect . "
                ORDER BY s.id DESC
                LIMIT 500
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($rows)) {
            return [];
        }

        $rows = $this->attachOnlineState($rows);

        return array_map(static function (array $row): array {
            $online = (int)($row['online'] ?? 0);

            return [
                'id' => (int)($row['id'] ?? 0),
                'ppp_username' => (string)($row['ppp_username'] ?? ''),
                'online' => $online,
                'online_text' => $online === 1 ? 'ONLINE' : 'OFFLINE',
            ];
        }, $rows);
    }

    private function attachOnlineState(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $usernames = [];
        foreach ($rows as $row) {
            if (!empty($row['ppp_username'])) {
                $usernames[] = (string)$row['ppp_username'];
            }
        }

        $usernames = array_values(array_unique($usernames));
        $onlineMap = [];

        if (!empty($usernames)) {
            $placeholders = implode(',', array_fill(0, count($usernames), '?'));

            $stmt = $this->radiusDb->prepare("
                SELECT DISTINCT username
                FROM radacct
                WHERE acctstoptime IS NULL
                  AND username IN ($placeholders)
            ");
            $stmt->execute($usernames);

            $active = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($active as $item) {
                $username = (string)($item['username'] ?? '');
                if ($username !== '') {
                    $onlineMap[$username] = 1;
                }
            }
        }

        foreach ($rows as &$row) {
            $username = (string)($row['ppp_username'] ?? '');
            $online = (int)($onlineMap[$username] ?? 0);
            $row['online'] = $online;
            $row['online_text'] = $online === 1 ? 'ONLINE' : 'OFFLINE';
        }
        unset($row);

        return $rows;
    }

    public function nextAccountNumber(): int
    {
        $stmt = $this->db->query("SELECT COALESCE(MAX(account_number), 1000000000) AS max_no FROM subscribers");
        $max = (int)($stmt->fetchColumn() ?: 1000000000);

        return $max + 1;
    }

    public function nextServiceNumber(): int
    {
        $stmt = $this->db->query("SELECT COALESCE(MAX(service_number), 2000000000) AS max_no FROM subscriber_services");
        $max = (int)($stmt->fetchColumn() ?: 2000000000);

        return $max + 1;
    }

    public function create(
        array $data,
        array $plan,
        string $pppUsername,
        string $pppPassword,
        string $portalUsername,
        string $portalPassword
    ): array {
        $accountNumber = $this->nextAccountNumber();
        $serviceNumber = $this->nextServiceNumber();

        $nextDueDate = null;
        $expiresAt = null;

        if (($plan['plan_type'] ?? 'POSTPAID') === 'POSTPAID') {
            $nextDueDate = date('Y-m-d', strtotime('+30 days'));
        } else {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['validity_days'] . ' days'));
        }

        try {
            $this->db->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Create Subscriber Portal User
            |--------------------------------------------------------------------------
            */

            $stmtUser = $this->db->prepare("
                INSERT INTO users
                (
                    username,
                    password,
                    full_name,
                    email,
                    role,
                    status
                )
                VALUES
                (?, ?, ?, ?, 'SUBSCRIBER', 'ACTIVE')
            ");

            $stmtUser->execute([
                $portalUsername,
                password_hash($portalPassword, PASSWORD_DEFAULT),
                $data['full_name'],
                $portalUsername,
            ]);

            $userId = (int)$this->db->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create Subscriber Profile
            |--------------------------------------------------------------------------
            */

            $stmt1 = $this->db->prepare("
                INSERT INTO subscribers
                (
                    user_id,
                    account_number,
                    full_name,
                    address,
                    contact_number,
                    email,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, 'ACTIVE')
            ");

            $stmt1->execute([
                $userId,
                $accountNumber,
                $data['full_name'],
                $data['address'],
                $data['contact_number'],
                $data['email'],
            ]);

            $subscriberId = (int)$this->db->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create Subscriber Service
            |--------------------------------------------------------------------------
            */

            $stmt2 = $this->db->prepare("
                INSERT INTO subscriber_services
                (
                    subscriber_id,
                    ppp_username,
                    ppp_password,
                    plan_id,
                    account_type,
                    status,
                    next_due_date,
                    expires_at,
                    service_number
                )
                VALUES
                (?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?)
            ");

            $stmt2->execute([
                $subscriberId,
                $pppUsername,
                $pppPassword,
                (int)$plan['id'],
                $plan['plan_type'],
                $nextDueDate,
                $expiresAt,
                $serviceNumber,
            ]);

            $this->db->commit();

            return [
                'ok' => true,
                'user_id' => $userId,
                'subscriber_id' => $subscriberId,
                'account_number' => $accountNumber,
                'service_number' => $serviceNumber,
                'ppp_username' => $pppUsername,
                'ppp_password' => $pppPassword,
                'portal_username' => $portalUsername,
                'portal_password' => $portalPassword,
                'next_due_date' => $nextDueDate,
                'expires_at' => $expiresAt,
            ];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Portal insert failed.',
            ];
        }
    }

    public function updateProfileAndService(int $id, array $data, array $plan): bool
    {
        $stmt1 = $this->db->prepare("
            UPDATE subscribers
            SET
                full_name = ?,
                address = ?,
                contact_number = ?,
                email = ?
            WHERE id = ?
              AND deleted_at IS NULL
        ");

        $ok1 = $stmt1->execute([
            $data['full_name'],
            $data['address'],
            $data['contact_number'],
            $data['email'],
            $id,
        ]);

        $nextDueDate = null;
        $expiresAt = null;

        if (($plan['plan_type'] ?? 'POSTPAID') === 'POSTPAID') {
            $nextDueDate = date('Y-m-d', strtotime('+30 days'));
        } else {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['validity_days'] . ' days'));
        }

        $stmt2 = $this->db->prepare("
            UPDATE subscriber_services
            SET
                plan_id = ?,
                account_type = ?,
                next_due_date = ?,
                expires_at = ?
            WHERE subscriber_id = ?
        ");

        $ok2 = $stmt2->execute([
            (int)$plan['id'],
            $plan['plan_type'],
            $nextDueDate,
            $expiresAt,
            $id,
        ]);

        return $ok1 && $ok2;
    }

    public function updateSubscriberStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE subscribers
            SET status = ?
            WHERE id = ?
              AND deleted_at IS NULL
        ");

        return $stmt->execute([$status, $id]);
    }

    public function updateServiceStatus(int $subscriberId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE subscriber_services
            SET status = ?
            WHERE subscriber_id = ?
        ");

        return $stmt->execute([$status, $subscriberId]);
    }

    public function updatePppPassword(int $subscriberId, string $password): bool
    {
        $stmt = $this->db->prepare("
            UPDATE subscriber_services
            SET ppp_password = ?
            WHERE subscriber_id = ?
        ");

        return $stmt->execute([$password, $subscriberId]);
    }

    public function softDelete(int $subscriberId): bool
    {
        $stmt1 = $this->db->prepare("
            UPDATE subscribers
            SET
                status = 'INACTIVE',
                deleted_at = NOW()
            WHERE id = ?
        ");

        $stmt2 = $this->db->prepare("
            UPDATE subscriber_services
            SET status = 'TERMINATED'
            WHERE subscriber_id = ?
        ");

        return $stmt1->execute([$subscriberId]) && $stmt2->execute([$subscriberId]);
    }

    public function radiusTableExists(string $tableName): bool
    {
        $stmt = $this->radiusDb->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $stmt->execute([$tableName]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function syncRadiusCreate(
        string $username,
        string $password,
        string $planName,
        string $status = 'ACTIVE',
        ?string $expiresAt = null
    ): void {
        $del1 = $this->radiusDb->prepare("
            DELETE FROM radcheck
            WHERE username = ?
              AND attribute = 'Cleartext-Password'
        ");
        $del1->execute([$username]);

        $ins1 = $this->radiusDb->prepare("
            INSERT INTO radcheck (username, attribute, op, value)
            VALUES (?, 'Cleartext-Password', ':=', ?)
        ");
        $ins1->execute([$username, $password]);

        $del2 = $this->radiusDb->prepare("
            DELETE FROM radusergroup
            WHERE username = ?
        ");
        $del2->execute([$username]);

        $ins2 = $this->radiusDb->prepare("
            INSERT INTO radusergroup (username, groupname, priority)
            VALUES (?, ?, 1)
        ");
        $ins2->execute([$username, $planName]);

        if ($this->radiusTableExists('isp_subscribers')) {
            $del3 = $this->radiusDb->prepare("
                DELETE FROM isp_subscribers
                WHERE username = ?
            ");
            $del3->execute([$username]);

            $ins3 = $this->radiusDb->prepare("
                INSERT INTO isp_subscribers (username, status, expires_at, plan)
                VALUES (?, ?, ?, ?)
            ");
            $ins3->execute([$username, $status, $expiresAt, $planName]);
        }
    }

    public function syncRadiusPlan(string $username, string $planName, ?string $expiresAt = null): void
    {
        $del = $this->radiusDb->prepare("
            DELETE FROM radusergroup
            WHERE username = ?
        ");
        $del->execute([$username]);

        $ins = $this->radiusDb->prepare("
            INSERT INTO radusergroup (username, groupname, priority)
            VALUES (?, ?, 1)
        ");
        $ins->execute([$username, $planName]);

        if ($this->radiusTableExists('isp_subscribers')) {
            $stmt = $this->radiusDb->prepare("
                UPDATE isp_subscribers
                SET plan = ?, expires_at = ?
                WHERE username = ?
            ");
            $stmt->execute([$planName, $expiresAt, $username]);
        }
    }

    public function syncRadiusStatus(string $username, string $status, ?string $expiresAt = null): void
    {
        if ($this->radiusTableExists('isp_subscribers')) {
            $stmt = $this->radiusDb->prepare("
                UPDATE isp_subscribers
                SET status = ?, expires_at = ?
                WHERE username = ?
            ");
            $stmt->execute([$status, $expiresAt, $username]);
        }
    }

    public function syncRadiusPassword(string $username, string $password): void
    {
        $del = $this->radiusDb->prepare("
            DELETE FROM radcheck
            WHERE username = ?
              AND attribute = 'Cleartext-Password'
        ");
        $del->execute([$username]);

        $ins = $this->radiusDb->prepare("
            INSERT INTO radcheck (username, attribute, op, value)
            VALUES (?, 'Cleartext-Password', ':=', ?)
        ");
        $ins->execute([$username, $password]);
    }

    public function radiusDelete(string $username): void
    {
        foreach (['radcheck', 'radusergroup'] as $table) {
            $stmt = $this->radiusDb->prepare("DELETE FROM {$table} WHERE username = ?");
            $stmt->execute([$username]);
        }

        if ($this->radiusTableExists('isp_subscribers')) {
            $stmt = $this->radiusDb->prepare("DELETE FROM isp_subscribers WHERE username = ?");
            $stmt->execute([$username]);
        }
    }

    public function resetPortalPassword(int $subscriberId, string $newPassword): array
    {
        $stmt = $this->db->prepare("
        SELECT
            s.id AS subscriber_id,
            s.user_id,
            s.email,
            s.full_name,
            u.username,
            u.email AS user_email,
            u.role,
            u.status AS user_status
        FROM subscribers s
        LEFT JOIN users u
            ON u.id = s.user_id
        WHERE s.id = ?
          AND s.deleted_at IS NULL
        LIMIT 1
    ");

        $stmt->execute([$subscriberId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'ok' => false,
                'message' => 'Subscriber not found.',
            ];
        }

        $userId = (int)($row['user_id'] ?? 0);

        if ($userId <= 0) {
            return [
                'ok' => false,
                'message' => 'Subscriber has no linked portal user account.',
            ];
        }

        $role = strtoupper((string)($row['role'] ?? ''));

        if ($role !== 'SUBSCRIBER') {
            return [
                'ok' => false,
                'message' => 'Linked user account is not a subscriber account.',
            ];
        }

        $stmtUpdate = $this->db->prepare("
        UPDATE users
        SET
            password = ?,
            status = 'ACTIVE',
            updated_at = NOW()
        WHERE id = ?
          AND role = 'SUBSCRIBER'
        LIMIT 1
    ");

        $ok = $stmtUpdate->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $userId,
        ]);

        if (!$ok) {
            return [
                'ok' => false,
                'message' => 'Failed to reset portal password.',
            ];
        }

        return [
            'ok' => true,
            'portal_username' => $row['username'] ?: ($row['user_email'] ?: $row['email']),
        ];
    }
}