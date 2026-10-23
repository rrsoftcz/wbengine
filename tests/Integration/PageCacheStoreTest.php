<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Application\Application;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\PageCache;
use Wbengine\Session;
use Wbengine\Site;

/**
 * The rules for storing a finished response (PageCache::capture). A page is stored only
 * when it is the same for every visitor, so each rule has a test that proves a page that
 * breaks it is NOT stored.
 */
class PageCacheStoreTest extends DbTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wb-pc-' . bin2hex(random_bytes(4));
        Cache::setDriver(new FileCache($this->dir), 60);
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        PageCache::setHeaderProvider(null);
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        http_response_code(200);
    }

    /**
     * Arm the page cache for a request to $path, as handle() would, with a fake application.
     */
    private function arm(string $path, array $site = [], bool $sessionModified = false): void
    {
        $site += ['loaded' => true, 'acl' => 0, 'link' => $path];

        $siteDouble = $this->createStub(Site::class);
        $siteDouble->method('isLoaded')->willReturn($site['loaded']);
        $siteDouble->method('getLink')->willReturn($site['link']);
        $siteDouble->method('__get')->willReturnCallback(fn ($name) => 'acl' === $name ? $site['acl'] : null);

        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $this->setPrivate($app, '_site', $siteDouble);
        if ($sessionModified) {
            $session = $this->createStub(Session::class);
            $session->method('wasModified')->willReturn(true);
            $this->setPrivate($app, '_session', $session);
        }

        $page = new \ReflectionClass(PageCache::class);
        foreach (['_app' => $app, '_key' => 'page:' . $path, '_path' => $path, '_ttl' => 60] as $name => $value) {
            $property = $page->getProperty($name);
            $property->setValue(null, $value);
        }
    }

    private function setPrivate(object $object, string $name, $value): void
    {
        $property = new \ReflectionProperty(Application::class, $name);
        $property->setValue($object, $value);
    }

    private function stored(string $path): bool
    {
        Cache::fetch('page:' . $path, $found);
        return $found;
    }

    public function testAPlainPageIsStoredAndTheBodyIsReturnedUnchanged(): void
    {
        $this->arm('/blog/');

        $out = PageCache::capture('<html>hello</html>');

        $this->assertSame('<html>hello</html>', $out);
        $this->assertTrue($this->stored('/blog/'));
        $page = Cache::fetch('page:/blog/', $found);
        $this->assertSame('<html>hello</html>', $page['body']);
        $this->assertSame('"' . md5('<html>hello</html>') . '"', $page['etag']);
    }

    public function testOnlyA200IsStored(): void
    {
        foreach ([404, 500, 302, 301, 401, 403] as $status) {
            $this->arm('/blog/');
            http_response_code($status);

            $this->assertSame('x', PageCache::capture('x'));
            $this->assertFalse($this->stored('/blog/'), "status $status must not be stored");
        }
    }

    public function testAnEmptyOrHugeBodyIsNotStored(): void
    {
        $this->arm('/blog/');
        PageCache::capture('');
        $this->assertFalse($this->stored('/blog/'));

        PageCache::capture(str_repeat('x', PageCache::MAX_BODY + 1));
        $this->assertFalse($this->stored('/blog/'));
    }

    public function testAPageThatWroteToTheSessionIsNotStored(): void
    {
        $this->arm('/login/', [], true);

        PageCache::capture('<form>with a csrf token</form>');

        $this->assertFalse($this->stored('/login/'));
    }

    public function testAnUnknownSiteIsNotStored(): void
    {
        $this->arm('/nowhere/', ['loaded' => false]);

        PageCache::capture('not found page');

        $this->assertFalse($this->stored('/nowhere/'));
    }

    public function testASiteThatNeedsALoginIsNotStored(): void
    {
        foreach ([1, 2, 9] as $acl) {
            $this->arm('/profile/', ['acl' => $acl]);

            PageCache::capture('private');

            $this->assertFalse($this->stored('/profile/'), "acl $acl");
        }
    }

    public function testADeeperPathThatFallsBackToASiteIsNotStored(): void
    {
        // /blog/anything/ is served by the site /blog/, but it must not get an entry of its own.
        $this->arm('/blog/anything/', ['link' => '/blog/']);

        PageCache::capture('fallback');

        $this->assertFalse($this->stored('/blog/anything/'));
        $this->assertFalse($this->stored('/blog/'));
    }

    public function testTheSiteLinkIsComparedIgnoringCaseAndTrailingSlash(): void
    {
        $this->arm('/blog/', ['link' => '/Blog']);

        PageCache::capture('ok');

        $this->assertTrue($this->stored('/blog/'));
    }

    private function withHeaders(array $headers): void
    {
        PageCache::setHeaderProvider(fn () => $headers);
    }

    public function testARedirectHeaderIsNotStoredEvenWithA200Status(): void
    {
        $this->arm('/blog/');
        $this->withHeaders(['Location: /elsewhere/']);

        PageCache::capture('redirecting');

        $this->assertFalse($this->stored('/blog/'));
    }

    public function testJsonAndPlainTextResponsesAreNotStored(): void
    {
        foreach (['application/json', 'text/plain', 'application/xml', 'application/pdf'] as $type) {
            $this->arm('/api/');
            $this->withHeaders(['X-Other: 1', 'Content-Type: ' . $type]);

            PageCache::capture('{"a":1}');

            $this->assertFalse($this->stored('/api/'), $type);
        }
    }

    public function testAnHtmlContentTypeIsStillStored(): void
    {
        $this->arm('/blog/');
        $this->withHeaders(['Content-Type: text/html; charset=UTF-8', 'X-Frame-Options: DENY']);

        PageCache::capture('<p>ok</p>');

        $this->assertTrue($this->stored('/blog/'));
    }

    public function testNoHeadersAtAllIsFine(): void
    {
        $this->arm('/blog/');
        $this->withHeaders([]);

        PageCache::capture('<p>ok</p>');

        $this->assertTrue($this->stored('/blog/'));
    }

    public function testWithoutAnArmedApplicationNothingIsStored(): void
    {
        $page = new \ReflectionClass(PageCache::class);
        $page->getProperty('_app')->setValue(null, null);
        $page->getProperty('_key')->setValue(null, 'page:/blog/');
        $page->getProperty('_path')->setValue(null, '/blog/');

        $this->assertSame('x', PageCache::capture('x'));
        $this->assertFalse($this->stored('/blog/'));
    }
}
