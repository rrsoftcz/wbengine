<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Application\Application;
use Wbengine\Box\Article;
use Wbengine\Renderer;

class MarkdownSourceArticleTest extends TestCase
{
    private string $dir;
    protected function setUp(): void { $this->dir = sys_get_temp_dir() . '/wb-md-article-' . bin2hex(random_bytes(6)); mkdir($this->dir); }
    protected function tearDown(): void { foreach (glob($this->dir . '/*') ?: [] as $f) { unlink($f); } rmdir($this->dir); }
    private function article(object $row): Article
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $renderer = (new \ReflectionClass(Renderer::class))->newInstanceWithoutConstructor();
        $renderer->setAdapterName('NativeRenderer');
        $renderer->setTemplateDir($this->dir); $renderer->setCompileDir($this->dir);
        $site = new class($app) { public function __construct(private $app) {} public function getParent() { return $this->app; } };
        $parent = new class($site) { public function __construct(private $site) {} public function getSite() { return $this->site; } };
        return new class($row, $renderer, $parent) extends Article {
            public function __construct($row, private $renderer, private $parent) { $this->article = $row; }
            public function getRenderer() { return $this->renderer; }
            public function getParent() { return $this->parent; }
        };
    }
    private function row(array $fields = []): object { return (object) ($fields + ['title' => 'Title', 'author' => '', 'source' => '', 'introtext' => '', 'bodytext' => '']); }

    public function testExplicitMarkdownArticleIsSafelyRenderedInsideExistingBox(): void
    {
        $source = '**Strong** <script>alert(1)</script> [x](javascript:alert(1)) {$secret}';
        $row = $this->row(['content_format' => 'markdown', 'introtext' => $source]);
        $html = $this->article($row)->getArticleBox();
        self::assertStringContainsString('<div class="story">', $html);
        self::assertStringContainsString('<strong>Strong</strong> &lt;script&gt;alert(1)&lt;/script&gt; <a href="">x</a> {$secret}', $html);
        self::assertSame($source, $row->introtext, 'Formatting must not replace the source row with rendered HTML');
    }

    public function testLegacyRowsKeepExactStoredHtmlWithoutReprocessing(): void
    {
        $source = '<p class="old">legacy <b>HTML</b></p>';
        foreach ([[], ['content_format' => 'legacy_html']] as $format) {
            self::assertStringContainsString($source, $this->article($this->row($format + ['introtext' => $source]))->getArticleBox());
        }
    }

    public function testTranslatedFieldsUseTheirOwnFormatWhileFallbackKeepsBaseFormat(): void
    {
        $row = $this->row(['content_format' => 'legacy_html', 'introtext' => '<p class="old">Base</p>', 'bodytext' => '**Translated**', 'bodytext_format' => 'markdown']);
        $html = $this->article($row)->getArticleBox();
        self::assertStringContainsString('<p class="old">Base</p>', $html);
        self::assertStringContainsString('<p><strong>Translated</strong></p>', $html);
        $row = $this->row(['content_format' => 'markdown', 'introtext' => '**Base**', 'bodytext' => '<p class="old">Translated</p>', 'bodytext_format' => 'legacy_html']);
        $html = $this->article($row)->getArticleBox();
        self::assertStringContainsString('<p><strong>Base</strong></p>', $html);
        self::assertStringContainsString('<p class="old">Translated</p>', $html);
    }

    public function testUnknownContentFormatIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->article($this->row(['content_format' => 'html', 'introtext' => 'text']))->getArticleBox();
    }
}
