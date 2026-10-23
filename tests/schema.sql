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
