<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\Db;
use Wbengine\Site;
use Wbengine\Site\SiteModel;

class SiteDiagnosticsTest extends DbTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wb-site-diagnostics-' . bin2hex(random_bytes(5));
        Cache::setDriver(new FileCache($this->dir), 60);
        Db::insert('cms_sites', ['link' => '/cached/', 'visible' => 1, 'strict' => 0,
            'title' => 'Cached page', 'html_title' => 'Cached title', 'acl' => 0]);
        $site = $this->createStub(Site::class);
        $site->method('getUrl')->willReturn('/cached/');
        $site->method('isUrlStrict')->willReturn(false);
        (new SiteModel())->loadSiteData($site);
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        foreach (glob($this->dir . '/*') ?: [] as $file) { unlink($file); }
        rmdir($this->dir);
    }

    private function runFresh(string $mode): array
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/fixtures/site-diagnostics.php', $mode, $this->dir],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors . $output);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testProductionInitializationOnlyQueriesSiteSelection(): void
    {
        $result = $this->runFresh('production');
        $this->assertSame(1, $result['queries'], 'one bound query selects the site using its database collation');
        $this->assertSame(1, $result['connections']);
        $this->assertTrue($result['adapterCreated']);
        $this->assertTrue($result['loaded']);
        $this->assertSame('Cached title', $result['vars']['meta']['title']);
        foreach (['dbinfo', 'phpversion', 'server'] as $key) {
            $this->assertArrayNotHasKey($key, $result['vars']);
        }
    }

    #[DataProvider('diagnosticModes')]
    public function testDebugAndExplicitDiagnosticsKeepLegacyTemplateValues(string $mode): void
    {
        $result = $this->runFresh($mode);
        if ('debug' !== $mode) {
            $this->assertSame(1, $result['initialized']['connections']);
            $this->assertTrue($result['initialized']['adapterCreated']);
            $this->assertTrue($result['explicitReturnsSite']);
        }
        $this->assertSame(1, $result['connections']);
        $this->assertSame(1, $result['queries'], 'diagnostics add no SQL beyond site selection');
        $this->assertTrue($result['adapterCreated']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/D', $result['vars']['dbinfo']);
        $this->assertSame(PHP_VERSION, $result['vars']['phpversion']);
        $this->assertSame('missing-server' === $mode ? '' : 'WBengine diagnostics fixture', $result['vars']['server']);
    }

    public static function diagnosticModes(): array
    {
        return [['debug'], ['explicit'], ['missing-server']];
    }
}
