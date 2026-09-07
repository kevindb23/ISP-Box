INSERT INTO permissions (permission_key, module_key, action_key, label, description, is_sensitive, sort_order)
VALUES (
    'notifications.feed',
    'notifications',
    'feed',
    'Notifications - Feed',
    'Read the user-scoped notification feed and mark notifications as read.',
    0,
    2622
)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description),
    is_sensitive = VALUES(is_sensitive),
    sort_order = VALUES(sort_order);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'notifications.feed'
WHERE r.code IN ('SUBSCRIBER', 'ADMINISTRATOR', 'SUPERADMIN');
