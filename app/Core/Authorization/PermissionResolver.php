<?php

namespace App\Core\Authorization;

final class PermissionResolver
{
    private const ACTIONS = [
        'dashboard' => ['view'],
        'subscriber-plans' => ['view','create','update','delete'],
        'subscribers' => ['view','create','update','delete'],
        'bng' => ['view','update','delete','configure','deploy'],
        'routers' => ['view','update','deploy'],
        'cgnat' => ['view','delete','configure','deploy'],
        'vlan-management' => ['view','update','delete'],
        'radius' => ['view','delete','configure'],
        'olt-management' => ['view','create','update','delete'],
        'nap-management' => ['view','create','update','delete'],
        'ont-devices' => ['view','create','update','delete'],
        'service-provisioning' => ['view','create','update','deploy'],
        'billing' => ['view','create','update','approve','configure'],
        'tickets' => ['view','update'],
        'payment-gateway' => ['view','update','approve','configure'],
        'staff-attendance' => ['view','update'],
        'technician-management' => ['view','update'],
        'technician-portal' => ['view','update'],
        'work-orders' => ['view','update'],
        'users' => ['view','create','update','delete','configure'],
        'audit' => ['view'],
        'branding' => ['view','update'],
        'system-settings' => ['view','update'],
        'api-tokens' => ['view','create','delete'],
        'subscriber-portal' => ['view','update'],
        'mfa' => ['view','configure','update'],
    ];

    private const PREFIXES = [
        'subscriber-portal' => 'subscriber-portal', 'payment-gateway' => 'payment-gateway',
        'service-provisioning' => 'service-provisioning', 'technician-management' => 'technician-management',
        'staff-attendance' => 'staff-attendance', 'subscriber-plans' => 'subscriber-plans',
        'vlan-management' => 'vlan-management', 'olt-management' => 'olt-management',
        'nap-management' => 'nap-management', 'ont-devices' => 'ont-devices',
        'system-settings' => 'system-settings', 'api-tokens' => 'api-tokens',
        'work-orders' => 'work-orders', 'payment-gateway' => 'payment-gateway',
        'technician-portal' => 'technician-portal', 'subscribers' => 'subscribers',
        'dashboard' => 'dashboard', 'routers' => 'routers', 'billing' => 'billing',
        'tickets' => 'tickets', 'branding' => 'branding', 'radius' => 'radius',
        'audit' => 'audit', 'users' => 'users', 'cgnat' => 'cgnat', 'bng' => 'bng',
        'mfa' => 'mfa',
    ];

    public function forRoute(string $method, string $uri): ?string
    {
        $path = preg_replace('#^/api/v1/#', '/', $uri);
        $path = trim((string)$path, '/');
        if ($path === '' || $path === 'logout') return null;

        $module = null;
        foreach (self::PREFIXES as $prefix => $candidate) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $module = $candidate;
                break;
            }
        }
        if ($module === null) return null;

        $method = strtoupper($method);
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return $module . '.view';

        $tail = strtolower($path);
        if ($module === 'mfa' && (str_contains($tail, '/login/verify') || str_contains($tail, '/disable'))) return 'mfa.update';
        if ($module === 'users' && str_contains($tail, '/permissions')) return 'users.configure';
        $action = match (true) {
            preg_match('#/(delete|remove|destroy|revoke)(?:/|$)#', $tail) === 1 => 'delete',
            preg_match('#/(approve|confirm|review|verify|reject)(?:/|$)#', $tail) === 1 => 'approve',
            preg_match('#/(deploy|apply|push|execute|provision|activate|disconnect|retry|sync)(?:/|$)#', $tail) === 1 => 'deploy',
            preg_match('#/(settings|config|configure|stage|save-draft)(?:/|$)#', $tail) === 1 => 'configure',
            preg_match('#/(store|create|add|upload)(?:/|$)#', $tail) === 1 => 'create',
            default => 'update',
        };

        return $module . '.' . $action;
    }

    public function knownPermissions(): array
    {
        $permissions = [];
        foreach (self::ACTIONS as $module => $actions) {
            foreach ($actions as $action) $permissions[] = $module . '.' . $action;
        }
        return $permissions;
    }
}
