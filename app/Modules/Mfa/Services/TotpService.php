<?php

namespace App\Modules\Mfa\Services;

final class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 20): string
    {
        $bytes = random_bytes($length);
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            $secret .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return rtrim($secret, '=');
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6 || !preg_match('/^[A-Z2-7]+$/', $secret)) return false;

        $timestamp ??= time();
        foreach ([-1, 0, 1] as $offset) {
            $expected = $this->code($secret, intdiv($timestamp, 30) + $offset);
            if (hash_equals($expected, $code)) return true;
        }

        return false;
    }

    public function code(string $secret, int $counter): string
    {
        $key = $this->decode($secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function otpauthUri(string $secret, string $label, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    private function decode(string $secret): string
    {
        $secret = strtoupper(rtrim($secret, '='));
        $bits = '';
        foreach (str_split($secret) as $character) {
            $position = strpos(self::ALPHABET, $character);
            if ($position === false) throw new \InvalidArgumentException('Invalid TOTP secret.');
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $result .= chr(bindec($byte));
        }
        return $result;
    }
}
