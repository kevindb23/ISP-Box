<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/.env.secret-key';
if (is_file($path)) {
    fwrite(STDERR, "Secret key already exists; refusing to replace it.\n");
    exit(1);
}
$encoded = base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)) . PHP_EOL;
if (file_put_contents($path, $encoded, LOCK_EX) === false) throw new RuntimeException('Unable to write application secret key.');
chmod($path, 0600);
echo "Generated protected application secret key. Back it up outside the web root.\n";
