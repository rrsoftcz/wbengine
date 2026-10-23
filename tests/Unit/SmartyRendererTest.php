<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Config;
use Wbengine\Renderer\Adapter as RendererAdapter;
use Wbengine\Renderer\Adapter\SmartyRenderer;

class SmartyRendererTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-smarty-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/tpl', 0777, true);
        mkdir($this->dir . '/compiled', 0777, true);
        mkdir($this->dir . '/config', 0777, true);
        file_put_contents($this->dir . '/tpl/hello.tpl', 'Hello {$name}, {$obj->title}!');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{tpl,compiled,config}/*', GLOB_BRACE) ?: [] as $f) {
            @unlink($f);
        }
        foreach (['tpl', 'compiled', 'config'] as $d) {
            @rmdir($this->dir . '/' . $d);
        }
        @rmdir($this->dir);
    }

    private function renderer(): SmartyRenderer
    {
        $r = new SmartyRenderer();
        $r->setTemplateDir($this->dir . '/tpl');
        $r->setCompileDir($this->dir . '/compiled');
        $r->setConfigDir($this->dir . '/config');
        return $r;
    }

    public function testAssignAndFetch(): void
    {
        $r = $this->renderer();
        $r->assign('name', 'World');
        $r->registerObject('obj', (object)array('title' => 'Box'));

        $this->assertSame('Hello World, Box!', $r->fetch('hello.tpl'));
    }

    public function testCompileCheckCanBeSwitchedOffAndOn(): void
    {
        $r = $this->renderer();
        $r->setCompileCheck(false);
        $r->assign('name', 'A');
        $r->registerObject('obj', (object)array('title' => 'T'));
        $this->assertSame('Hello A, T!', $r->fetch('hello.tpl'));

        $r->setCompileCheck(true);
        $this->assertSame('Hello A, T!', $r->fetch('hello.tpl'));
    }

    public function testDelimitersCanBeChanged(): void
    {
        file_put_contents($this->dir . '/tpl/delim.tpl', 'x <%$name%> y');
        $r = $this->renderer();
        $r->setLeftDelimiter('<%');
        $r->setRightDelimiter('%>');
        $r->assign('name', 'N');

        $this->assertSame('x N y', $r->fetch('delim.tpl'));
    }

    public function testFacadeWorksWithoutArgumentErrors(): void
    {
        $facade = new RendererAdapter();
        $facade->setAdapterName('SmartyRenderer');
        $facade->setTemplateDir($this->dir . '/tpl');
        $facade->setCompileDir($this->dir . '/compiled');
        $facade->setConfigDir($this->dir . '/config');
        $facade->setCompileCheck(false);
        $facade->setLeftDelimiter('{');
        $facade->setRightDelimiter('}');
        $facade->registerObject('obj', (object)array('title' => 'F'));
        $facade->assign('name', 'Facade');

        $this->assertSame('Hello Facade, F!', $facade->fetch('hello.tpl'));
    }

    public function testCompileCheckDefaultsToOnWhenNotConfigured(): void
    {
        $this->assertTrue(Config::getRendererCompileCheck());
    }
}
