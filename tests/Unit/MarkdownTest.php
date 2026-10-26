<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Markdown\Node;
use Wbengine\Markdown;
use Wbengine\Markdown\Parser;
use Wbengine\Markdown\HtmlRenderer;

class MarkdownTest extends TestCase
{
    public function testBlockAndInlineTreeRendersSemanticHtml(): void
    {
        $source = "# Heading\n\nA **strong** and *emphasized* [link](https://example.com).\n\n> Quote\n\n- one\n- two\n\n```php\n<x>\n```\n";
        $expected = "<h1>Heading</h1>\n<p>A <strong>strong</strong> and <em>emphasized</em> <a href=\"https://example.com\">link</a>.</p>\n<blockquote>\n<p>Quote</p>\n</blockquote>\n<ul>\n<li>one</li>\n<li>two</li>\n</ul>\n<pre><code class=\"language-php\">&lt;x&gt;\n</code></pre>\n";
        self::assertTrue(class_exists(Markdown::class), 'Markdown facade must be implemented');
        self::assertSame($expected, (new Markdown())->render($source));
        $tree = (new Parser())->parse($source);
        self::assertSame('document', $tree->type);
        self::assertSame('heading', $tree->children[0]->type);
        self::assertSame($expected, (new HtmlRenderer())->render($tree));
    }

    public function testSafeOutputAndGfmExtensions(): void
    {
        self::assertTrue(class_exists(Markdown::class), 'Markdown facade must be implemented');
        $markdown = new Markdown();
        self::assertSame("<p>text &lt;script&gt;alert(1)&lt;/script&gt; <a href=\"\">x</a> <img src=\"\" alt=\"x\" /></p>\n", $markdown->render('text <script>alert(1)</script> [x](javascript:alert(1)) ![x](data:text/html,x)'));
        self::assertSame("<p><del>gone</del></p>\n<ul>\n<li><input checked=\"\" disabled=\"\" type=\"checkbox\"> done</li>\n<li><input disabled=\"\" type=\"checkbox\"> todo</li>\n</ul>\n", $markdown->render("~~gone~~\n\n- [x] done\n- [ ] todo"));
        self::assertSame("<table>\n<thead>\n<tr>\n<th align=\"left\">a</th>\n<th align=\"right\">b</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td align=\"left\">1</td>\n<td align=\"right\">2</td>\n</tr>\n</tbody>\n</table>\n", $markdown->render("a | b\n:--- | ---:\n1 | 2"));
    }


    public function testInlineNestingLimitFailsClosed(): void
    {
        $this->expectException(\LengthException::class);
        (new Parser(maxDepth: 3))->parse('*a **b *c **d e** c* b** a*');
    }

    public function testWorkBudgetBoundsRepeatedUnmatchedOpeners(): void
    {
        $this->expectException(\LengthException::class);
        (new Parser(maxWork: 100))->parse(str_repeat('[', 30));
    }

    public function testResourceBudgetAllowsOrdinaryLongProse(): void
    {
        $source = str_repeat('ordinary prose ', 10000);
        self::assertSame('<p>' . rtrim($source) . "</p>\n", (new Markdown())->render($source));
    }

    public function testNodeCountLimitFailsClosed(): void
    {
        $this->expectException(\LengthException::class);
        (new Parser(maxNodes: 10))->parse('a b c d e f g h i j k l');
    }


