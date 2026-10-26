-- Optional API content upgrade, after 002 and 003. Back up before applying manually.
-- Every table involved in an atomic API write must use InnoDB.
ALTER TABLE cms_articles ENGINE=InnoDB;
ALTER TABLE cms_sites ENGINE=InnoDB;
ALTER TABLE cms_menu ENGINE=InnoDB;
ALTER TABLE cms_sections ENGINE=InnoDB;
ALTER TABLE cms_boxes ENGINE=InnoDB;
ALTER TABLE cms_site_translations ENGINE=InnoDB;
ALTER TABLE cms_menu_translations ENGINE=InnoDB;
ALTER TABLE cms_article_translations ENGINE=InnoDB;
ALTER TABLE cms_boxes ADD COLUMN api_settings TEXT DEFAULT NULL;
CREATE TABLE IF NOT EXISTS cms_api_changes (
    change_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource VARCHAR(32) NOT NULL,
    resource_id VARCHAR(64) NOT NULL,
    action VARCHAR(32) NOT NULL,
    before_data LONGTEXT DEFAULT NULL,
    after_data LONGTEXT DEFAULT NULL,
    integration_id INT UNSIGNED NOT NULL,
    central_actor VARCHAR(128) DEFAULT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (change_id),
    KEY api_changes_resource (resource, resource_id, change_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cms_api_drafts (
    article_id INT UNSIGNED NOT NULL,
    source_etag CHAR(66) NOT NULL,
    payload LONGTEXT NOT NULL,
    revision_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cms_api_revisions (
    revision_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL,
    payload LONGTEXT NOT NULL,
    action VARCHAR(32) NOT NULL,
    integration_id INT UNSIGNED NOT NULL,
    central_actor VARCHAR(128) DEFAULT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (revision_id),
    KEY api_revisions_article (article_id, revision_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cms_api_images (
    image_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    mime VARCHAR(32) NOT NULL,
    size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    integration_id INT UNSIGNED NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (image_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
