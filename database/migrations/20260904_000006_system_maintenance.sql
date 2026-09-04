CREATE TABLE IF NOT EXISTS system_maintenance (
    id TINYINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    message VARCHAR(1000) NOT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_system_maintenance_singleton CHECK (id = 1),
    CONSTRAINT chk_system_maintenance_window CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_maintenance (id, enabled, message, starts_at, ends_at)
VALUES (1, 0, '', NULL, NULL)
ON DUPLICATE KEY UPDATE id = id;
