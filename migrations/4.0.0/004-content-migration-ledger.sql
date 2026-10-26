-- Optional MD-03 migration ledger. Apply after 003-markdown-content.sql.
-- Replace cms_ if the installation uses another prefix. Contains original content: restrict access and back it up.
-- Retain this table until every migration rollback window has expired.
CREATE TABLE cms_content_migrations (
    migration_id CHAR(64) NOT NULL PRIMARY KEY,
    entity VARCHAR(16) NOT NULL,
    article_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(10) NOT NULL DEFAULT '',
    source_format VARCHAR(16) NOT NULL,
    before_data LONGTEXT NOT NULL,
    after_data LONGTEXT NOT NULL,
    source_hash CHAR(64) NOT NULL,
    target_hash CHAR(64) NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    rolled_back_at BIGINT UNSIGNED NULL,
    INDEX content_record (entity, article_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
