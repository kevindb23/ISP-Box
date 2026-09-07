<?php

use Framework\SessionManager;

require_once BASE_PATH . '/app/Core/Branding/branding.php';

$branding = \App\Core\Branding::get();
$brandCompanyName = trim((string)($branding['company_name'] ?? $branding['client_name'] ?? 'ISP-In-A-BOX'));
$brandPoweredBy = trim((string)($branding['powered_by'] ?? '1WAN'));

$user = SessionManager::user();

$username = trim((string)($user['username'] ?? 'Admin'));
$fullName = trim((string)($user['full_name'] ?? ''));
$email = trim((string)($user['email'] ?? ''));
$role = strtoupper((string)($user['role'] ?? 'ADMINISTRATOR'));

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $path);
$section = $segments[0] ?? '';

$isSubscriber = $role === 'SUBSCRIBER';
$isTechnician = $role === 'TECHNICIAN';
$isBilling = $role === 'BILLING';
$isNoc = $role === 'NOC';
$isSupport = $role === 'SUPPORT';
$canAccessRadius = in_array($role, ['ADMIN', 'ADMINISTRATOR', 'SUPERADMIN', 'NOC'], true);

$displayName = $username !== '' ? $username : 'Admin';
$displaySubtext = $role;

if ($isSubscriber || $isTechnician || $isBilling || $isNoc || $isSupport) {
    $displayName = $fullName !== '' ? $fullName : $username;
}

if ($isSubscriber) {
    $displaySubtext = $email !== '' ? $email : $username;
}

if ($isTechnician) {
    $displaySubtext = 'TECHNICIAN';
}

if ($isBilling) {
    $displaySubtext = 'BILLING';
}

if ($isNoc) {
    $displaySubtext = 'NOC';
}

if ($isSupport) {
    $displaySubtext = 'SUPPORT';
}

$avatarSource = $displayName !== '' ? $displayName : $username;
$avatarText = strtoupper(substr($avatarSource, 0, 1));

?>

