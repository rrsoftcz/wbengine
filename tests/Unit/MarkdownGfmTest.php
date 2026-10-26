<?php
namespace Wbengine\Tests\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Markdown;

class MarkdownGfmTest extends TestCase
{
    public static function examples(): array
    {
        $cases = json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/markdown/gfm-extensions.json'), true, 512, JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($cases as $case) { $out[$case['section'] . '/' . $case['example']] = [$case]; }
        return $out;
    }

    #[DataProvider('examples')]
    public function testOfficialGfmExtension(array $case): void
    {
        self::assertSame($case['html'], (new Markdown())->render($case['markdown']));
    }
}
