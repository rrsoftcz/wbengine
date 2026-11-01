<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Version;

final class VersionTest extends TestCase
{
    protected function setUp(): void { Version::reset(); }
    protected function tearDown(): void { Version::reset(); }

    #[DataProvider('pretty')]
    public function testNormalizeKeepsAReleaseAndNeverMakesUpANumber(?string $pretty, string $expected): void
    {
        self::assertSame($expected, Version::normalize($pretty));
    }

    public static function pretty(): array
    {
        return [
            'tag' => ['v6.7.0', '6.7.0'],
            'plain' => ['6.7.0', '6.7.0'],
            'pre-release' => ['v6.7.0-rc.1', '6.7.0-rc.1'],
            'branch' => ['dev-master', 'dev'],
            'branch with number' => ['6.7.x-dev', 'dev'],
            'empty' => ['', 'dev'],
            'null' => [null, 'dev'],
            'garbage' => ['6.7', 'dev'],
            'injection' => ["6.7.0\"><script>", 'dev'],
        ];
    }

    public function testCurrentIsAReleaseNumberOrDevAndStaysTheSame(): void
    {
        self::assertMatchesRegularExpression('/^(\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?|dev)$/D', Version::current());
        self::assertSame(Version::current(), Version::current());
    }
}
