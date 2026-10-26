<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Application\Application;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\PageCache;
use Wbengine\Session\Csrf;
use Wbengine\Template\Engine;
use Wbengine\Template\FragmentCache;
use Wbengine\Tests\Support\MemorySession;

class CsrfTest extends TestCase
{
    private array $server;
    private array $post;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->post = $_POST;
        $_SERVER = $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_POST = $this->post;
        Cache::setDriver(null);
        (new \ReflectionProperty(PageCache::class, '_bypassed'))->setValue(null, false);
        if (class_exists(FragmentCache::class) && property_exists(FragmentCache::class, '_bypassed')) {
            (new \ReflectionProperty(FragmentCache::class, '_bypassed'))->setValue(null, false);
        }
    }

    public function testTokensAreStableForTabsAndSeparatedByPurposeAndSession(): void
    {
        $session = new MemorySession();
        $csrf = $session->getCsrf();
        $this->assertSame($session, $csrf->getParent());
        $this->assertSame($csrf, $session->getCsrf());
        $first = $csrf->token('article.edit');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $first);
        $this->assertSame($first, $csrf->token('article.edit'));
        $this->assertTrue($csrf->verify($first, 'article.edit'));
        $this->assertSame(1, $session->writes);
        $this->assertFalse($csrf->verify($first, 'article.delete'));
        $this->assertNotSame($first, $csrf->token('article.delete'));
        $other = new MemorySession();
        $this->assertFalse($other->getCsrf()->verify($first, 'article.edit'));
        $this->assertSame(0, $other->writes);
        $this->assertNotSame($first, $other->getCsrf()->token('article.edit'));
    }

    #[DataProvider('invalidTokens')]
    public function testInvalidTokenShapesCannotPassOrCreateState($token): void
    {
        $session = new MemorySession();
        $csrf = $session->getCsrf();
        $this->assertFalse($csrf->verify($token));
        $this->assertSame(0, $session->writes);
        $csrf->token();
        $this->assertFalse($csrf->verify($token));
    }

    public static function invalidTokens(): array
    {
        return [[null], [''], [[]], [new \stdClass()], [true], [0], ['wrong'], [str_repeat('a', 63)], [str_repeat('g', 64)], [str_repeat('a', 65)]];
    }

    #[DataProvider('requestMethods')]
    public function testOnlyUnsafeMethodsCanValidateARequest(string $method, bool $valid): void
    {
        $csrf = (new MemorySession())->getCsrf();
        $_POST['token'] = $csrf->token('save');
        $_SERVER['REQUEST_METHOD'] = $method;
        $this->assertSame($valid, $csrf->validateRequest('save'));
    }

    public static function requestMethods(): array
    {
        return [['POST', true], ['PUT', true], ['PATCH', true], ['DELETE', true], ['GET', false], ['HEAD', false], ['OPTIONS', false], ['', false]];
    }

    public function testAjaxHeaderWorksAndInvalidHeaderCannotFallBackToPost(): void
    {
        $csrf = (new MemorySession())->getCsrf();
        $token = $csrf->token('save');
        $_SERVER = ['REQUEST_METHOD' => 'PATCH', 'HTTP_X_CSRF_TOKEN' => $token];
        $this->assertTrue($csrf->validateRequest('save'));
        $_POST['token'] = $token;
        foreach (['', 'wrong', [], true] as $header) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = $header;
            $this->assertFalse($csrf->validateRequest('save'));
        }
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $this->assertTrue($csrf->validateRequest('save'));
        $_POST = [];
        $_SERVER['QUERY_STRING'] = 'token=' . $token;
        $this->assertFalse($csrf->validateRequest('save'));
    }

    public function testResetInvalidatesAllPurposes(): void
    {
        $csrf = (new MemorySession())->getCsrf();
        $old = $csrf->token('save');
        $csrf->reset();
        $this->assertFalse($csrf->verify($old, 'save'));
        $this->assertNotSame($old, $csrf->token('save'));
    }

    #[DataProvider('badPurposes')]
    public function testInvalidPurposesAreRejected(string $purpose): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MemorySession())->getCsrf()->token($purpose);
    }

    public static function badPurposes(): array { return [[''], [str_repeat('x', 129)]]; }

    public function testTokensBypassCachedPublicAndUserFragmentsIncludingTheirWrappers(): void
    {
        $dir = sys_get_temp_dir() . '/wb-csrf-cache-' . bin2hex(random_bytes(5));
        Cache::setDriver(new FileCache($dir));
        try {
            $engine = (new Engine())->setCacheUserResolver(fn () => 1);
            $this->assertSame('old-public', $engine->cache('public', 60, fn () => 'old-public'));
            $this->assertSame('old-user', $engine->cache('user', 60, fn () => 'old-user', 'user'));
            $csrf = (new MemorySession())->getCsrf();
            $token = $engine->cache('wrapper', 60, fn () => $csrf->token('save'), 'user');
            $this->assertTrue(PageCache::isBypassed());
            $this->assertSame('new-public', $engine->cache('public', 60, fn () => 'new-public'));
            $this->assertSame('new-user', $engine->cache('user', 60, fn () => 'new-user', 'user'));
            // A wrapper that issued a token must not have been stored either.
            (new \ReflectionProperty(FragmentCache::class, '_bypassed'))->setValue(null, false);
            $this->assertSame('fresh-wrapper', $engine->cache('wrapper', 60, fn () => 'fresh-wrapper', 'user'));
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $token);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) { unlink($file); }
            rmdir($dir);
        }
    }

    public function testApplicationUsesTheSessionOwnedCsrfServiceLazily(): void
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $this->assertFalse($app->hasModifiedSession());
        $session = new MemorySession();
        (new \ReflectionProperty(Application::class, '_session'))->setValue($app, $session);
        $this->assertSame($session->getCsrf(), $app->getCsrf());
        $this->assertSame(0, $session->writes);
    }
}
