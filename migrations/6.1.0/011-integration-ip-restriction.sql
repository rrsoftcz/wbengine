-- Apply explicitly during the upgrade to WBengine 6.1. No runtime DDL is performed.
-- Requires migration 4.0.0/002. Safe to run more than once: each column is added only when missing.
-- This migration assumes the standard cms_ prefix, like the existing auth tables.
-- allowed_ips is a JSON list of addresses and CIDR ranges a key may be used from (NULL: any address).
-- last_used_ip is the address of the last successful call of the key.
SET @wb_allowed_missing = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_integrations' AND COLUMN_NAME = 'allowed_ips');
SET @wb_allowed_sql = IF(@wb_allowed_missing, 'ALTER TABLE cms_api_integrations ADD COLUMN allowed_ips TEXT NULL DEFAULT NULL', 'DO 0');
PREPARE wb_allowed_stmt FROM @wb_allowed_sql;
EXECUTE wb_allowed_stmt;
DEALLOCATE PREPARE wb_allowed_stmt;
SET @wb_ip_missing = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_integrations' AND COLUMN_NAME = 'last_used_ip');
SET @wb_ip_sql = IF(@wb_ip_missing, 'ALTER TABLE cms_api_integrations ADD COLUMN last_used_ip VARCHAR(45) NULL DEFAULT NULL', 'DO 0');
PREPARE wb_ip_stmt FROM @wb_ip_sql;
EXECUTE wb_ip_stmt;
DEALLOCATE PREPARE wb_ip_stmt;
