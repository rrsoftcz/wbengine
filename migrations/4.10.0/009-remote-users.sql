-- Account state and one-time password setup links for the remote user management of API v2.
-- Apply explicitly after migrations 4.0.0/002 and 4.0.0/005. No runtime DDL is performed. Safe to run more than once.
-- cms_users.disabled_at: epoch seconds when the account was disabled, NULL for an active account.
-- cms_password_resets: only the SHA-256 of a one-time link is stored; the link works once, for 48 hours.
-- This migration assumes the standard cms_ prefix, like the existing auth tables.
SET @wb_usr_disabled_missing = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_users' AND COLUMN_NAME = 'disabled_at');
SET @wb_usr_disabled_sql = IF(@wb_usr_disabled_missing, 'ALTER TABLE cms_users ADD COLUMN disabled_at BIGINT UNSIGNED NULL DEFAULT NULL', 'DO 0');
PREPARE wb_usr_disabled_stmt FROM @wb_usr_disabled_sql;
EXECUTE wb_usr_disabled_stmt;
DEALLOCATE PREPARE wb_usr_disabled_stmt;
CREATE TABLE IF NOT EXISTS cms_password_resets (
    token_hash CHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    used_at BIGINT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (token_hash),
    KEY password_resets_user (user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
