$token = bin2hex(random_bytes(32));

$this->tokenRepository->create($user['id'], $token);

$this->json([
    "success" => true,
    "token" => $token
]);
