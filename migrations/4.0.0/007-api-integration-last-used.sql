-- Apply explicitly during the upgrade to the local integration screen. No runtime DDL is performed.
-- Requires migration 4.0.0/002. Safe to run more than once: the column is added only when missing.
-- This migration assumes the standard cms_ prefix, like the existing auth tables.
-- last_used_at is a unix timestamp refreshed at most once per 60 seconds per key.
SET @wb_last_used_missing = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_integrations' AND COLUMN_NAME = 'last_used_at');
SET @wb_last_used_sql = IF(@wb_last_used_missing, 'ALTER TABLE cms_api_integrations ADD COLUMN last_used_at BIGINT UNSIGNED NULL DEFAULT NULL AFTER revoked_at', 'DO 0');
PREPARE wb_last_used_stmt FROM @wb_last_used_sql;
EXECUTE wb_last_used_stmt;
DEALLOCATE PREPARE wb_last_used_stmt;
