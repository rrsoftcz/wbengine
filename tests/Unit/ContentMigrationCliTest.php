<?php
namespace Wbengine\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Wbengine\Content\Migration\Cli;
use Wbengine\Content\Migration\Converters;
class ContentMigrationCliTest extends TestCase
{
    public function testExplicitSourceFormatIsRequired(): void
    {
        self::assertTrue(class_exists(Cli::class));
        $this->expectException(\InvalidArgumentException::class);
        (new Cli())->options(['--article=1', '--config=/site/Settings.json']);
    }
    public function testDefaultModeIsDryRun(): void
    {
        self::assertTrue(class_exists(Cli::class));
        $options = (new Cli())->options(['--article=1', '--source-format=html', '--config=/site/Settings.json']);
        self::assertSame('dry-run', $options['mode']);
    }
    public function testApplyRequiresReviewedApprovalHash(): void
    {
        self::assertTrue(class_exists(Cli::class));
        $this->expectException(\InvalidArgumentException::class);
        (new Cli())->options(['--apply=report.json', '--config=/site/Settings.json']);
    }
    public function testUnknownAndConflictingModesAreRefused(): void
    {
        self::assertTrue(class_exists(Cli::class));
        $this->expectException(\InvalidArgumentException::class);
        (new Cli())->options(['--apply=report.json', '--rollback=' . str_repeat('a', 64), '--approve=' . str_repeat('a', 64), '--config=/site/Settings.json']);
    }
    public function testTexyRequiresTheExplicitOptionalBridge(): void
    {
        self::assertTrue(class_exists(Converters::class));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('compat/texy-migration');
        Converters::for('texy');
    }
}
