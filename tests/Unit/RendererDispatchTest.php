<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Application\Application;
use Wbengine\Application\Path\Path;
use Wbengine\Config;
use Wbengine\Renderer;

class RendererDispatchTest extends TestCase
{
    private string $dir;
    private $config;

    protected function setUp(): void
    {
        $this->config = Config::$config;
        Config::$config = clone $this->config;
        $this->dir = sys_get_temp_dir() . '/wb-dispatch-' . bin2hex(random_bytes(5));
        mkdir($this->dir . '/tpl', 0700, true);
        mkdir($this->dir . '/compiled', 0700);
        file_put_contents($this->dir . '/tpl/site.tpl', '{$content|raw}{$suffix}');
    }

    protected function tearDown(): void
    {
        Config::$config = $this->config;
        foreach (glob($this->dir . '/*/*') ?: [] as $file) { unlink($file); }
        foreach (glob($this->dir . '/*') ?: [] as $dir) { rmdir($dir); }
        rmdir($this->dir);
    }

    private function dispatch(string $adapter, string $content): string
    {
        $app = $this->createStub(Application::class);
        $app->method('_getObjectPath')->willReturn(new Path());
        $app->method('getVars')->willReturn(['content' => $content, 'suffix' => '<b title="x">unsafe</b>']);
        $renderer = new DispatchFixtureRenderer($app, $this->dir, $adapter);
        ob_start();
        try {
            $renderer->dispatch($app);
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    #[DataProvider('documents')]
    public function testDispatchPreservesRenderedWhitespaceAndEscapesOrdinaryValues(string $adapter, bool $unused, string $content): void
    {
        $this->assertSame($content . '&lt;b title=&quot;x&quot;&gt;unsafe&lt;/b&gt;', $this->dispatch($adapter, $content));
    }

    public static function documents(): array
    {
        $documents = [
            'pre' => "<pre>first  column\n\tsecond column\r\n</pre>\n",
            'textarea' => "<textarea>\n  first\n\nsecond\tvalue\n</textarea>",
            'script comment' => "<script>// preserve this line ending\nwindow.ready = true;\n</script>",
            'script string' => '<script>const value = `first  second' . "\n" . 'third`;</script>',
            'style string' => '<style>.label::before { content: "a  b"; }' . "\n" . '.label { white-space: pre-wrap; }</style>',
            'inline and attribute' => "  <p style=\"white-space: pre-wrap\" title=\"two  spaces\">one  <span>two</span>\nthree</p>  ",
            'JSON script' => '<script type="application/ld+json">{"name":"two  words"}</script>',
        ];
        $cases = [];
        foreach (['NativeRenderer'] as $adapter) {
            foreach ([false, true] as $minimize) {
                foreach ($documents as $name => $content) {
                    $cases[$adapter . ' ' . ($minimize ? 'legacy flag on ' : 'flag off ') . $name] = [$adapter, $minimize, $content];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('adapters')]
    public function testDispatchNeedsNoMinifySetting(string $adapter): void
    {
        $this->assertSame("<pre>a  b\nc</pre>" . '&lt;b title=&quot;x&quot;&gt;unsafe&lt;/b&gt;',
            $this->dispatch($adapter, "<pre>a  b\nc</pre>"));
    }

    public static function adapters(): array { return [['NativeRenderer']]; }
}

class DispatchFixtureRenderer extends Renderer
{
    public function __construct(Application $app, private string $fixtureDir, private string $fixtureAdapter)
    {
        parent::__construct($app);
    }
    public function getRendererAdapterName() { return $this->fixtureAdapter; }
    public function getRendererTemplatesPath() { return $this->fixtureDir . '/tpl'; }
    public function getRendererCacheDir() { return $this->fixtureDir . '/compiled'; }
    public function getConfigDir() { return $this->fixtureDir; }
}
