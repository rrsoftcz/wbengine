-- Optional content migration. Back up articles and translations before applying.
-- Check existing columns first; replace cms_ when using a different table prefix.
-- Existing stored HTML is preserved without parsing or rewriting.
-- New Markdown content must explicitly set content_format = 'markdown'.
-- Rollback requires restoring converted content before dropping these columns.
ALTER TABLE cms_articles ADD COLUMN content_format ENUM('legacy_html', 'markdown') NOT NULL DEFAULT 'legacy_html';
ALTER TABLE cms_article_translations ADD COLUMN content_format ENUM('legacy_html', 'markdown') NOT NULL DEFAULT 'legacy_html';
