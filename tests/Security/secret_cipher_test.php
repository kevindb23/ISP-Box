<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/Infrastructure/Security/SecretCipher.php';

use App\Infrastructure\Security\SecretCipher;

$cipher = new SecretCipher();
$plaintext = 'test-secret-' . bin2hex(random_bytes(8));
$encrypted = $cipher->encrypt($plaintext);
if ($encrypted === $plaintext || !$cipher->isEncrypted($encrypted)) throw new RuntimeException('Secret was not encrypted.');
if ($cipher->decrypt($encrypted) !== $plaintext) throw new RuntimeException('Secret did not round-trip.');
if ($cipher->decrypt('legacy-plaintext') !== 'legacy-plaintext') throw new RuntimeException('Legacy transition read failed.');
echo "secret_cipher=PASS\n";
