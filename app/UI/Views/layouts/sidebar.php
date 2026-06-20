<?php

use Framework\SessionManager;

$user = SessionManager::user();

$username = trim((string)($user['username'] ?? 'Admin'));
$fullName = trim((string)($user['full_name'] ?? ''));
$email = trim((string)($user['email'] ?? ''));
$role = strtoupper((string)($user['role'] ?? 'ADMINISTRATOR'));

$branding = require BASE_PATH . '/config/branding.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $path);
$section = $segments[0] ?? '';

$isSubscriber = $role === 'SUBSCRIBER';

$displayName = $username !== '' ? $username : 'Admin';
$displaySubtext = $role;

if ($isSubscriber) {
    $displayName = $fullName !== '' ? $fullName : $username;
    $displaySubtext = $email !== '' ? $email : $username;
}

$avatarSource = $displayName !== '' ? $displayName : $username;
$avatarText = strtoupper(substr($avatarSource, 0, 1));

?>

<div class="sidebar">

    <!-- HEADER -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="bi bi-hdd-network"></i>
            <div class="logo-text">
                <div class="logo-title">
                    <?= htmlspecialchars($branding['client_name'] ?? 'ISP-in-a-Box') ?>
                </div>
                <div class="logo-subtitle">
                    Powered by <?= htmlspecialchars(defined('POWERED_BY') ? POWERED_BY : '1WAN') ?>
                </div>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">

        <?php if ($isSubscriber): ?>

            <?php
            $subscriberSubsection = $segments[1] ?? '';
            $isAccountPage = $section === 'subscriber-portal' && ($subscriberSubsection === '' || $subscriberSubsection === 'account');
            $isServicesPage = $section === 'subscriber-portal' && $subscriberSubsection === 'services';
            $isInvoicesPage = $section === 'subscriber-portal' && $subscriberSubsection === 'invoices';
            $isPaymentsPage = $section === 'subscriber-portal' && $subscriberSubsection === 'payments';
            $isTicketsPage = $section === 'subscriber-portal' && $subscriberSubsection === 'tickets';
            ?>

            <!-- MY ACCOUNT -->
            <li class="menu-item <?= $isAccountPage ? 'active' : '' ?>">
                <a href="/subscriber-portal">
                    <i class="bi bi-person-vcard"></i>
                    <span>My Account</span>
                </a>
            </li>

            <!-- MY SERVICES -->
            <li class="menu-item <?= $isServicesPage ? 'active' : '' ?>">
                <a href="/subscriber-portal/services">
                    <i class="bi bi-wifi"></i>
                    <span>My Services</span>
                </a>
            </li>

            <!-- MY INVOICES -->
            <li class="menu-item <?= $isInvoicesPage ? 'active' : '' ?>">
                <a href="/subscriber-portal/invoices">
                    <i class="bi bi-receipt"></i>
                    <span>My Invoices</span>
                </a>
            </li>

            <!-- MY PAYMENTS -->
            <li class="menu-item <?= $isPaymentsPage ? 'active' : '' ?>">
                <a href="/subscriber-portal/payments">
                    <i class="bi bi-credit-card"></i>
                    <span>My Payments</span>
                </a>
            </li>

            <!-- MY TICKETS -->
            <li class="menu-item <?= $isTicketsPage ? 'active' : '' ?>">
                <a href="/subscriber-portal/tickets">
                    <i class="bi bi-ticket-detailed"></i>
                    <span>My Tickets</span>
                </a>
            </li>

        <?php else: ?>

            <!-- DASHBOARD -->
            <li class="menu-item <?= ($section === 'dashboard' || $section === '') ? 'active' : '' ?>">
                <a href="/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- PLANS -->
            <li class="menu-item <?= $section === 'subscriber-plans' ? 'active' : '' ?>">
                <a href="/subscriber-plans">
                    <i class="bi bi-card-checklist"></i>
                    <span>Plans</span>
                </a>
            </li>

            <!-- SUBSCRIBERS -->
            <li class="menu-item <?= $section === 'subscribers' ? 'active' : '' ?>">
                <a href="/subscribers">
                    <i class="bi bi-people"></i>
                    <span>Subscribers</span>
                </a>
            </li>

            <!-- CGNAT -->
            <li class="menu-item <?= $section === 'cgnat' ? 'active' : '' ?>">
                <a href="/cgnat">
                    <i class="bi bi-diagram-3"></i>
                    <span>CGNAT</span>
                </a>
            </li>

            <!-- VLAN -->
            <li class="menu-item <?= $section === 'vlan-management' ? 'active' : '' ?>">
                <a href="/vlan-management">
                    <i class="bi bi-tags"></i>
                    <span>VLAN</span>
                </a>
            </li>

            <!-- OLT -->
            <li class="menu-item <?= $section === 'olt-management' ? 'active' : '' ?>">
                <a href="/olt-management">
                    <i class="bi bi-hdd-rack"></i>
                    <span>OLT</span>
                </a>
            </li>

            <!-- NAP -->
            <li class="menu-item <?= $section === 'nap-management' ? 'active' : '' ?>">
                <a href="/nap-management">
                    <i class="bi bi-bezier2"></i>
                    <span>NAP</span>
                </a>
            </li>

            <!-- ONT -->
            <li class="menu-item <?= $section === 'ont-devices' ? 'active' : '' ?>">
                <a href="/ont-devices">
                    <i class="bi bi-router"></i>
                    <span>ONT</span>
                </a>
            </li>

            <!-- PROVISIONING -->
            <li class="menu-item <?= $section === 'service-provisioning' ? 'active' : '' ?>">
                <a href="/service-provisioning">
                    <i class="bi bi-gear-wide-connected"></i>
                    <span>Provisioning</span>
                </a>
            </li>

            <!-- BILLING -->
            <li class="menu-item <?= $section === 'billing' ? 'active' : '' ?>">
                <a href="/billing">
                    <i class="bi bi-receipt"></i>
                    <span>Billing</span>
                </a>
            </li>

            <!-- TICKETS -->
            <li class="menu-item <?= $section === 'tickets' ? 'active' : '' ?>">
                <a href="/tickets">
                    <i class="bi bi-ticket-detailed"></i>
                    <span>Tickets</span>
                </a>
            </li>

            <!-- STAFF ATTENDANCE -->
            <li class="menu-item <?= $section === 'staff-attendance' ? 'active' : '' ?>">
                <a href="/staff-attendance">
                    <i class="bi bi-clock-history"></i>
                    <span>Staff Attendance</span>
                </a>
            </li>

            <!-- TECHNICIAN MANAGEMENT -->
            <li class="menu-item <?= $section === 'technician-management' ? 'active' : '' ?>">
                <a href="/technician-management">
                    <i class="bi bi-person-workspace"></i>
                    <span>Technician Management</span>
                </a>
            </li>

            <!-- WORK ORDERS -->
            <li class="menu-item <?= $section === 'work-orders' ? 'active' : '' ?>">
                <a href="/work-orders">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Work Orders</span>
                </a>
            </li>

            <!-- PAYMENT GATEWAY -->
            <li class="menu-item <?= $section === 'payment-gateway' ? 'active' : '' ?>">
                <a href="/payment-gateway">
                    <i class="bi bi-credit-card-2-front"></i>
                    <span>Payment Gateway</span>
                </a>
            </li>

            <!-- USERS -->
            <li class="menu-item <?= $section === 'users' ? 'active' : '' ?>">
                <a href="/users">
                    <i class="bi bi-person-gear"></i>
                    <span>Users</span>
                </a>
            </li>

            <!-- AUDIT LOGS -->

            <li class="menu-item <?= $section === 'audit' ? 'active' : '' ?>">
                <a href="/audit">
                    <i class="bi bi-shield-check"></i>
                    <span>Audit Logs</span>
                </a>
            </li>

        <?php endif; ?>

    </ul>

    <!-- FOOTER -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= htmlspecialchars($avatarText) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($displayName) ?></div>
                <div class="user-role"><?= htmlspecialchars($displaySubtext) ?></div>
            </div>
            <a href="/logout" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

</div>