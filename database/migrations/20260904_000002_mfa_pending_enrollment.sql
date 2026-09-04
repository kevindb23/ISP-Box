ALTER TABLE user_mfa
    ADD COLUMN IF NOT EXISTS pending_method ENUM('AUTHENTICATOR', 'EMAIL') DEFAULT NULL AFTER verified_at,
    ADD COLUMN IF NOT EXISTS pending_secret_encrypted TEXT DEFAULT NULL AFTER pending_method,
    ADD COLUMN IF NOT EXISTS pending_started_at DATETIME DEFAULT NULL AFTER pending_secret_encrypted;
