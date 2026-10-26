<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Application\Application;
use Wbengine\Markdown;
use Wbengine\Template\Engine;
use Wbengine\Template\Exception\TemplateException;

class MarkdownSourceTest extends TestCase
{
    private string $dir;
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-md-source-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/templates', 0700, true);
        mkdir($this->dir . '/compiled', 0700);
        mkdir($this->dir . '/outside', 0700);
    }
    protected function tearDown(): void { $this->remove($this->dir); }
    private function remove(string $path): void
    {
        if (is_link($path) || !is_dir($path)) { unlink($path); return; }
        foreach (glob($path . '/*') ?: [] as $child) { $this->remove($child); }
        rmdir($path);
    }
    private function engine(): Engine { return (new Engine())->setTemplateDir($this->dir . '/templates'); }

    public function testMarkdownIsContentWithoutCompilerOrTemplateExecution(): void
    {
        $source = "# Title\n\n<?php wb_pwn(); ?>\n\n{\$x} {if true}loop{/if}\n\n[x](javascript:alert(1))\n";
        file_put_contents($this->dir . '/templates/page.md', $source);
        $GLOBALS['wb_pwned'] = false;
        $engine = $this->engine()->assign('x', 'SECRET');
        self::assertSame((new Markdown())->render($source), $engine->fetch('page.md'));
        self::assertFalse($GLOBALS['wb_pwned']);
        self::assertStringNotContainsString('SECRET', $engine->fetch('page.md'));
        self::assertSame([], glob($this->dir . '/compiled/*'));
    }

    public function testMarkdownCanBeIncludedInsideNormalLayout(): void
    {
        file_put_contents($this->dir . '/templates/page.MD', "# Content\n\n{\$secret}");
        file_put_contents($this->dir . '/templates/layout.tpl', '<main>{include "page.MD"}</main>');
        self::assertSame("<main><h1>Content</h1>\n<p>{\$secret}</p>\n</main>", $this->engine()->setCompileDir($this->dir . '/compiled')->assign('secret', 'SECRET')->fetch('layout.tpl'));
    }

    public function testMarkdownCannotEscapeTemplateRootsThroughSymlinks(): void
    {
        file_put_contents($this->dir . '/outside/page.md', '# Outside');
        symlink($this->dir . '/outside/page.md', $this->dir . '/templates/link.md');
        $this->expectException(TemplateException::class);
        $this->engine()->fetch('link.md');
    }

    public function testMarkdownCannotEscapeTemplateRootsThroughTraversal(): void
    {
        file_put_contents($this->dir . '/outside/page.md', '# Outside');
        $this->expectException(TemplateException::class);
        $this->engine()->fetch('../outside/page.md');
    }

    public function testAdditionalRootsAndCanonicalMarkdownTargetsRemainContent(): void
    {
        $source = '<?php wb_pwn(); ?>';
        file_put_contents($this->dir . '/outside/page.md', $source);
        symlink($this->dir . '/outside/page.md', $this->dir . '/templates/page.tpl');
        $engine = $this->engine()->addRoot($this->dir . '/outside');
        self::assertSame((new Markdown())->render($source), $engine->fetch('page.tpl'));
        file_put_contents($this->dir . '/templates/actual.tpl', $source);
        symlink($this->dir . '/templates/actual.tpl', $this->dir . '/templates/request.md');
        self::assertSame((new Markdown())->render($source), $engine->fetch('request.md'));
    }

    public function testMarkdownSourceReadIsBounded(): void
    {
        file_put_contents($this->dir . '/templates/large.md', str_repeat('a', 1048577));
        $this->expectException(\LengthException::class);
        $this->engine()->fetch('large.md');
    }

    public function testMarkdownResolverIsLazyAndReused(): void
    {
        self::assertTrue(method_exists(Engine::class, 'setMarkdownResolver'));
        file_put_contents($this->dir . '/templates/page.tpl', 'plain');
        file_put_contents($this->dir . '/templates/page.md', '# heading');
        $calls = 0; $service = new Markdown();
        $engine = $this->engine()->setCompileDir($this->dir . '/compiled')->setMarkdownResolver(function () use (&$calls, $service) { $calls++; return $service; });
        self::assertSame('plain', $engine->fetch('page.tpl'));
        self::assertSame(0, $calls);
        self::assertSame("<h1>heading</h1>\n", $engine->fetch('page.md'));
        $engine->fetch('page.md');
        self::assertSame(1, $calls);
    }

    public function testRendererAdapterForwardsTheLazyMarkdownService(): void
    {
        file_put_contents($this->dir . '/templates/page.md', '# heading');
        $adapter = new \Wbengine\Renderer\Adapter();
        $adapter->setAdapterName('NativeRenderer');
        $adapter->setTemplateDir($this->dir . '/templates');
        $calls = 0;
        $adapter->setMarkdownResolver(function () use (&$calls) { $calls++; return new Markdown(); });
        self::assertSame(0, $calls);
        self::assertSame("<h1>heading</h1>\n", $adapter->fetch('page.md'));
        self::assertSame(1, $calls);
    }

    public function testApplicationMarkdownServiceIsLazyAndStable(): void
    {
        self::assertTrue(method_exists(Application::class, 'getMarkdown'));
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(Markdown::class, $app->getMarkdown());
        self::assertSame($app->getMarkdown(), $app->getMarkdown());
    }
}
