-- WBengine 5.0.0 removed the API v1 login with refresh tokens. The token table is no longer used.
DROP TABLE IF EXISTS `cms_refresh_tokens`;
