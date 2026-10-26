<?php

// HTTP-only fixture. The production framework is exercised in a separate PHP process.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/Application/Env/Const.php';

use Wbengine\Application\Application;
use Wbengine\Cache;
use Wbengine\Config;
use Wbengine\PageCache;
use Wbengine\Site;

$configuration = json_decode(file_get_contents(dirname(__DIR__) . '/config/Settings.test.json'));
$configuration->debug = false;
$configuration->cache = (object) ['enabled' => true, 'driver' => 'file',
    'dir' => getenv('WB_PAGE_CACHE_HTTP_DIR'), 'pages' => (object) [
        'enabled' => true, 'denyPaths' => ['/excluded/'],
    ]];
Config::$config = $configuration;
Cache::setDriver(new \Wbengine\Cache\FileCache(getenv('WB_PAGE_CACHE_HTTP_DIR')));

class HttpFixtureSite extends Site
{
    public function isLoaded() { return true; }
    public function getLink() { return $_SERVER['REQUEST_URI']; }
    public function __get($name) { return 'acl' === $name ? 0 : null; }
}

class HttpFixtureApplication extends Application
{
    private $fixtureSite;
    public function __construct() { $this->fixtureSite = new HttpFixtureSite(); }
    public function getSite() { return $this->fixtureSite; }
    public function hasModifiedSession() { return false; }
}

$path = $_SERVER['REQUEST_URI'];
if ('/bypass/' === $path) {
    PageCache::bypass();
}
if (isset($_SERVER['HTTP_X_CURRENT_POLICY'])) {
    header('X-Frame-Options: SAMEORIGIN');
}
if ('/old/' === $path) {
    // A record created before the stricter policy must not be served after upgrade.
    Cache::store(PageCache::cacheKey($path), ['body' => 'old private body', 'etag' => '"old"'], 60);
}
PageCache::handle(new HttpFixtureApplication());
header('Content-Type: text/html; charset=UTF-8');
if (!isset($_SERVER['HTTP_X_CURRENT_POLICY'])) {
    header('X-Frame-Options: DENY');
}
header("Content-Security-Policy: default-src 'self'");
header('Referrer-Policy: same-origin');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Resource-Policy: same-origin');
header('Origin-Agent-Cluster: ?1');
if ('/private/' === $path) {
    header('Cache-Control: private, no-store');
}
if ('/late-bypass/' === $path) {
    PageCache::bypass();
}
http_response_code(200);
if ('/scoped/' === $path) {
    $dir = getenv('WB_PAGE_CACHE_HTTP_DIR');
    file_put_contents($dir . '/fragment.tpl', "{cache 'profile' scope='user'}{\$name}{/cache}");
    $id = (int) ($_SERVER['HTTP_X_FIXTURE_USER'] ?? 0);
    $engine = (new \Wbengine\Template\Engine())->setTemplateDir($dir)->setCompileDir($dir)
        ->setCacheUserResolver(fn () => $id)->assign('name', 'user-' . $id);
    echo $engine->fetch('fragment.tpl');
    return;
}
echo '<p>' . bin2hex(random_bytes(12)) . '</p>';
echo htmlspecialchars((string) ($_COOKIE['audience'] ?? ''), ENT_QUOTES, 'UTF-8');
