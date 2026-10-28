-- Minimal schema for the integration tests.
CREATE TABLE IF NOT EXISTS `cms_users` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_type` int DEFAULT '2',
  `group_id` int unsigned NOT NULL DEFAULT '0',
  `username` varchar(150) NOT NULL,
  `firstname` varchar(45) DEFAULT NULL,
  `lastname` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL DEFAULT '0',
  `email` varchar(100) DEFAULT NULL,
  `age` int DEFAULT '0',
  `sex` tinyint DEFAULT '0',
  `ac_status` int unsigned NOT NULL DEFAULT '0',
  `ac_key` varchar(45) DEFAULT NULL,
  `address` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postcode` varchar(45) DEFAULT NULL,
  `country` int unsigned DEFAULT '0',
  `ac_active` int unsigned DEFAULT '0',
  `user_ip` varchar(45) DEFAULT NULL,
  `locale` int DEFAULT '1',
  `role` varchar(20) NOT NULL DEFAULT 'user',
  PRIMARY KEY (`user_id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_sections` (
  `section_id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(255) DEFAULT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `key` varchar(45) NOT NULL DEFAULT '',
  `return_error_code` int DEFAULT '0',
  PRIMARY KEY (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_sites` (
  `site_id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL DEFAULT '0',
  `left_id` int unsigned NOT NULL DEFAULT '0',
  `right_id` int unsigned NOT NULL DEFAULT '0',
  `strict` tinyint DEFAULT '0',
  `visible` tinyint unsigned NOT NULL DEFAULT '0',
  `link` varchar(100) NOT NULL,
  `title` varchar(120) NOT NULL DEFAULT '',
  `html_title` varchar(120) NOT NULL DEFAULT '',
  `html_description` varchar(170) NOT NULL DEFAULT '',
  `html_keywords` varchar(255) NOT NULL DEFAULT '',
  `acl` int DEFAULT '0',
  PRIMARY KEY (`site_id`),
  KEY `link` (`link`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_refresh_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `family_id` char(32) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  `family_started_at` int unsigned NOT NULL DEFAULT '0',
  `used_at` int unsigned DEFAULT NULL,
  `revoked_at` int unsigned DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `family_id` (`family_id`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_boxes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `module` varchar(100) NOT NULL DEFAULT '',
  `method` varchar(100) NOT NULL DEFAULT '',
  `section_id` int unsigned NOT NULL DEFAULT '0',
  `static` tinyint NOT NULL DEFAULT '0',
  `shared` tinyint NOT NULL DEFAULT '0',
  `device_min` int NOT NULL DEFAULT '0',
  `device_strict` tinyint NOT NULL DEFAULT '0',
  `order` int NOT NULL DEFAULT '0',
  `sites` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_menu` (
  `menu_id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int unsigned NOT NULL,
  `type` tinyint DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(50) DEFAULT NULL,
  `visible` tinyint unsigned NOT NULL DEFAULT '1',
  `order` tinyint DEFAULT NULL,
  `parent` int DEFAULT '0',
  PRIMARY KEY (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_articles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT '0',
  `views` int DEFAULT '0',
  `title` varchar(255) DEFAULT NULL,
  `introtext` text,
  `bodytext` text,
  `published` int NOT NULL DEFAULT '0',
  `author` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_site_translations` (
  `site_id` int unsigned NOT NULL,
  `locale` varchar(10) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `html_title` varchar(120) DEFAULT NULL,
  `html_description` varchar(170) DEFAULT NULL,
  `html_keywords` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`site_id`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_menu_translations` (
  `menu_id` int unsigned NOT NULL,
  `locale` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`menu_id`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_article_translations` (
  `article_id` int unsigned NOT NULL,
  `locale` varchar(10) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `introtext` text,
  `bodytext` text,
  PRIMARY KEY (`article_id`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int NOT NULL DEFAULT '0',
  `session_data` text,
  `user_agent` varchar(255) DEFAULT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `session_updated` int NOT NULL DEFAULT '0',
  `session_expire` int NOT NULL DEFAULT '0',
  `user_salt` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API v2 integrations: migrations/4.0.0/002 plus migrations/4.0.3/001 (last_used_at).
CREATE TABLE IF NOT EXISTS `cms_api_integrations` (
  `integration_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `key_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `scopes` text NOT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` bigint unsigned NOT NULL,
  `revoked_at` bigint unsigned DEFAULT NULL,
  `last_used_at` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`integration_id`),
  UNIQUE KEY `api_integration_hash` (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cms_api_audit` (
  `audit_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `integration_id` int unsigned DEFAULT NULL,
  `central_actor` varchar(128) DEFAULT NULL,
  `local_user_id` int unsigned DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `outcome` varchar(64) NOT NULL,
  `created_at` bigint unsigned NOT NULL,
  PRIMARY KEY (`audit_id`),
  KEY `api_audit_integration_time` (`integration_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
