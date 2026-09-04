<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationsRepository;

class NotificationsService
{
    private NotificationsRepository $repo;

    public function __construct(NotificationsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function summary(): array
    {
        return ['module' => 'Notifications', 'settings' => $this->repo->all()];
    }

    public function save(array $input): array
    {
        $data = [
            'enabled' => filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'telegram_bot_token' => trim((string)($input['telegram_bot_token'] ?? '')),
            'telegram_chat_id' => trim((string)($input['telegram_chat_id'] ?? '')),
        ];
        if ($data['enabled'] && $data['telegram_bot_token'] === '' && !$this->repo->all()['telegram_bot_configured']) throw new \InvalidArgumentException('Add a Telegram bot token before enabling notifications.');
        if ($data['enabled'] && $data['telegram_chat_id'] === '') throw new \InvalidArgumentException('Add a Telegram chat ID before enabling notifications.');
        return $this->repo->save($data);
    }

    public function testTelegram(): array
    {
        $config = $this->repo->connectionConfig();
        if ($config['telegram_bot_token'] === '' || $config['telegram_chat_id'] === '') throw new \RuntimeException('Configure the Telegram bot token and chat ID first.');
        $url = 'https://api.telegram.org/bot' . rawurlencode($config['telegram_bot_token']) . '/sendMessage';
        $payload = json_encode(['chat_id' => $config['telegram_chat_id'], 'text' => 'NexusBox notification test'], JSON_THROW_ON_ERROR);
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => 8, 'ignore_errors' => true]]);
        $response = @file_get_contents($url, false, $context);
        $result = is_string($response) ? json_decode($response, true) : null;
        if (!is_array($result) || empty($result['ok'])) throw new \RuntimeException('Telegram test notification failed.');
        return ['sent' => true];
    }
}
