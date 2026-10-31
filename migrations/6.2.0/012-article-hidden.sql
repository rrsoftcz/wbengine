-- Apply explicitly during the upgrade to WBengine 6.2. No runtime DDL is performed.
-- Safe to run more than once: the column and the index are added only when missing.
-- This migration assumes the standard cms_ prefix, like the existing auth tables.
-- cms_articles.hidden: 1 hides the article. The page of a hidden article is not found (404) and its menu
-- item disappears; 0 shows it again. Existing rows stay visible.
SET @wb_hidden_missing = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_articles' AND COLUMN_NAME = 'hidden');
SET @wb_hidden_sql = IF(@wb_hidden_missing, 'ALTER TABLE cms_articles ADD COLUMN hidden TINYINT NOT NULL DEFAULT 0', 'DO 0');
PREPARE wb_hidden_stmt FROM @wb_hidden_sql;
EXECUTE wb_hidden_stmt;
DEALLOCATE PREPARE wb_hidden_stmt;
-- The page lookup checks for a hidden article of the page on every request.
SET @wb_site_index_missing = (SELECT COUNT(*) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_articles' AND COLUMN_NAME = 'site_id' AND SEQ_IN_INDEX = 1);
SET @wb_site_index_sql = IF(@wb_site_index_missing, 'ALTER TABLE cms_articles ADD KEY wb_articles_site (site_id)', 'DO 0');
PREPARE wb_site_index_stmt FROM @wb_site_index_sql;
EXECUTE wb_site_index_stmt;
DEALLOCATE PREPARE wb_site_index_stmt;
