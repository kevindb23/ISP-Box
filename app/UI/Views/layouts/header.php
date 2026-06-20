<?php

use Framework\SessionManager;

$user = SessionManager::user();

$username = trim((string)($user['username'] ?? 'Admin'));
$fullName = trim((string)($user['full_name'] ?? ''));
$email = trim((string)($user['email'] ?? ''));
$role = strtoupper((string)($user['role'] ?? 'ADMINISTRATOR'));

$isSubscriber = $role === 'SUBSCRIBER';

$displayName = $username !== '' ? $username : 'Admin';
$displaySubtext = $role;

if ($isSubscriber) {
    $displayName = $fullName !== '' ? $fullName : $username;
    $displaySubtext = $email !== '' ? $email : $username;
}

$avatarSource = $displayName !== '' ? $displayName : $username;
$avatarText = strtoupper(substr($avatarSource, 0, 1));

$branding = require BASE_PATH . '/config/branding.php';

?>

<div class="topbar">

    <div class="topbar-left">

        <div class="portal-brand">

            <div class="portal-icon">
                <i class="bi bi-gear-fill"></i>
            </div>

            <div class="portal-title">

                <span class="portal-name">
                    <?= htmlspecialchars($branding['client_name'] ?? 'ISP-in-a-Box') ?>
                </span>

            </div>

        </div>

    </div>

    <div class="topbar-right">

        <div class="user-menu">

            <div class="user-avatar">
                <?= htmlspecialchars($avatarText) ?>
            </div>

            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($displayName) ?></div>
                <div class="user-role"><?= htmlspecialchars($displaySubtext) ?></div>
            </div>

            <a href="/logout" class="logout-top">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>

        </div>

    </div>

</div>