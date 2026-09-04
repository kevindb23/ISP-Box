<?php

use Framework\SessionManager;

require_once BASE_PATH . '/app/Core/Branding/branding.php';
require_once BASE_PATH . '/app/Core/UI/navigation.php';

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

$branding = \App\Core\Branding::get();
$breadcrumb = nexusbox_breadcrumb();

?>

<div class="topbar">

    <div class="topbar-left">

        <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle navigation" aria-controls="primarySidebar">
            <i class="bi bi-list"></i>
        </button>


        <nav class="workspace-breadcrumb" aria-label="Current page">
            <span class="workspace-breadcrumb-group"><?= htmlspecialchars($breadcrumb['group']) ?></span>
            <i class="bi bi-chevron-right workspace-breadcrumb-separator" aria-hidden="true"></i>
            <span class="workspace-breadcrumb-current" aria-current="page"><?= htmlspecialchars($breadcrumb['current']) ?></span>
        </nav>

    </div>

    <div class="topbar-right">
        <div class="global-search nx-header-search" role="search">
            <i class="bi bi-search"></i>
            <input id="globalSearchInput" type="search" autocomplete="off" placeholder="Search modules"
                   aria-label="Search accessible modules" aria-controls="globalSearchSuggestions"
                   aria-expanded="false" aria-autocomplete="list">
            <div id="globalSearchSuggestions" class="nx-search-suggestions" role="listbox" hidden></div>
        </div>
        <button type="button" class="topbar-alerts" id="globalNotificationsToggle" aria-label="View notifications" aria-controls="globalNotifications" aria-expanded="false">
            <i class="bi bi-bell topbar-alert-icon" aria-hidden="true"></i>
            <span class="nx-notification-count" id="globalNotificationsCount" aria-live="polite" aria-atomic="true" hidden></span>
            <span>Alerts</span>
        </button>
        <div id="globalNotifications" class="nx-notification-popover" role="dialog" aria-label="Notifications" hidden>
            <div class="nx-notification-header">
                <div>
                    <strong>Notifications</strong>
                    <small>Security events</small>
                </div>
                <button type="button" class="nx-notification-refresh" aria-label="Refresh notifications" title="Refresh notifications"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
            </div>
            <div class="nx-notification-list" role="status" aria-live="polite"><div class="nx-notification-empty">Open notifications to load recent events.</div></div>
        </div>
        <div id="globalNotificationModal" class="nx-notification-modal" role="dialog" aria-modal="true" aria-labelledby="globalNotificationModalTitle" hidden>
            <div class="nx-notification-modal-card">
                <div class="nx-notification-modal-header">
                    <div><small>Security alert</small><strong id="globalNotificationModalTitle">Notification details</strong></div>
                    <button type="button" class="nx-notification-modal-close" aria-label="Close notification details">&times;</button>
                </div>
                <div class="nx-notification-modal-body">
                    <p id="globalNotificationModalDescription"></p>
                    <dl><div><dt>User</dt><dd id="globalNotificationModalUser">—</dd></div><div><dt>IP address</dt><dd id="globalNotificationModalIp">—</dd></div><div><dt>Time</dt><dd id="globalNotificationModalTime">—</dd></div></dl>
                </div>
            </div>
        </div>

        <div class="user-menu">
            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle theme" title="Toggle theme">
                <i class="bi bi-moon-stars"></i>
            </button>

            <form method="post" action="/logout" style="display:contents">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Security\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="logout-top" aria-label="Log out" title="Log out">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </button>
            </form>

        </div>

    </div>

</div>
