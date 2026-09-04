<?php

namespace App\Modules\Notifications\Repositories;

use Framework\DatabaseConnection;
use App\Infrastructure\Security\SecretCipher;
use PDO;

class NotificationsRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection, private SecretCipher $secrets)
    {
        $this->db = $connection->get();
    }

    public function all(): array
    {
        $row = $this->db->query('SELECT * FROM notification_settings WHERE id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['enabled' => (int)($row['enabled'] ?? 0) === 1, 'telegram_chat_id' => (string)($row['telegram_chat_id'] ?? ''), 'telegram_bot_configured' => trim((string)($row['telegram_bot_token_encrypted'] ?? '')) !== ''];
    }

    public function save(array $data): array
    {
        $current = $this->db->query('SELECT telegram_bot_token_encrypted FROM notification_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $token = trim((string)($data['telegram_bot_token'] ?? ''));
        $encrypted = $token !== '' ? $this->secrets->encrypt($token) : ($current['telegram_bot_token_encrypted'] ?? null);
        $stmt = $this->db->prepare('UPDATE notification_settings SET enabled = :enabled, telegram_bot_token_encrypted = :token, telegram_chat_id = :chat WHERE id = 1');
        $stmt->execute(['enabled' => $data['enabled'] ? 1 : 0, 'token' => $encrypted, 'chat' => $data['telegram_chat_id']]);
        return $this->all();
    }

    public function connectionConfig(): array
    {
        $row = $this->db->query('SELECT * FROM notification_settings WHERE id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $row['telegram_bot_token'] = $this->secrets->decrypt($row['telegram_bot_token_encrypted'] ?? null) ?? '';
        return $row;
    }
}
