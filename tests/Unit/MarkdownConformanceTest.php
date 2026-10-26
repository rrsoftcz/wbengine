<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Markdown;
use Wbengine\Markdown\HtmlRenderer;
use Wbengine\Markdown\Node;
use Wbengine\Markdown\Parser;

class MarkdownConformanceTest extends TestCase
{
    public static function examples(): array
    {
        $path = dirname(__DIR__) . '/fixtures/markdown/';
        $cases = json_decode(file_get_contents($path . 'commonmark-0.31.2.json'), true, 512, JSON_THROW_ON_ERROR);
        $deviations = json_decode(file_get_contents($path . 'safe-deviations.json'), true, 512, JSON_THROW_ON_ERROR);
        $result = [];
        foreach ($cases as $case) { $result['CommonMark ' . $case['example'] . ': ' . $case['section']] = [$case, $deviations[$case['example']] ?? null]; }
        return $result;
    }

    #[DataProvider('examples')]
    public function testOfficialExampleAndExactSafeOutput(array $case, ?array $deviation): void
    {
        $tree = (new Parser())->parse($case['markdown']);
        $replacements = [];
        $projected = $this->project($tree, $replacements);
        // Test-only projection restores only raw nodes and disallowed URL schemes.
        // Exact comparison still checks every block/inline rule in HTML examples.
        self::assertSame($case['html'], strtr((new HtmlRenderer())->render($projected), $replacements));
        if ($deviation !== null) {
            self::assertNotEmpty($deviation['reason']);
            self::assertNotSame($case['html'], $deviation['html'], 'Remove obsolete safety exception');
        }
        self::assertSame($deviation['html'] ?? $case['html'], (new Markdown())->render($case['markdown']));
    }

    public function testCorpusIdentityAndCompleteSafetyExceptionRegistry(): void
    {
        $path = dirname(__DIR__) . '/fixtures/markdown/';
        $manifest = json_decode(file_get_contents($path . 'sources.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($manifest['commonmark']['sha256'], hash_file('sha256', $path . 'commonmark-0.31.2.json'));
        self::assertCount(652, self::examples());
        $deviations = json_decode(file_get_contents($path . 'safe-deviations.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(76, $deviations);
        foreach ($deviations as $number => $case) { self::assertGreaterThanOrEqual(1, $number); self::assertLessThanOrEqual(652, $number); }
    }

    private function project(Node $node, array &$replacements): Node
    {
        $a = $node->attributes;
        if (in_array($node->type, ['html', 'html_block'], true)) {
            $key = 'WBRAWMARKER' . count($replacements) . 'END';
            $replacements[$key] = $node->literal;
            return new Node('text', $key);
        }
        if (in_array($node->type, ['link', 'image'], true) && preg_match('/^([a-z][a-z0-9+.-]*):/i', $a['destination'], $m) && !in_array(strtolower($m[1]), ['http', 'https', 'mailto'], true)) {
            $key = 'WBURLMARKER' . count($replacements) . 'END';
            $replacements[$key] = $a['destination'];
            $a['destination'] = $key;
        }
        $children = [];
        foreach ($node->children as $child) { $children[] = $this->project($child, $replacements); }
        return new Node($node->type, $node->literal, $children, $a);
    }
}
