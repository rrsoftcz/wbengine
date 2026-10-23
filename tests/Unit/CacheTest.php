<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Cache;
use Wbengine\Cache\ApcuCache;
use Wbengine\Cache\Exception\CacheException;
use Wbengine\Cache\FileCache;
use Wbengine\Cache\NullCache;

class CacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-cache-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function useFileCache(int $ttl = 60): FileCache
    {
        $driver = new FileCache($this->dir);
        Cache::setDriver($driver, $ttl);
        return $driver;
    }

    public function testDisabledCacheAlwaysCallsTheLoader(): void
    {
        Cache::setDriver(new NullCache());
        $calls = 0;
        $loader = function () use (&$calls) { $calls++; return array(1); };

        Cache::remember('k', $loader);
        Cache::remember('k', $loader);

        $this->assertSame(2, $calls);
        $this->assertFalse(Cache::isEnabled());
    }

    public function testHitDoesNotCallTheLoaderAgain(): void
    {
        $this->useFileCache();
        $calls = 0;
        $loader = function () use (&$calls) { $calls++; return array('a' => 1, 'b' => array(2, 'x' => null)); };

        $first = Cache::remember('k', $loader);
        $second = Cache::remember('k', $loader);

        $this->assertSame(1, $calls);
        $this->assertSame($first, $second);
        $this->assertTrue(Cache::isEnabled());
    }

    public function testEmptyResultsAreNotStored(): void
    {
        $this->useFileCache();
        $calls = 0;
        $loader = function () use (&$calls) { $calls++; return array(); };

        Cache::remember('missing', $loader);
        Cache::remember('missing', $loader);

        $this->assertSame(2, $calls);
        $this->assertSame(array(), glob($this->dir . '/*.cache.php'));
    }

    public function testEmptyArrayIsStoredWhenRequested(): void
    {
        $this->useFileCache();
        $calls = 0;
        $loader = function () use (&$calls) { $calls++; return array(); };

        Cache::remember('bounded', $loader, null, true);
        $second = Cache::remember('bounded', $loader, null, true);

        $this->assertSame(1, $calls);
        $this->assertSame(array(), $second);
    }

    public function testNullIsNeverStoredEvenWhenEmptyIsAllowed(): void
    {
        $this->useFileCache();
        $calls = 0;
        $loader = function () use (&$calls) { $calls++; return null; };

        Cache::remember('k', $loader, null, true);
        Cache::remember('k', $loader, null, true);

        $this->assertSame(2, $calls);
    }

    public function testExpiredEntryIsReloaded(): void
    {
        $this->useFileCache();
        $calls = 0;
        $loader = function () use (&$calls) { $calls++; return array($calls); };

        Cache::remember('k', $loader, -1);
        $second = Cache::remember('k', $loader);

        $this->assertSame(2, $calls);
        $this->assertSame(array(2), $second);
    }

    public function testFlushRemovesEverything(): void
    {
        $this->useFileCache();
        Cache::remember('a', fn () => array(1));
        Cache::remember('b', fn () => array(2));
        $this->assertCount(2, glob($this->dir . '/*.cache.php'));

        Cache::flush();

        $this->assertSame(array(), glob($this->dir . '/*.cache.php'));
        $calls = 0;
        Cache::remember('a', function () use (&$calls) { $calls++; return array(1); });
        $this->assertSame(1, $calls);
    }

    public function testValuesSurviveStringsQuotesAndUnicode(): void
    {
        $this->useFileCache();
        $value = array('title' => "O'Brien \"q\" \\ \$x {\$y} \u{010D}e\u{0161}tina \u{1F600}", 'n' => 0, 'f' => 1.5, 'b' => false);

        Cache::remember('k', fn () => $value);

        $this->assertSame($value, Cache::remember('k', fn () => array('different')));
    }

    public function testKeysCannotEscapeTheCacheDirectory(): void
    {
        $this->useFileCache();

        Cache::remember('../../evil', fn () => array(1));

        $files = glob($this->dir . '/*');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.cache.php', $files[0]);
    }

    public function testUnwritableDirectoryIsReported(): void
    {
        $this->expectException(CacheException::class);
        new FileCache('/proc/definitely/not/writable');
    }

    public function testApcuDriverWhenAvailable(): void
    {
        if (!function_exists('apcu_fetch') || !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('APCu is not enabled for the CLI.');
        }
        Cache::setDriver(new ApcuCache('wbtest:'), 60);
        $calls = 0;
        $loader = function () use (&$calls) { $calls++; return array('x' => 1); };

        Cache::remember('k', $loader);
        Cache::remember('k', $loader);
        $this->assertSame(1, $calls);

        Cache::flush();
        Cache::remember('k', $loader);
        $this->assertSame(2, $calls);
    }
}