    public static function unsafeDestinations(): array
    {
        return array_map(static fn($url) => [$url], ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'java&#x0a;script:alert(1)', 'data:image/svg+xml,x', 'vbscript:x', 'file:///etc/passwd', 'ftp://example.com']);
    }

    #[DataProvider('unsafeDestinations')]
    public function testUnsafeSchemesCannotBecomeActiveLinksOrImages(string $url): void
    {
        self::assertSame("<p><a href=\"\">x</a> <img src=\"\" alt=\"x\" /></p>\n", (new Markdown())->render('[x](' . $url . ') ![x](' . $url . ')'));
    }

    public function testNormalizationAndDocumentReferenceIsolation(): void
    {
        $m = new Markdown();
        self::assertSame("<p>A\nB\nC\u{fffd}</p>\n", $m->render("A\r\nB\rC\0"));
        self::assertSame("<p><a href=\"/one\">x</a></p>\n", $m->render("[x]\n\n[x]: /one"));
        self::assertSame("<p>[x]</p>\n", $m->render('[x]'));
        self::assertSame("<p>\u{fffd}</p>\n", $m->render("\xff"));
    }

    public function testContentCannotExecuteTemplatesOrBreakHtmlAttributes(): void
    {
        $m = new Markdown();
        self::assertSame("<p>{{ phpinfo() }} &lt;img src=x onerror=alert(1)&gt;</p>\n", $m->render('{{ phpinfo() }} <img src=x onerror=alert(1)>'));
        self::assertSame("<p><a href=\"/ok\" title=\"&quot; onmouseover=&quot;x\">x</a></p>\n", $m->render('[x](/ok "&quot; onmouseover=&quot;x")'));
        self::assertSame("<pre><code class=\"language-&quot;onmouseover=&quot;x\">text\n</code></pre>\n", $m->render("```\"onmouseover=\"x\ntext\n```"));
    }

    public function testLazyContainerNestingIsBoundedBeforeRecursiveBlockParsing(): void
    {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('work limit');
        (new Parser(maxDepth: 3, maxWork: 10))->parse('> - - - - - - x');
    }

    public function testRendererRejectsDeepImageAltTrees(): void
    {
        $tree = new Node('text', 'alt');
        for ($i = 0; $i < 5; $i++) { $tree = new Node('image', children: [$tree], attributes: ['destination' => '/ok']); }
        $this->expectException(\LengthException::class);
        (new HtmlRenderer(maxDepth: 3))->render($tree);
    }

    public function testGfmTaskMarkerMayBeFollowedByNewline(): void
    {
        self::assertSame("<ul>\n<li><input checked=\"\" disabled=\"\" type=\"checkbox\"> task</li>\n</ul>\n", (new Markdown())->render("- [X]\n  task"));
    }

    public function testOrdinaryLinkAndCodeHeavyDocumentsFitDefaultBudget(): void
    {
        self::assertSame('<p>' . rtrim(str_repeat('<a href="/x">x</a> <code>y</code> &amp; ', 1000)) . "</p>\n", (new Markdown())->render(str_repeat('[x](/x) `y` &amp; ', 1000)));
    }

    public function testDelimiterReductionIsBoundedByWorkBudget(): void
    {
        $this->expectException(\LengthException::class);
        (new Parser(maxWork: 1000))->parse(str_repeat('*x* ', 30));
    }

    public function testAngleDestinationAllowsEscapedLessThan(): void
    {
        self::assertSame("<p><a href=\"foo%3Cbar\">x</a></p>\n", (new Markdown())->render('[x](<foo\<bar>)'));
        self::assertSame("<p><a href=\"foo%3Cbar\">x</a></p>\n", (new Markdown())->render("[x]\n\n[x]: <foo\\<bar>"));
    }

    public function testParenthesizedTitleRejectsUnescapedNestedOpeningParenthesis(): void
    {
        self::assertSame("<p>[x](foo (title (nested)))</p>\n", (new Markdown())->render('[x](foo (title (nested)))'));
        self::assertSame("<p><a href=\"foo\" title=\"title (nested)\">x</a></p>\n", (new Markdown())->render('[x](foo (title \(nested\)))'));
    }

    public function testEscapedClosingBracketInFullReferenceLabel(): void
    {
        self::assertSame("<p><a href=\"/url\">x</a></p>\n", (new Markdown())->render("[x][a\\]b]\n\n[a\\]b]: /url"));
    }

    public function testReferenceLabelLimitCountsUnicodeCharacters(): void
    {
        $label = str_repeat('é', 500);
        self::assertSame("<p><a href=\"/ok\">x</a></p>\n", (new Markdown())->render('[x][' . $label . "]\n\n[" . $label . ']: /ok'));
    }

    public function testInvalidUtf8InReferenceCandidateDoesNotEmitWarnings(): void
    {
        set_error_handler(static function (int $severity, string $message): never { throw new \ErrorException($message, 0, $severity); });
        try {
            self::assertSame("<p>[x\u{fffd}]</p>\n", (new Markdown())->render("[x\xff]\n\n[x]: /a"));
        } finally { restore_error_handler(); }
    }

    public function testReferenceAmplificationCannotExceedOutputBudget(): void
    {
        $source = str_repeat('[x] ', 10) . "\n\n[x]: /" . str_repeat('a', 40);
        $this->expectException(\LengthException::class);
        (new HtmlRenderer(maxOutputBytes: 128))->render((new Parser())->parse($source));
    }

    public function testRendererBudgetIsResetBetweenDocuments(): void
    {
        $renderer = new HtmlRenderer(maxOutputBytes: 11);
        $tree = (new Parser())->parse('123');
        self::assertSame("<p>123</p>\n", $renderer->render($tree));
        self::assertSame("<p>123</p>\n", $renderer->render($tree));
    }

    public function testBackslashCannotEscapeDestinationWhitespaceRestrictions(): void
    {
        foreach (["[x](foo\\ bar)", "[x](<a\\\nb>)", "[x]\n\n[x]: <a\\\nb>", "[x]\n\n[x]: foo\\ bar"] as $source) {
            self::assertStringNotContainsString('<a href=', (new Markdown())->render($source));
        }
    }

    public function testInputSizeLimitFailsClosed(): void
    {
        self::assertTrue(class_exists(Parser::class), 'Parser must be implemented');
        $this->expectException(\LengthException::class);
        (new Parser(maxBytes: 4))->parse('12345');
    }

    public function testNestingLimitFailsClosed(): void
    {
        self::assertTrue(class_exists(Parser::class), 'Parser must be implemented');
        $this->expectException(\LengthException::class);
        (new Parser(maxDepth: 3))->parse('> > > > nested');
    }
}
