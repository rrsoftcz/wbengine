<?php

/**
 * leave this...
 */
if (!defined('IN_CMS')) {
	exit ('Fuck');
}


define('FRONT_SURFIX_CLASS_NAME', '_front');
define('DEFAULT_APP_DIR', '/App');
define('DEFAULT_APP_CONFIG_DIR', '/Config/');
define('DEFAULT_APP_CONFIG_FILE_NAME_DEVEL', 'Devel.cfg.php');
define('DEFAULT_APP_CONFIG_FILE_NAME_PROFUCTION', 'Default.cfg.php');

define('DEVICE_TYPE_MOBILE', 1);
define('DEVICE_TYPE_TABLET', 2);
define('DEVICE_TYPE_DESKTOP', 3);

// User related
define('ANONYMOUS', 1);
// User default locale
define('USER_LOGGED', 1);
define('USER_NOT_LOGGED', 0);
define('DEFAULT_LOCALE', 1);
define('DEFAULT_AGE', 31557600);
define('HTML_STATIC', 1);

// Central sectin name, shoul be defined in db table Sections!
define('HTML_CENTRAL_SECTION', 'central');
define('HTML_RIGHT_SECTION', 'right');
define('HTML_FOOTER_SECTION', 'footer');
define('HTML_HEADER_SECTION', 'header');
define('HTML_GLOBAL_TEMPLATE', 'site');
define('HTML_TEMPLATE_TYPE_FRONT', 'front');
define('HTML_TEMPLATE_TYPE_BACKEND', 'admin');

// Error codes
define('HTML_ERROR', 'error');
define('HTML_ERROR_404', 404);
define('HTML_ERROR_401', 401);
define('HTML_ERROR_410', 410);
define('HTML_ERROR_500', 500);

define('ACCOUNT_NOT_LOGGED', 0);
define('ACCOUNT_INACTIVE', 1);
define('ACCOUNT_INACTIVE_ADMIN', 2);

define('STORE_STATUS_ADDED', 0);
define('STORE_STATUS_PENDING', 1);
define('STORE_STATUS_COMPLETE', 2);

// Groups types
define('STATIC', 1);
define('GROUPED', 2);
define('DYNAMIC', 3);

// User type states
define('USER_NORMAL', 0);
define('USER_INACTIVE', 1);
define('USER_IGNORE', 2);
define('USER_FOUNDER', 3);

define('S_TABLE_ARTICLES', 'cms_articles');
define('S_TABLE_SITES', 'cms_sites');
define('S_TABLE_SITE_TYPES', 'cms_sitetype');
define('S_TABLE_MENU', 'cms_menu');
define('S_TABLE_BOX_ORDERS', 'cms_boxorders');
define('S_TABLE_BOXES', 'cms_boxes');
define('S_TABLE_SESSIONS', 'cms_sessions');
define('S_TABLE_BANS', 'cms_bans');
define('S_TABLE_USERS', 'cms_users');
define('S_TABLE_LOCALES', 'cms_locales');
define('S_TABLE_SUBMENU', 'cms_submenu');
define('S_TABLE_SECTIONS', 'cms_sections');
define('S_TABLE_LANDCODES', 'cms_landcodes');
define('S_TABLE_COMMENTS', 'cms_comments');

define('S_TABLE_DOOR_CATEGORIES', 'doors_kategorie_dveri');
define('S_TABLE_DOOR_TYPES', 'doors_typy_dveri');
define('S_TABLE_DOOR_KATALOG', 'doors_katalog');

define('S_TABLE_STORE_PRODUCTS', 'store_products');
define('S_TABLE_STORE_BASKET', 'store_basket');
define('S_TABLE_STORE_DELIVERY_TYPES', 'store_delivery_types');

define('S_TABLE_WOW_ADDONS', 'wow_addons');
define('S_TABLE_WOW_PATCHES', 'wow_patches');
