-- Apply explicitly before enabling analytics. No visitor or request identifiers are stored.
CREATE TABLE IF NOT EXISTS cms_analytics_daily (
    day DATE NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    entity_type ENUM('page', 'article', 'box') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    views BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, site_id, entity_type, entity_id),
    KEY analytics_site_entity_day (site_id, entity_type, day),
    KEY analytics_retention_day (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cms_analytics_totals (
    site_id INT UNSIGNED NOT NULL,
    entity_type ENUM('page', 'box') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    views BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cms_analytics_meta (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    tracking_started_on DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
