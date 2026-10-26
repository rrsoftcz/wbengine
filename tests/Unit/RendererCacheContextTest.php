<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Application\Application;
use Wbengine\Application\Path\Path;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\PageCache;
use Wbengine\Renderer;
use Wbengine\Session;
use Wbengine\User;

class RendererCacheContextTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-render-context-' . bin2hex(random_bytes(5));
        mkdir($this->dir . '/tpl', 0700, true);
        mkdir($this->dir . '/compiled', 0700);
        Cache::setDriver(new FileCache($this->dir . '/cache'));
        file_put_contents($this->dir . '/tpl/public.tpl', "{cache 'name'}{\$name}{/cache}");
        file_put_contents($this->dir . '/tpl/user.tpl', "{cache 'name' scope='user'}{\$name}{/cache}");
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        (new \ReflectionProperty(PageCache::class, '_bypassed'))->setValue(null, false);
        foreach (glob($this->dir . '/*/*') as $file) {
            unlink($file);
        }
        foreach (glob($this->dir . '/*') as $dir) {
            rmdir($dir);
        }
        rmdir($this->dir);
    }

    private function renderer(Application $app): Renderer
    {
        return new CacheContextRenderer($app, $this->dir);
    }

    public function testRendererKeepsUserContextLazyForPublicFragments(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('_getObjectPath')->willReturn(new Path());
        $app->expects($this->never())->method('getClassUser');
        $renderer = $this->renderer($app);
        $renderer->assign('name', 'Public');
        $this->assertSame('Public', $renderer->fetch('public.tpl'));
    }

    public function testRendererUsesCurrentAuthenticatedSessionInsteadOfStaleUserResource(): void
    {
        $id = 7;
        $session = $this->createStub(Session::class);
        $session->method('getUserId')->willReturnCallback(function () use (&$id) { return $id; });
        $user = $this->createStub(User::class);
        $user->method('getUserIsLogged')->willReturn(true);
        $user->method('getUserId')->willReturn(7);
        $app = $this->createStub(Application::class);
        $app->method('_getObjectPath')->willReturn(new Path());
        $app->method('getClassUser')->willReturn($user);
        $app->method('getSession')->willReturn($session);
        $renderer = $this->renderer($app);
        $renderer->assign('name', 'Alice');
        $this->assertSame('Alice', $renderer->fetch('user.tpl'));
        $id = 8;
        $renderer->assign('name', 'Bob');
        $this->assertSame('Bob', $renderer->fetch('user.tpl'));
        $id = 7;
        $renderer->assign('name', 'changed');
        $this->assertSame('Alice', $renderer->fetch('user.tpl'));
    }
}

class CacheContextRenderer extends Renderer
{
    private string $fixtureDir;

    public function __construct(Application $app, string $dir)
    {
        $this->fixtureDir = $dir;
        parent::__construct($app);
    }

    public function getRendererAdapterName() { return 'NativeRenderer'; }
    public function getRendererTemplatesPath() { return $this->fixtureDir . '/tpl'; }
    public function getRendererCacheDir() { return $this->fixtureDir . '/compiled'; }
    public function getConfigDir() { return $this->fixtureDir; }
}
