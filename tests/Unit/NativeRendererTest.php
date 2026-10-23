<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Renderer\Adapter as RendererFacade;
use Wbengine\Renderer\Adapter\NativeRenderer;
use Wbengine\Renderer\Exception\RendererException;

class NativeRendererTest extends TestCase
{
    private string $dir;
    private string $framework;

    protected function setUp(): void
    {
        $this->framework = dirname(__DIR__, 2);
        $this->dir = sys_get_temp_dir() . '/wb-nr-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/tpl', 0777, true);
        mkdir($this->dir . '/compiled', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir . '/*') ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($this->dir);
    }

    private function renderer(bool $autoEscape = true): NativeRenderer
    {
        $r = new NativeRenderer();
        $r->setTemplateDir($this->dir . '/tpl');
        $r->setCompileDir($this->dir . '/compiled');
        $r->setConfigDir('/ignored');
        $r->setCacheDir('/ignored');
        $r->setAutoEscape($autoEscape);
        return $r;
    }

    public function testAssignAcceptsNameValueAndArraysAndObjects(): void
    {
        file_put_contents($this->dir . '/tpl/t.tpl', '{$a}{$b}{$o->x}');
        $r = $this->renderer();

        $r->assign('a', 'A');
        $r->assign(array('b' => 'B'));
        $r->registerObject('o', (object) array('x' => 'X'));

        $this->assertSame('ABX', $r->fetch('t.tpl'));
    }

    public function testDisplayPrintsAndFetchReturns(): void
    {
        file_put_contents($this->dir . '/tpl/t.tpl', 'hello');
        $r = $this->renderer();

        ob_start();
        $r->display('t.tpl');
        $this->assertSame('hello', ob_get_clean());
        $this->assertSame('hello', $r->fetch('t.tpl'));
    }

    public function testCustomDelimitersAreRefusedClearly(): void
    {
        $this->expectException(RendererException::class);
        $this->renderer()->setLeftDelimiter('<%');
    }

    public function testCacheSwitchesAreHarmless(): void
    {
        $r = $this->renderer();
        $r->enableCache();
        $r->disableCache();
        $r->setCompileCheck(false);
        $this->addToAssertionCount(1);
    }

    public function testFacadeCreatesTheNativeAdapterByName(): void
    {
        file_put_contents($this->dir . '/tpl/t.tpl', '{$x|escape}');
        $facade = new RendererFacade();
        $facade->setAdapterName('NativeRenderer');
        $facade->setTemplateDir($this->dir . '/tpl');
        $facade->setCompileDir($this->dir . '/compiled');
        $facade->setAutoEscape(true);
        $facade->assign('x', '<i>');

        $this->assertSame('&lt;i&gt;', $facade->fetch('t.tpl'));
    }

    // ------------------------------------------------- bundled templates

    public function testBundledLoginTemplateEscapesAndNeverEchoesAPassword(): void
    {
        $r = $this->renderer();
        $r->assign('error', '<script>alert(1)</script>');
        $r->assign('token', '"><x>');
        $r->assign('userName', '"><b>');
        $r->assign('userPassword', 'secret-password');
        $r->assign('captcha', false);

        $html = $r->fetch($this->framework . '/Box/Login/View/Login.tpl');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('"><x>', $html);
        $this->assertStringNotContainsString('"><b>', $html);
        $this->assertStringNotContainsString('secret-password', $html, 'a password is never written back into the page');
    }

    public function testBundledArticleTemplateEscapesDataButKeepsTrustedHtml(): void
    {
        $article = (object) array(
            'title' => '<script>t</script>',
            'author' => '<img src=x onerror=a>',
            'source' => '"><svg>',
            'introtext' => '<p class="intro">Intro <b>bold</b></p>',
            'bodytext' => '<h2>Body</h2>',
        );
        $r = $this->renderer();
        $r->assign('Article', $article);

        $html = $r->fetch($this->framework . '/Box/Article/View/Article.tpl');

        $this->assertStringNotContainsString('<script>t</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('"><svg>', $html);
        $this->assertStringContainsString('<p class="intro">Intro <b>bold</b></p>', $html, 'trusted formatted HTML stays HTML');
        $this->assertStringContainsString('<h2>Body</h2>', $html);
    }

    public function testBundledTemplatesAreIdenticalInSmarty(): void
    {
        $article = (object) array('title' => 'T & <U>', 'author' => 'A', 'source' => 'http://x?a=1&b=2', 'introtext' => '<p>i</p>', 'bodytext' => '<p>b</p>');
        $smarty = new \Wbengine\Renderer\Adapter\SmartyRenderer();
        $smarty->setTemplateDir($this->framework . '/Box');
        $smarty->setCompileDir($this->dir . '/compiled');
        $smarty->assign('Article', $article);

        $native = $this->renderer();
        $native->assign('Article', $article);

        $this->assertSame(
            $smarty->fetch('Article/View/Article.tpl'),
            $native->fetch($this->framework . '/Box/Article/View/Article.tpl')
        );
    }
}
