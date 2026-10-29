<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Config;

final class AuditSettingsTest extends TestCase
{
    private mixed $previous;

    protected function setUp(): void { $this->previous = Config::$config; }
    protected function tearDown(): void { Config::$config = $this->previous; }

    public function testDefaultsAreOneYear(): void
    {
        Config::$config = (object) [];
        self::assertSame(['retentionDays' => 365, 'revisionRetentionDays' => 365], Config::getAuditSettings());
    }

    public function testBoundsAreInclusiveAndStringsFromJsonOrEnvAreAccepted(): void
    {
        Config::$config = (object) ['audit' => (object) ['retentionDays' => 30, 'revisionRetentionDays' => 3650]];
        self::assertSame(['retentionDays' => 30, 'revisionRetentionDays' => 3650], Config::getAuditSettings());
        Config::$config = (object) ['audit' => ['retentionDays' => '90']];
        self::assertSame(['retentionDays' => 90, 'revisionRetentionDays' => 365], Config::getAuditSettings());
    }

    #[DataProvider('invalid')]
    public function testInvalidValuesAreRefusedWithTheKeyName(string $key, mixed $value): void
    {
        Config::$config = (object) ['audit' => (object) [$key => $value]];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('audit.' . $key);
        Config::getAuditSettings();
    }

    public static function invalid(): array
    {
        return [['retentionDays', 29], ['retentionDays', 3651], ['retentionDays', 0], ['retentionDays', -1], ['retentionDays', 'abc'], ['retentionDays', 90.5], ['revisionRetentionDays', 10], ['revisionRetentionDays', 4000], ['revisionRetentionDays', true]];
    }
}
