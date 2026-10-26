<?php
/**
 * Isolated synthetic HTTP fixture: native templates + framework file/data/page cache.
 * No CMS database is opened. Native PHP file sessions simulate identity and lock contention;
 * this does not benchmark WBengine database sessions, authentication or production CMS queries.
 * Start with WB_MATRIX_DIR pointing to a fresh writable temporary directory and
 * PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:PORT tests/fixtures/request-matrix.php.
 * Paths: /off|data-warm|page-warm/anonymous|anonymous-session|logged|custom-api|same-session/.
 */
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use Wbengine\Cache;
use Wbengine\Config;
use Wbengine\PageCache;

$directory = getenv('WB_MATRIX_DIR');
if (!$directory || !is_dir($directory)) { http_response_code(500); exit('Set WB_MATRIX_DIR to an isolated temporary directory.'); }
if (!preg_match('~^/(off|data-warm|page-warm)/(anonymous|anonymous-session|logged|custom-api|same-session)/$~D', $_SERVER['REQUEST_URI'], $match)) { http_response_code(404); exit; }
[, $mode, $identity] = $match;
Config::$config = (object) ['debug'=>false, 'cache'=>(object) ['enabled'=>$mode !== 'off', 'pages'=>(object) ['enabled'=>$mode === 'page-warm']], 'i18n'=>(object) ['enabled'=>false]];
Cache::setDriver($mode === 'off' ? new \Wbengine\Cache\NullCache() : new \Wbengine\Cache\FileCache($directory));
class MatrixSite extends \Wbengine\Site
{
    public function isLoaded() { return true; }
    public function getLink() { return $_SERVER['REQUEST_URI']; }
    public function __get($name) { return $name === 'acl' ? 0 : null; }
}
class MatrixApplication extends \Wbengine\Application\Application
{
    public function __construct() {}
    public function getSite() { return new MatrixSite(); }
    public function hasModifiedSession() { return false; }
}
if ($identity !== 'anonymous' && $identity !== 'custom-api') {
    PageCache::bypass();
    session_name('BMARK'); session_save_path($directory); session_cache_limiter(''); session_start();
    $_SESSION['fixture_identity'] = $identity === 'logged' ? 'fixture-user-1' : 'anonymous';
}
if ($identity === 'custom-api') { PageCache::bypass(); }
PageCache::handle(new MatrixApplication());
$data = Cache::remember('matrix-data', static fn () => array_map(static fn ($n) => ['id'=>$n, 'title'=>'Article ' . $n], range(1, 50)));
if ($identity === 'custom-api') { header('Content-Type: application/json'); $body = json_encode(['data'=>$data]); }
else {
    // The template is provisioned once before starting the HTTP server by the test runner.
    $engine = (new \Wbengine\Template\Engine())->setTemplateDir($directory)->setCompileDir($directory);
    $engine->assign('count', count($data)); $body = $engine->fetch('index.tpl');
}
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
// SQL instrumentation is intentionally absent: this fixture does not exercise a DB.
header('X-WB-Benchmark-Memory: ' . memory_get_peak_usage(true));
echo $body;
