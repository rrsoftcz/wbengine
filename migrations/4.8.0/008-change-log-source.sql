-- Change log for every write path (API v2, API v1, command line). Apply explicitly after migrations
-- 4.0.0/002 and 4.0.0/005. No runtime DDL is performed. Safe to run more than once: each column and
-- index is added only when missing. Existing rows stay valid: they get source 'v2' and no local user.
-- This migration assumes the standard cms_ prefix, like the existing auth tables.
-- source: v2, v1 or cli. local_user_id: the local account behind a v1 write (NULL otherwise).
-- Writes that do not come from an integration key store integration_id 0.
-- cms_api_audit.detail: error code or reference of a rejected or failed write, never a request body.
-- The created_at indexes keep the retention commands (audit:prune) cheap.
SET @wb_chg_source_missing = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_changes' AND COLUMN_NAME = 'source');
SET @wb_chg_source_sql = IF(@wb_chg_source_missing, 'ALTER TABLE cms_api_changes ADD COLUMN source VARCHAR(8) NOT NULL DEFAULT ''v2'' AFTER central_actor', 'DO 0');
PREPARE wb_chg_source_stmt FROM @wb_chg_source_sql;
EXECUTE wb_chg_source_stmt;
DEALLOCATE PREPARE wb_chg_source_stmt;
SET @wb_chg_user_missing = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_changes' AND COLUMN_NAME = 'local_user_id');
SET @wb_chg_user_sql = IF(@wb_chg_user_missing, 'ALTER TABLE cms_api_changes ADD COLUMN local_user_id INT UNSIGNED NULL DEFAULT NULL AFTER source', 'DO 0');
PREPARE wb_chg_user_stmt FROM @wb_chg_user_sql;
EXECUTE wb_chg_user_stmt;
DEALLOCATE PREPARE wb_chg_user_stmt;
SET @wb_chg_created_missing = (SELECT COUNT(*) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_changes' AND INDEX_NAME = 'api_changes_created');
SET @wb_chg_created_sql = IF(@wb_chg_created_missing, 'ALTER TABLE cms_api_changes ADD KEY api_changes_created (created_at)', 'DO 0');
PREPARE wb_chg_created_stmt FROM @wb_chg_created_sql;
EXECUTE wb_chg_created_stmt;
DEALLOCATE PREPARE wb_chg_created_stmt;
SET @wb_aud_detail_missing = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_audit' AND COLUMN_NAME = 'detail');
SET @wb_aud_detail_sql = IF(@wb_aud_detail_missing, 'ALTER TABLE cms_api_audit ADD COLUMN detail VARCHAR(128) NULL DEFAULT NULL AFTER outcome', 'DO 0');
PREPARE wb_aud_detail_stmt FROM @wb_aud_detail_sql;
EXECUTE wb_aud_detail_stmt;
DEALLOCATE PREPARE wb_aud_detail_stmt;
SET @wb_aud_created_missing = (SELECT COUNT(*) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_audit' AND INDEX_NAME = 'api_audit_created');
SET @wb_aud_created_sql = IF(@wb_aud_created_missing, 'ALTER TABLE cms_api_audit ADD KEY api_audit_created (created_at)', 'DO 0');
PREPARE wb_aud_created_stmt FROM @wb_aud_created_sql;
EXECUTE wb_aud_created_stmt;
DEALLOCATE PREPARE wb_aud_created_stmt;
SET @wb_rev_created_missing = (SELECT COUNT(*) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_api_revisions' AND INDEX_NAME = 'api_revisions_created');
SET @wb_rev_created_sql = IF(@wb_rev_created_missing, 'ALTER TABLE cms_api_revisions ADD KEY api_revisions_created (created_at)', 'DO 0');
PREPARE wb_rev_created_stmt FROM @wb_rev_created_sql;
EXECUTE wb_rev_created_stmt;
DEALLOCATE PREPARE wb_rev_created_stmt;
