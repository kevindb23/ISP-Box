<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

function requireText(string $path, string $text, string $message): void
{
    $source = file_get_contents(BASE_PATH . '/' . $path);
    if ($source === false || !str_contains($source, $text)) {
        throw new RuntimeException($message);
    }
}

requireText(
    'app/Modules/Radius/Repositories/RadiusSettingsRepository.php',
    'getOptionalConnectionConfig',
    'Radius settings repository needs an optional configuration accessor.'
);
requireText(
    'app/Modules/Subscribers/Repositories/SubscriberRepository.php',
    'getOptionalConnectionConfig',
    'Subscribers must not require Radius settings during repository construction.'
);
requireText(
    'app/Modules/Subscribers/Repositories/SubscriberRepository.php',
    'PDOException|RuntimeException',
    'Subscriber online-state lookup must tolerate missing Radius settings.'
);
requireText(
    'app/Modules/SubscriberPlans/Repositories/SubscriberPlansRepository.php',
    'getOptionalConnectionConfig',
    'Subscriber Plans must not require Radius settings during repository construction.'
);

echo "radius_optional_dependency=PASS\n";
