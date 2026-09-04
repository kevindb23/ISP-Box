CREATE TABLE IF NOT EXISTS user_mfa (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    method ENUM('AUTHENTICATOR', 'EMAIL') NOT NULL DEFAULT 'AUTHENTICATOR',
    secret_encrypted TEXT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_mfa_user (user_id),
    KEY idx_user_mfa_enabled (enabled),
    CONSTRAINT fk_user_mfa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS mfa_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    purpose ENUM('LOGIN', 'PASSWORD_RESET', 'ENROLLMENT') NOT NULL,
    method ENUM('AUTHENTICATOR', 'EMAIL') NOT NULL,
    challenge_token_hash CHAR(64) NOT NULL,
    code_hash VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
    verified_at DATETIME DEFAULT NULL,
    consumed_at DATETIME DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mfa_challenge_token (challenge_token_hash),
    KEY idx_mfa_challenge_lookup (user_id, purpose, consumed_at, expires_at),
    CONSTRAINT fk_mfa_challenge_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO permissions (permission_key, module_key, action_key, label, description, is_sensitive, sort_order)
VALUES
    ('mfa.view', 'mfa', 'view', 'MFA — View', 'View multi-factor authentication settings.', 0, 2601),
    ('mfa.configure', 'mfa', 'configure', 'MFA — Configure', 'Configure MFA methods for user accounts.', 1, 2602),
    ('mfa.update', 'mfa', 'update', 'MFA — Update', 'Update MFA enrollment and verification state.', 1, 2603)
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_sensitive = VALUES(is_sensitive), sort_order = VALUES(sort_order);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE p.permission_key = 'mfa.view' AND r.code IN ('SUPERADMIN', 'NOC', 'SUPPORT', 'BILLING', 'TECHNICIAN', 'SUBSCRIBER');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE p.permission_key IN ('mfa.configure', 'mfa.update') AND r.code = 'SUPERADMIN';