<aside class="sidebar" id="primarySidebar" aria-label="Primary navigation">

    <div class="sidebar-header">
        <div class="sidebar-logo">
            <?php if (!empty($branding['logo_path'])): ?>
                <img
                    src="<?= htmlspecialchars($branding['logo_path']) ?>"
                    alt="<?= htmlspecialchars($brandCompanyName) ?> logo"
                    class="sidebar-brand-logo"
                    data-brand-logo
                >
            <?php else: ?>
                <i class="bi bi-hdd-network" data-brand-logo aria-hidden="true"></i>
            <?php endif; ?>

            <div class="logo-text">
                <div class="logo-title" data-brand-company-name><?= htmlspecialchars($brandCompanyName) ?></div>
                <div class="logo-subtitle brand-powered-label">Powered by <?= htmlspecialchars($brandPoweredBy) ?></div>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">

        <?php
        $rbacNavigation = [
            'Operations' => [
                ['dashboard', '/dashboard', 'bi-speedometer2', 'Dashboard'],
                ['subscriber-plans', '/subscriber-plans', 'bi-card-checklist', 'Plans'],
                ['subscribers', '/subscribers', 'bi-people', 'Subscribers'],
                ['subscriber-portal', '/subscriber-portal', 'bi-person-vcard', 'My Account', ['SUBSCRIBER']],
                ['subscriber-portal', '/subscriber-portal/services', 'bi-wifi', 'My Services', ['SUBSCRIBER']],
                ['subscriber-portal', '/subscriber-portal/invoices', 'bi-receipt', 'My Invoices', ['SUBSCRIBER']],
                ['subscriber-portal', '/subscriber-portal/payments', 'bi-credit-card', 'My Payments', ['SUBSCRIBER']],
                ['subscriber-portal', '/subscriber-portal/tickets', 'bi-ticket-detailed', 'My Tickets', ['SUBSCRIBER']],
                ['subscriber-portal', '/subscriber-portal/security', 'bi-shield-lock', 'Security', ['SUBSCRIBER']],
            ],
            'Network' => [
                ['bng', '/bng', 'bi-router', 'BNG'], ['routers', '/routers', 'bi-signpost-split', 'Routers'],
                ['cgnat', '/cgnat', 'bi-diagram-3', 'CGNAT'], ['vlan-management', '/vlan-management', 'bi-tags', 'VLAN'],
                ['radius', '/radius', 'bi-shield-lock', 'RADIUS'], ['olt-management', '/olt-management', 'bi-hdd-rack', 'OLT'],
                ['nap-management', '/nap-management', 'bi-bezier2', 'NAP'], ['ont-devices', '/ont-devices', 'bi-router', 'ONT'],
                ['service-provisioning', '/service-provisioning', 'bi-gear-wide-connected', 'Provisioning'],
            ],
            'Billing' => [
                ['billing', '/billing', 'bi-receipt', 'Billing'],
                ['payment-gateway', '/payment-gateway', 'bi-credit-card-2-front', 'Payment Gateway'],
            ],
            'Support Ticket' => [
                ['tickets', '/tickets', 'bi-ticket-detailed', 'Tickets'],
            ],
            'Workforce' => [
                ['staff-attendance', '/staff-attendance', 'bi-clock-history', 'Attendance'],
                ['technician-management', '/technician-management', 'bi-person-workspace', 'Technicians'],
                ['technician-portal', '/technician-portal', 'bi-clipboard-check', 'My Work Orders', ['TECHNICIAN']],
                ['work-orders', '/work-orders', 'bi-clipboard-check', 'Work Orders'],
            ],
            'Admin' => [
                ['users', '/users', 'bi-person-gear', 'Users'], ['audit', '/audit', 'bi-shield-check', 'Audit Logs'],
                ['branding', '/branding', 'bi-palette', 'Branding'], ['system-settings', '/system-settings', 'bi-sliders', 'System Settings'],
                ['api-tokens', '/api-tokens', 'bi-key', 'API Tokens'], ['email', '/email', 'bi-envelope-at', 'Email'],
                ['notifications', '/notifications', 'bi-bell', 'Notifications'],
            ],
            'Maintenance' => [
                ['scheduled-downtime', '/scheduled-downtime', 'bi-calendar2-week', 'Scheduled Downtime'],
                ['system-maintenance', '/system-maintenance', 'bi-cone-striped', 'System Maintenance'],
            ],
            'Security' => [
                ['mfa', '/mfa', 'bi-shield-lock', 'MFA', ['ADMINISTRATOR', 'SUPERADMIN']],
            ],
        ];
        foreach ($rbacNavigation as $group => $items):
            $groupId = 'sidebar-group-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($group));
            $visibleItems = array_values(array_filter($items, static function (array $item) use ($role): bool {
                $audience = $item[4] ?? null;
                if (is_array($audience) && !in_array($role, $audience, true)) return false;
                return \App\Core\Authorization\AuthorizationContext::can($item[0] . '.view');
            }));
            if ($visibleItems === []) continue;
        ?>
            <li class="menu-section">
                <button type="button" class="menu-section-toggle" aria-expanded="true" data-sidebar-section="<?= htmlspecialchars($groupId) ?>">
                    <span><?= htmlspecialchars($group) ?></span>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
            </li>
            <?php foreach ($visibleItems as [$moduleKey, $href, $icon, $label]): ?>
                <?php $isNavigationActive = $moduleKey === 'subscriber-portal'
                    ? '/' . $path === rtrim($href, '/')
                    : $section === $moduleKey; ?>
                <li class="menu-item <?= $isNavigationActive ? 'active' : '' ?>" data-sidebar-section-item="<?= htmlspecialchars($groupId) ?>">
                    <a href="<?= htmlspecialchars($href) ?>">
                        <i class="bi <?= htmlspecialchars($icon) ?>"></i><span><?= htmlspecialchars($label) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <?php if (false): // Legacy role menus retained temporarily for migration reference. ?>
        <?php if ($isSubscriber): ?>

            <?php
            $subscriberSubsection = $segments[1] ?? '';
            $isAccountPage = $section === 'subscriber-portal' && ($subscriberSubsection === '' || $subscriberSubsection === 'account');
            $isServicesPage = $section === 'subscriber-portal' && $subscriberSubsection === 'services';
            $isInvoicesPage = $section === 'subscriber-portal' && $subscriberSubsection === 'invoices';
            $isPaymentsPage = $section === 'subscriber-portal' && $subscriberSubsection === 'payments';
            $isTicketsPage = $section === 'subscriber-portal' && $subscriberSubsection === 'tickets';
            ?>

            <li class="menu-item <?= $isAccountPage ? 'active' : '' ?>">
                <a href="/subscriber-portal">
                    <i class="bi bi-person-vcard"></i>
                    <span>My Account</span>
                </a>
            </li>

            <li class="menu-item <?= $isServicesPage ? 'active' : '' ?>">
                <a href="/subscriber-portal/services">
                    <i class="bi bi-wifi"></i>
                    <span>My Services</span>
                </a>
            </li>

            <li class="menu-item <?= $isInvoicesPage ? 'active' : '' ?>">
                <a href="/subscriber-portal/invoices">
                    <i class="bi bi-receipt"></i>
                    <span>My Invoices</span>
                </a>
            </li>

            <li class="menu-item <?= $isPaymentsPage ? 'active' : '' ?>">
                <a href="/subscriber-portal/payments">
                    <i class="bi bi-credit-card"></i>
                    <span>My Payments</span>
                </a>
            </li>

            <li class="menu-item <?= $isTicketsPage ? 'active' : '' ?>">
                <a href="/subscriber-portal/tickets">
                    <i class="bi bi-ticket-detailed"></i>
                    <span>My Tickets</span>
                </a>
            </li>

        <?php elseif ($isBilling): ?>

            <li class="menu-item <?= $section === 'staff-attendance' ? 'active' : '' ?>">
                <a href="/staff-attendance">
                    <i class="bi bi-clock-history"></i>
                    <span>My Attendance</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'billing' ? 'active' : '' ?>">
                <a href="/billing">
                    <i class="bi bi-receipt"></i>
                    <span>Billing</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'tickets' ? 'active' : '' ?>">
                <a href="/tickets">
                    <i class="bi bi-ticket-detailed"></i>
                    <span>My Tickets</span>
                </a>
            </li>

        <?php elseif ($isNoc): ?>

            <li class="menu-item <?= $section === 'staff-attendance' ? 'active' : '' ?>">
                <a href="/staff-attendance">
                    <i class="bi bi-clock-history"></i>
                    <span>My Attendance</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'tickets' ? 'active' : '' ?>">
                <a href="/tickets">
                    <i class="bi bi-ticket-detailed"></i>
                    <span>My Tickets</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'work-orders' ? 'active' : '' ?>">
                <a href="/work-orders">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Work Orders</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'subscriber-plans' ? 'active' : '' ?>">
                <a href="/subscriber-plans">
                    <i class="bi bi-card-checklist"></i>
                    <span>Plans</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'vlan-management' ? 'active' : '' ?>">
                <a href="/vlan-management">
                    <i class="bi bi-tags"></i>
                    <span>VLAN</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'bng' ? 'active' : '' ?>">
                <a href="/bng">
                    <i class="bi bi-router"></i>
                    <span>BNG</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'routers' ? 'active' : '' ?>">
                <a href="/routers">
                    <i class="bi bi-signpost-split"></i>
                    <span>Routers</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'cgnat' ? 'active' : '' ?>">
                <a href="/cgnat">
                    <i class="bi bi-diagram-3"></i>
                    <span>CGNAT</span>
                </a>
            </li>

            <?php if ($canAccessRadius): ?>
                <li class="menu-item <?= $section === 'radius' ? 'active' : '' ?>">
                    <a href="/radius">
                        <i class="bi bi-shield-lock"></i>
                        <span>RADIUS</span>
                    </a>
                </li>
            <?php endif; ?>

            <li class="menu-item <?= $section === 'olt-management' ? 'active' : '' ?>">
                <a href="/olt-management">
                    <i class="bi bi-hdd-rack"></i>
                    <span>OLT</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'nap-management' ? 'active' : '' ?>">
                <a href="/nap-management">
                    <i class="bi bi-bezier2"></i>
                    <span>NAP</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'ont-devices' ? 'active' : '' ?>">
                <a href="/ont-devices">
                    <i class="bi bi-router"></i>
                    <span>ONT</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'service-provisioning' ? 'active' : '' ?>">
                <a href="/service-provisioning">
                    <i class="bi bi-gear-wide-connected"></i>
                    <span>Provisioning</span>
                </a>
            </li>

        <?php elseif ($isSupport): ?>

            <li class="menu-item <?= $section === 'staff-attendance' ? 'active' : '' ?>">
                <a href="/staff-attendance">
                    <i class="bi bi-clock-history"></i>
                    <span>My Attendance</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'subscribers' ? 'active' : '' ?>">
                <a href="/subscribers">
                    <i class="bi bi-people"></i>
                    <span>Subscribers</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'tickets' ? 'active' : '' ?>">
                <a href="/tickets">
                    <i class="bi bi-ticket-detailed"></i>
                    <span>My Tickets</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'work-orders' ? 'active' : '' ?>">
                <a href="/work-orders">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Work Orders</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'technician-management' ? 'active' : '' ?>">
                <a href="/technician-management">
                    <i class="bi bi-person-workspace"></i>
                    <span>Technician Management</span>
                </a>
            </li>

        <?php elseif ($isTechnician): ?>

            <li class="menu-item <?= $section === 'staff-attendance' ? 'active' : '' ?>">
                <a href="/staff-attendance">
                    <i class="bi bi-clock-history"></i>
                    <span>My Attendance</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'technician-portal' ? 'active' : '' ?>">
                <a href="/technician-portal">
                    <i class="bi bi-clipboard-check"></i>
                    <span>My Work Orders</span>
                </a>
            </li>

        <?php else: ?>

            <li class="menu-section">Operations</li>
            <li class="menu-item <?= ($section === 'dashboard' || $section === '') ? 'active' : '' ?>">
                <a href="/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'subscriber-plans' ? 'active' : '' ?>">
                <a href="/subscriber-plans">
                    <i class="bi bi-card-checklist"></i>
                    <span>Plans</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'subscribers' ? 'active' : '' ?>">
                <a href="/subscribers">
                    <i class="bi bi-people"></i>
                    <span>Subscribers</span>
                </a>
            </li>

            <li class="menu-section">Network</li>
            <li class="menu-item <?= $section === 'bng' ? 'active' : '' ?>">
                <a href="/bng">
                    <i class="bi bi-router"></i>
                    <span>BNG</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'routers' ? 'active' : '' ?>">
                <a href="/routers">
                    <i class="bi bi-signpost-split"></i>
                    <span>Routers</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'cgnat' ? 'active' : '' ?>">
                <a href="/cgnat">
                    <i class="bi bi-diagram-3"></i>
                    <span>CGNAT</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'vlan-management' ? 'active' : '' ?>">
                <a href="/vlan-management">
                    <i class="bi bi-tags"></i>
                    <span>VLAN</span>
                </a>
            </li>

            <?php if ($canAccessRadius): ?>
                <li class="menu-item <?= $section === 'radius' ? 'active' : '' ?>">
                    <a href="/radius">
                        <i class="bi bi-shield-lock"></i>
                        <span>RADIUS</span>
                    </a>
                </li>
            <?php endif; ?>

            <li class="menu-item <?= $section === 'olt-management' ? 'active' : '' ?>">
                <a href="/olt-management">
                    <i class="bi bi-hdd-rack"></i>
                    <span>OLT</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'nap-management' ? 'active' : '' ?>">
                <a href="/nap-management">
                    <i class="bi bi-bezier2"></i>
                    <span>NAP</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'ont-devices' ? 'active' : '' ?>">
                <a href="/ont-devices">
                    <i class="bi bi-router"></i>
                    <span>ONT</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'service-provisioning' ? 'active' : '' ?>">
                <a href="/service-provisioning">
                    <i class="bi bi-gear-wide-connected"></i>
                    <span>Provisioning</span>
                </a>
            </li>

            <li class="menu-section">Business</li>
            <li class="menu-item <?= $section === 'billing' ? 'active' : '' ?>">
                <a href="/billing">
                    <i class="bi bi-receipt"></i>
                    <span>Billing</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'tickets' ? 'active' : '' ?>">
                <a href="/tickets">
                    <i class="bi bi-ticket-detailed"></i>
                    <span>Tickets</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'payment-gateway' ? 'active' : '' ?>">
                <a href="/payment-gateway">
                    <i class="bi bi-credit-card-2-front"></i>
                    <span>Payment Gateway</span>
                </a>
            </li>

            <li class="menu-section">Workforce</li>
            <li class="menu-item <?= $section === 'staff-attendance' ? 'active' : '' ?>">
                <a href="/staff-attendance">
                    <i class="bi bi-clock-history"></i>
                    <span>Attendance</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'technician-management' ? 'active' : '' ?>">
                <a href="/technician-management">
                    <i class="bi bi-person-workspace"></i>
                    <span>Technicians</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'work-orders' ? 'active' : '' ?>">
                <a href="/work-orders">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Work Orders</span>
                </a>
            </li>

            <li class="menu-section">Admin</li>
            <li class="menu-item <?= $section === 'users' ? 'active' : '' ?>">
                <a href="/users">
                    <i class="bi bi-person-gear"></i>
                    <span>Users</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'audit' ? 'active' : '' ?>">
                <a href="/audit">
                    <i class="bi bi-shield-check"></i>
                    <span>Audit Logs</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'branding' ? 'active' : '' ?>">
                <a href="/branding">
                    <i class="bi bi-palette"></i>
                    <span>Branding</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'system-settings' ? 'active' : '' ?>">
                <a href="/system-settings">
                    <i class="bi bi-sliders"></i>
                    <span>System Settings</span>
                </a>
            </li>

            <li class="menu-item <?= $section === 'api-tokens' ? 'active' : '' ?>">
                <a href="/api-tokens">
                    <i class="bi bi-key"></i>
                    <span>API Tokens</span>
                </a>
            </li>

        <?php endif; ?>
        <?php endif; // End disabled legacy role menu block. ?>

    </ul>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= htmlspecialchars($avatarText) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($displayName) ?></div>
                <div class="user-role"><?= htmlspecialchars($displaySubtext) ?></div>
            </div>
            <form method="post" action="/logout" style="display:contents">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Security\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="logout-btn" aria-label="Log out" title="Log out">
                <i class="bi bi-chevron-right"></i>
            </button>
            </form>
        </div>
    </div>

</aside>

<script>
    document.querySelectorAll('#primarySidebar .menu-item.active > a').forEach(function (link) {
        link.setAttribute('aria-current', 'page');
    });
</script>
