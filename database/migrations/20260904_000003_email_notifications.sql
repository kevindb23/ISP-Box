CREATE TABLE IF NOT EXISTS email_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    preset VARCHAR(40) NOT NULL DEFAULT 'CUSTOM',
    smtp_host VARCHAR(255) NOT NULL DEFAULT '',
    smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    smtp_encryption ENUM('NONE', 'TLS', 'SSL') NOT NULL DEFAULT 'TLS',
    smtp_username VARCHAR(255) NOT NULL DEFAULT '',
    smtp_password_encrypted TEXT DEFAULT NULL,
    from_name VARCHAR(255) NOT NULL DEFAULT '',
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS notification_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    telegram_bot_token_encrypted TEXT DEFAULT NULL,
    telegram_chat_id VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO email_settings (id) VALUES (1) ON DUPLICATE KEY UPDATE id = id;
INSERT INTO notification_settings (id, enabled) VALUES (1, 0) ON DUPLICATE KEY UPDATE id = id;

INSERT INTO permissions (permission_key, module_key, action_key, label, description, is_sensitive, sort_order)
VALUES
    ('email.view', 'email', 'view', 'Email — View', 'View SMTP configuration.', 0, 2610),
    ('email.configure', 'email', 'configure', 'Email — Configure', 'Configure SMTP settings.', 1, 2611),
    ('notifications.view', 'notifications', 'view', 'Notifications — View', 'View notification settings.', 0, 2620),
    ('notifications.configure', 'notifications', 'configure', 'Notifications — Configure', 'Configure notification channels.', 1, 2621)
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_sensitive = VALUES(is_sensitive), sort_order = VALUES(sort_order);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE p.permission_key IN ('email.view', 'email.configure', 'notifications.view', 'notifications.configure')
  AND r.code IN ('ADMINISTRATOR', 'SUPERADMIN');
