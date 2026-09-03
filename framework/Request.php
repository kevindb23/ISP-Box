<?php

namespace Framework;

class Request
{
    private ?array $json = null;
    private ?string $rawBody = null;

    public function all(): array
    {
        return array_replace($_GET, $_POST, $this->json());
    }

    public function input(): array
    {
        return array_replace($_POST, $this->json());
    }

    public function query(): array
    {
        return $_GET;
    }

    public function files(): array
    {
        return $_FILES;
    }

    public function ip(): ?string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null;
    }

    public function value(string $key, $default = null)
    {
        $input = $this->all();
        return array_key_exists($key, $input) ? $input[$key] : $default;
    }

    public function method(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        return '/' . ltrim((string)$path, '/');
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string)$_SERVER[$key] : $default;
    }

    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');

        if (stripos($contentType, 'application/json') === false) {
            return $this->json = [];
        }

        $raw = $this->rawBody();
        $decoded = json_decode($raw ?: '', true);

        return $this->json = is_array($decoded) ? $decoded : [];
    }

    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = (string)(file_get_contents('php://input') ?: '');
        }
        return $this->rawBody;
    }
}
