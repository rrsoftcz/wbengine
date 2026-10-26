<?php
namespace Wbengine\Tests\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Content\Migration\HtmlConverter;
use Wbengine\Markdown;

class ContentMigrationConverterTest extends TestCase
{
    public static function examples(): array
    {
        return [
            'headings and text' => ['<h2>Title</h2><p>A <strong>bold</strong> and <em>italic</em> word.</p>'],
            'links images' => ['<p><a href="/path?a=1&amp;b=2" title="Title">Link</a> <img src="/image.jpg" alt="Alt" /></p>'],
            'lists' => ['<ul><li>One</li><li>Two<ul><li>Nested</li></ul></li></ul>'],
            'quote code' => ["<blockquote><p>Quote</p></blockquote><pre><code class=\"language-php\">&lt;x&gt;\n</code></pre>"],
            'inline code' => ['<p>Some <code>a`b</code><br>next <del>gone</del></p>'],
            'table' => ['<table><thead><tr><th>A</th><th align="right">B</th></tr></thead><tbody><tr><td>1</td><td align="right">2</td></tr></tbody></table>'],
            'markdown punctuation' => ['<p>1. [literal] *literal* &amp; &lt;literal&gt;</p>'],
        ];
    }
    #[DataProvider('examples')]
    public function testSupportedSemanticHtmlIsConvertedAndRenderChecked(string $html): void
    {
        self::assertTrue(class_exists(HtmlConverter::class));
        $result = (new HtmlConverter())->convert($html);
        self::assertSame([], $result->issues);
        self::assertTrue($result->safe());
        self::assertNotEmpty((new Markdown())->render($result->markdown));
        self::assertSame(hash('sha256', $html), $result->sourceHash);
    }
    public static function manualCases(): array
    {
        return array_map(static fn($html) => [$html], ['<div class="layout">Layout</div>', '<p style="color:red">Red</p>', '<script>alert(1)</script>', '<a href="javascript:alert(1)">unsafe</a>', '<table><tr><td colspan="2">merged</td></tr></table>', '<iframe src="https://example.com"></iframe>', '<table><thead class="layout"><tr><th>A</th></tr></thead></table>', '<pre><code class="language-a">a</code><code class="language-b">b</code></pre>', '<!DOCTYPE x [<!ENTITY secret SYSTEM "file:///etc/passwd">]><p>&secret;</p>']);
    }
    #[DataProvider('manualCases')]
    public function testLossyOrUnsafeContentRequiresManualRepair(string $html): void
    {
        self::assertTrue(class_exists(HtmlConverter::class));
        $result = (new HtmlConverter())->convert($html);
        self::assertFalse($result->safe());
        self::assertNotEmpty($result->issues);
    }
    public function testConverterInputIsBounded(): void
    {
        self::assertTrue(class_exists(HtmlConverter::class));
        $this->expectException(\LengthException::class);
        (new HtmlConverter(maxBytes: 10))->convert(str_repeat('a', 11));
    }
}
