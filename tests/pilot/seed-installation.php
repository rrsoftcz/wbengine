<?php
/** Seed exactly one fixed installation in a fresh PHP process (Db credentials are static). */
require __DIR__ . '/support.php';
use Wbengine\Application\Path\File;
use Wbengine\Api\V2\Model;
use Wbengine\Config;
use Wbengine\Db;
use Wbengine\User\Password;
ini_set('display_errors', '0');
try {
    $installation = pilotInstallation($argv[1] ?? '');
    $proxy = trim((string) file_get_contents('/state/proxy-ip.txt'));
    if (!filter_var($proxy, FILTER_VALIDATE_IP)) { throw new RuntimeException('Pilot proxy address is unavailable.'); }
    $config = [
        'timeZone' => 'Europe/Prague', 'codePage' => 'utf8', 'debug' => false, 'minimizeHtml' => false, 'minimizeCss' => false, 'cdnPath' => '',
        'apiV2' => ['enabled' => true, 'imageDirectory' => '/state/images'],
        'analytics' => ['enabled' => true, 'timezone' => 'UTC', 'retentionDays' => 62],
        'renderer' => ['adapterName' => 'NativeRenderer', 'templatesDir' => '/View/', 'compiledDir' => 'Compiled/', 'configDir' => '/Config/'],
        'dbAdapterDefinition' => ['adapterName' => 'Mysqli', 'database' => $installation['database'], 'charset' => 'utf8mb4', 'port' => '3306', 'hostname' => 'db', 'username' => $installation['user'], 'password' => pilotSecret($installation['secret'])],
        'http' => ['trustedProxies' => [$proxy], 'forwardedHeaders' => ['x-forwarded-proto', 'x-forwarded-for']],
        'cache' => ['enabled' => true, 'driver' => 'file', 'dir' => '/state/cache', 'pages' => ['enabled' => true]],
        'i18n' => ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs']],
        'cssFiles' => [], 'ipAdmins' => [], 'recaptcha' => ['secretKey' => ''],
    ];
    pilotWrite($installation['app'] . '/Config/Settings.json', json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    pilotWrite($installation['app'] . '/View/site.tpl', '<!doctype html><html><head><meta charset="utf-8"><title>Pilot</title></head><body><main>{$central|raw}</main></body></html>');
    pilotWrite($installation['app'] . '/View/404.tpl', 'Pilot page not found');
    pilotWrite($installation['app'] . '/View/410.tpl', 'Pilot page removed');
    Config::load(new File($installation['app'] . '/Config/Settings.json'));
    if (Config::getDbCredentials()->database !== $installation['database']) { throw new RuntimeException('Pilot database guard rejected the target.'); }
    Db::query('SELECT 1');
    foreach (['/app/tests/schema.sql', '/app/migrations/4.0.0/002-api-v2-integrations.sql', '/app/migrations/4.0.0/003-markdown-content.sql', '/app/migrations/4.0.0/005-api-content.sql', '/app/migrations/4.0.0/006-analytics.sql'] as $file) {
        $sql = preg_replace('/^--.*$/m', '', file_get_contents($file));
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ('' === $statement) { continue; }
            if (preg_match('/^ALTER TABLE ([a-z0-9_]+) ADD COLUMN ([a-z0-9_]+)/i', $statement, $column)) {
                if (Db::fetchAssoc('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$installation['database'], $column[1], $column[2]])) { continue; }
            }
            Db::query($statement);
        }
    }
    $saved = is_file($installation['seed']) ? json_decode(file_get_contents($installation['seed']), true, 64, JSON_THROW_ON_ERROR) : null;
    $identity = is_array($saved) && isset($saved['key']) ? (new Model())->authenticate($saved['key']) : null;
    if (null === $identity) {
        if (!Db::fetchAssoc('SELECT user_id FROM cms_users WHERE user_id = ?', [1])) {
            Db::insert('cms_users', ['user_id' => 1, 'username' => 'pilot-admin', 'email' => 'pilot-admin@example.test', 'password' => Password::hash(bin2hex(random_bytes(32))), 'role' => 'admin']);
        }
        $saved = (new Model())->provision(1, 'Isolated central administration ' . $installation['label'], Model::SCOPES);
        pilotWrite($installation['seed'], json_encode($saved, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    }
    if (!Db::fetchAssoc("SELECT `site_id` FROM `cms_sites` WHERE `site_id` = ?", [1])) {
        Db::insert('cms_sites', ['site_id' => 1, 'title' => 'Published ' . $installation['label'] . ' article', 'link' => '/pilot/article/', 'visible' => 1]);
    }
    if (!Db::fetchAssoc("SELECT `site_id` FROM `cms_sites` WHERE `site_id` = ?", [2])) {
        Db::insert('cms_sites', ['site_id' => 2, 'title' => 'Pilot structure page', 'link' => '/pilot/structure/', 'visible' => 1]);
    }
    if (!Db::fetchAssoc("SELECT `section_id` FROM `cms_sections` WHERE `section_id` = ?", [1])) {
        Db::insert('cms_sections', ['section_id' => 1, 'title' => 'Central', 'key' => 'central', 'active' => 1]);
    }
    if (!Db::fetchAssoc("SELECT `id` FROM `cms_boxes` WHERE `id` = ?", [1])) {
        Db::insert('cms_boxes', ['id' => 1, 'name' => 'Pilot article', 'module' => 'blog', 'method' => 'story', 'section_id' => 1, 'sites' => '["1"]', 'api_settings' => '{}']);
    }
    if (!Db::fetchAssoc("SELECT `id` FROM `cms_articles` WHERE `id` = ?", [1])) {
        Db::insert('cms_articles', ['id' => 1, 'site_id' => 1, 'title' => 'Published ' . $installation['label'] . ' article', 'introtext' => 'Published introduction.', 'bodytext' => "# Published " . $installation['label'] . "\n\nThis content is public on " . $installation['hostname'] . ".", 'content_format' => 'markdown', 'published' => time(), 'views' => 100]);
    }
    if (!Db::fetchAssoc("SELECT `article_id` FROM `cms_article_translations` WHERE `article_id` = ? AND locale = 'cs'", [1])) {
        Db::insert('cms_article_translations', ['article_id' => 1, 'locale' => 'cs', 'title' => 'Pilotni clanek', 'bodytext' => '# Publikovany pilot', 'content_format' => 'markdown']);
    }
    $analyticsDay = (new DateTimeImmutable('today', new DateTimeZone('UTC')))->format('Y-m-d');
    Db::query('INSERT INTO cms_analytics_meta (id, tracking_started_on) VALUES (1, ?) ON DUPLICATE KEY UPDATE tracking_started_on = LEAST(tracking_started_on, VALUES(tracking_started_on))', [$analyticsDay]);
    foreach ([['page', 1, 2], ['article', 1, 1], ['box', 1, 3]] as [$type, $id, $views]) {
        if (!Db::fetchAssoc('SELECT entity_id FROM cms_analytics_daily WHERE day = ? AND site_id = ? AND entity_type = ? AND entity_id = ?', [$analyticsDay, 1, $type, $id])) {
            Db::insert('cms_analytics_daily', ['day' => $analyticsDay, 'site_id' => 1, 'entity_type' => $type, 'entity_id' => $id, 'views' => $views]);
        }
    }
    foreach ([['page', 1, 50], ['box', 1, 75]] as [$type, $id, $views]) {
        if (!Db::fetchAssoc('SELECT entity_id FROM cms_analytics_totals WHERE site_id = ? AND entity_type = ? AND entity_id = ?', [1, $type, $id])) {
            Db::insert('cms_analytics_totals', ['site_id' => 1, 'entity_type' => $type, 'entity_id' => $id, 'views' => $views]);
        }
    }
    if (!Db::fetchAssoc("SELECT `menu_id` FROM `cms_menu` WHERE `menu_id` = ?", [1])) {
        Db::insert('cms_menu', ['menu_id' => 1, 'site_id' => 1, 'name' => 'Pilot article', 'type' => 1, 'order' => 1]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Pilot installation failed (' . get_class($error) . "). No credentials were logged.\n");
    exit(1);
}
