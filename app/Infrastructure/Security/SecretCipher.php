<?php

namespace App\Infrastructure\Security;

use RuntimeException;

final class SecretCipher
{
    private const PREFIX = 'enc:v1:';
    private ?string $key = null;

    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') return $plaintext;
        if ($this->isEncrypted($plaintext)) return $plaintext;
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, self::PREFIX, $nonce, $this->key());
        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '' || !$this->isEncrypted($value)) return $value;
        $decoded = base64_decode(substr($value, strlen(self::PREFIX)), true);
        $nonceBytes = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($decoded === false || strlen($decoded) <= $nonceBytes) throw new RuntimeException('Stored secret ciphertext is malformed.');
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($decoded, $nonceBytes), self::PREFIX, substr($decoded, 0, $nonceBytes), $this->key());
        if ($plaintext === false) throw new RuntimeException('Stored secret could not be decrypted.');
        return $plaintext;
    }

    public function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    private function key(): string
    {
        if ($this->key !== null) return $this->key;
        $encoded = trim((string)(getenv('APP_SECRET_KEY') ?: ''));
        $path = BASE_PATH . '/.env.secret-key';
        if ($encoded === '' && is_file($path) && is_readable($path)) $encoded = trim((string)file_get_contents($path));
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new RuntimeException('APP_SECRET_KEY is missing or invalid. Generate a protected 32-byte base64 key before storing secrets.');
        }
        return $this->key = $key;
    }
}
