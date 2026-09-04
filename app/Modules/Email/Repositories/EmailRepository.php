<?php

namespace App\Modules\Email\Repositories;

use Framework\DatabaseConnection;
use App\Infrastructure\Security\SecretCipher;
use PDO;

class EmailRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection, private SecretCipher $secrets)
    {
        $this->db = $connection->get();
    }

    public function all(): array
    {
        $row = $this->db->query('SELECT * FROM email_settings WHERE id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'preset' => (string)($row['preset'] ?? 'CUSTOM'), 'smtp_host' => (string)($row['smtp_host'] ?? ''),
            'smtp_port' => (int)($row['smtp_port'] ?? 587), 'smtp_encryption' => (string)($row['smtp_encryption'] ?? 'TLS'),
            'smtp_username' => (string)($row['smtp_username'] ?? ''), 'smtp_password_configured' => trim((string)($row['smtp_password_encrypted'] ?? '')) !== '',
            'from_name' => (string)($row['from_name'] ?? ''), 'from_email' => (string)($row['from_email'] ?? ''),
        ];
    }

    public function save(array $data): array
    {
        $current = $this->db->query('SELECT smtp_password_encrypted FROM email_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $password = trim((string)($data['smtp_password'] ?? ''));
        $encryptedPassword = $password !== '' ? $this->secrets->encrypt($password) : ($current['smtp_password_encrypted'] ?? null);
        $stmt = $this->db->prepare(<<<'SQL'
            UPDATE email_settings SET preset = :preset, smtp_host = :host, smtp_port = :port,
                smtp_encryption = :encryption, smtp_username = :username,
                smtp_password_encrypted = :password, from_name = :from_name, from_email = :from_email
            WHERE id = 1
        SQL);
        $stmt->execute([
            'preset' => $data['preset'], 'host' => $data['smtp_host'], 'port' => $data['smtp_port'],
            'encryption' => $data['smtp_encryption'], 'username' => $data['smtp_username'],
            'password' => $encryptedPassword, 'from_name' => $data['from_name'], 'from_email' => $data['from_email'],
        ]);
        return $this->all();
    }

    public function connectionConfig(): array
    {
        $row = $this->db->query('SELECT * FROM email_settings WHERE id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $row['smtp_password'] = $this->secrets->decrypt($row['smtp_password_encrypted'] ?? null) ?? '';
        return $row;
    }
}
