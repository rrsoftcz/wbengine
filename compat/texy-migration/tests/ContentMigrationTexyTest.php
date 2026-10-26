<?php
namespace Wbengine\Compat\TexyMigration\Tests;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;
use Wbengine\Content\Migration\Converter;
use Wbengine\Markdown;

class ContentMigrationTexyTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOptionalBridgeConvertsThroughTheSameSafeHtmlGate(): void
    {
        if (!class_exists(\Texy\Texy::class)) { $this->markTestSkipped('Optional Texy bridge dependency is not installed'); }
        $converter = new \Wbengine\Compat\TexyMigration\TexyConverter();
        self::assertInstanceOf(Converter::class, $converter);
        self::assertSame('texy', $converter->format());
        $source = 'Hello **bold**.';
        $result = $converter->convert($source);
        self::assertTrue($result->safe());
        self::assertSame(hash('sha256', $source), $result->sourceHash);
        self::assertSame("<p>Hello <strong>bold</strong>.</p>\n", (new Markdown())->render($result->markdown));
    }
}
