INSERT INTO permissions (permission_key, module_key, action_key, label, description, is_sensitive, sort_order)
VALUES
    ('scheduled-downtime.view', 'scheduled-downtime', 'view', 'Scheduled Downtime - View', 'View scheduled subscriber maintenance windows.', 0, 2630),
    ('scheduled-downtime.create', 'scheduled-downtime', 'create', 'Scheduled Downtime - Create', 'Create scheduled subscriber maintenance windows.', 1, 2631),
    ('scheduled-downtime.update', 'scheduled-downtime', 'update', 'Scheduled Downtime - Update', 'Update scheduled subscriber maintenance windows.', 1, 2632),
    ('scheduled-downtime.delete', 'scheduled-downtime', 'delete', 'Scheduled Downtime - Delete', 'Delete scheduled subscriber maintenance windows.', 1, 2633),
    ('system-maintenance.view', 'system-maintenance', 'view', 'System Maintenance - View', 'View system maintenance settings.', 0, 2640),
    ('system-maintenance.configure', 'system-maintenance', 'configure', 'System Maintenance - Configure', 'Configure system maintenance mode and schedule.', 1, 2641)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description),
    is_sensitive = VALUES(is_sensitive),
    sort_order = VALUES(sort_order);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE p.permission_key IN (
    'scheduled-downtime.view',
    'scheduled-downtime.create',
    'scheduled-downtime.update',
    'scheduled-downtime.delete',
    'system-maintenance.view',
    'system-maintenance.configure'
)
AND r.code IN ('ADMINISTRATOR', 'SUPERADMIN');
