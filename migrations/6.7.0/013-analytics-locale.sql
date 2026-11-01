-- Apply explicitly during the upgrade to WBengine 6.7, only on a site that uses analytics (migration 006).
-- Safe to run more than once: a table is changed only while it has no locale column, and a site
-- without the analytics tables is left alone. No runtime DDL is performed.
-- The views of a page are recorded per language from now on. The locale is part of the primary key.
-- Rows counted before this migration keep an empty locale, which means "not recorded per language":
-- the totals stay correct and the overview reports them as such, never as a guessed language.
SET @wb_al_daily = (SELECT COUNT(*) = 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_analytics_daily') AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_analytics_daily' AND COLUMN_NAME = 'locale');
SET @wb_al_daily_sql = IF(@wb_al_daily, 'ALTER TABLE cms_analytics_daily ADD COLUMN locale VARCHAR(10) NOT NULL DEFAULT '''' AFTER entity_id, DROP PRIMARY KEY, ADD PRIMARY KEY (day, site_id, entity_type, entity_id, locale)', 'DO 0');
PREPARE wb_al_daily_stmt FROM @wb_al_daily_sql;
EXECUTE wb_al_daily_stmt;
DEALLOCATE PREPARE wb_al_daily_stmt;
SET @wb_al_totals = (SELECT COUNT(*) = 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_analytics_totals') AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_analytics_totals' AND COLUMN_NAME = 'locale');
SET @wb_al_totals_sql = IF(@wb_al_totals, 'ALTER TABLE cms_analytics_totals ADD COLUMN locale VARCHAR(10) NOT NULL DEFAULT '''' AFTER entity_id, DROP PRIMARY KEY, ADD PRIMARY KEY (site_id, entity_type, entity_id, locale)', 'DO 0');
PREPARE wb_al_totals_stmt FROM @wb_al_totals_sql;
EXECUTE wb_al_totals_stmt;
DEALLOCATE PREPARE wb_al_totals_stmt;
