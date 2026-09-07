<?php

/** Resolve the compact breadcrumb shown in the shared application header. */
if (!function_exists('nexusbox_breadcrumb')) {
    function nexusbox_breadcrumb(?string $requestUri = null): array
    {
        $path = trim(parse_url($requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
        $section = explode('/', $path)[0] ?? '';
        $pages = [
            '' => 'Dashboard', 'dashboard' => 'Dashboard', 'subscriber-plans' => 'Plans',
            'subscribers' => 'Subscribers', 'bng' => 'BNG', 'cgnat' => 'CGNAT', 'vlan-management' => 'VLAN',
            'radius' => 'RADIUS', 'olt-management' => 'OLT', 'nap-management' => 'NAP',
            'ont-devices' => 'ONT', 'service-provisioning' => 'Provisioning', 'billing' => 'Billing',
            'tickets' => 'Tickets', 'payment-gateway' => 'Payment Gateway',
            'staff-attendance' => 'Attendance', 'technician-management' => 'Technicians',
            'work-orders' => 'Work Orders', 'users' => 'Users', 'audit' => 'Audit Logs',
            'branding' => 'Branding', 'system-settings' => 'System Settings', 'api-tokens' => 'API Tokens', 'email' => 'Email', 'notifications' => 'Notifications', 'scheduled-downtime' => 'Scheduled Downtime', 'system-maintenance' => 'System Maintenance', 'mfa' => 'MFA', 'subscriber-portal' => 'My Account',
        ];
        $groups = [
            '' => 'Operations', 'dashboard' => 'Operations', 'subscriber-plans' => 'Operations', 'subscribers' => 'Operations',
            'bng' => 'Network', 'cgnat' => 'Network', 'vlan-management' => 'Network', 'radius' => 'Network',
            'olt-management' => 'Network', 'nap-management' => 'Network', 'ont-devices' => 'Network',
            'service-provisioning' => 'Network', 'billing' => 'Business', 'tickets' => 'Business',
            'payment-gateway' => 'Business', 'staff-attendance' => 'Workforce',
            'technician-management' => 'Workforce', 'work-orders' => 'Workforce',
            'users' => 'Administration', 'audit' => 'Administration', 'branding' => 'Administration', 'system-settings' => 'Administration', 'api-tokens' => 'Administration', 'email' => 'Administration', 'notifications' => 'Administration', 'scheduled-downtime' => 'Maintenance', 'system-maintenance' => 'Maintenance', 'mfa' => 'Security',
            'subscriber-portal' => 'Subscriber portal',
        ];
        $current = $pages[$section] ?? ucwords(str_replace('-', ' ', $section));
        return ['group' => $groups[$section] ?? 'Workspace', 'current' => $current !== '' ? $current : 'Dashboard'];
    }
}
