-- Apply explicitly during the site's API v2 upgrade. No runtime DDL is performed.
-- This migration assumes the standard cms_ prefix, like the existing auth tables.
CREATE TABLE IF NOT EXISTS cms_api_integrations (
    integration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL,
    key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scopes TEXT NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    revoked_at BIGINT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (integration_id),
    UNIQUE KEY api_integration_hash (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cms_api_audit (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    integration_id INT UNSIGNED DEFAULT NULL,
    central_actor VARCHAR(128) DEFAULT NULL,
    local_user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(64) NOT NULL,
    outcome VARCHAR(64) NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (audit_id),
    KEY api_audit_integration_time (integration_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
