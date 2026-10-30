<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\Preflight;
use Wbengine\Console\Doctor;

class IntegrationsPreflightTest extends TestCase
{
    public function testReadyInstallationHasNoProblems(): void
    {
        $this->assertSame([], (new Preflight(fn (array $schema) => true))->problems(true, true, 'https://example.test'));
        $this->assertSame([], (new Preflight(fn (array $schema) => true))->problems(true, true));
    }

    public function testDisabledApiAndPlainHttpAreReportedTogether(): void
    {
        $problems = (new Preflight(fn (array $schema) => true))->problems(false, false);
        $this->assertSame([Preflight::API_DISABLED, Preflight::NOT_HTTPS], $problems);
    }

    public function testConfiguredPlainHttpBaseUrlFailsEvenOnAnHttpsRequest(): void
    {
        $this->assertSame([Preflight::NOT_HTTPS], (new Preflight(fn (array $schema) => true))->problems(true, true, 'http://example.test'));
    }

    public function testMissingTablesAndMissingLastUsedColumnAreDistinguished(): void
    {
        $missingTables = new Preflight(fn (array $schema) => false);
        $this->assertSame([Preflight::SCHEMA_INTEGRATIONS], $missingTables->problems(true, true));
        $missingColumn = new Preflight(fn (array $schema) => $schema !== Doctor::SCHEMA_INTEGRATIONS_LAST_USED);
        $this->assertSame([Preflight::SCHEMA_LAST_USED], $missingColumn->problems(true, true));
    }

    public function testUnavailableDatabaseFailsClosed(): void
    {
        $preflight = new Preflight(function (array $schema): bool { throw new \RuntimeException('connection refused'); });
        $this->assertSame([Preflight::DATABASE], $preflight->problems(true, true));
    }

    public function testEveryProblemHasAnInstructionInBothLanguages(): void
    {
        foreach (['en', 'cs'] as $locale) {
            $messages = require dirname(__DIR__, 2) . '/I18n/messages/' . $locale . '.php';
            foreach ([Preflight::API_DISABLED, Preflight::NOT_HTTPS, Preflight::SCHEMA_INTEGRATIONS, Preflight::SCHEMA_LAST_USED, Preflight::DATABASE] as $code) {
                $this->assertArrayHasKey('wb.integrations.problem.' . $code, $messages);
            }
            foreach (\Wbengine\Box\Integrations::PROFILE_SCOPES as $scope) {
                $this->assertArrayHasKey('wb.integrations.scope.' . str_replace(':', '_', $scope), $messages);
            }
        }
    }

    public function testAnalyticsIsUsableOnlyWhenEnabledAndTheSchemaExists(): void
    {
        $ready = new Preflight(fn (array $schema) => true);
        $this->assertSame([], $ready->analyticsProblems(true));
        $this->assertSame([Preflight::ANALYTICS_DISABLED], $ready->analyticsProblems(false));
        $noSchema = new Preflight(fn (array $schema) => $schema !== Doctor::SCHEMA_ANALYTICS);
        $this->assertSame([Preflight::ANALYTICS_SCHEMA], $noSchema->analyticsProblems(true));
        $this->assertSame([Preflight::ANALYTICS_DISABLED, Preflight::ANALYTICS_SCHEMA], $noSchema->analyticsProblems(false));
        $down = new Preflight(function (array $schema): bool { throw new \RuntimeException('connection refused'); });
        $this->assertSame([Preflight::ANALYTICS_DATABASE], $down->analyticsProblems(true));
    }

    public function testAnalyticsProblemsNeverBlockIssuingTheKey(): void
    {
        $preflight = new Preflight(fn (array $schema) => $schema !== Doctor::SCHEMA_ANALYTICS);
        $this->assertSame([], $preflight->problems(true, true));
    }

    public function testAnalyticsSchemaMatchesTheMigration(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/006-analytics.sql');
        foreach (Doctor::SCHEMA_ANALYTICS as $table => $columns) {
            $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table . ' (', $sql);
            foreach ($columns as $column) {
                $this->assertMatchesRegularExpression('~\b' . $column . '\s+[A-Z]~', $sql);
            }
        }
    }

    public function testAnalyticsTextsAndScopeDescriptionAreInBothLanguages(): void
    {
        foreach (['en', 'cs'] as $locale) {
            $messages = require dirname(__DIR__, 2) . '/I18n/messages/' . $locale . '.php';
            $this->assertArrayHasKey('wb.integrations.scope.analytics_read', $messages);
            foreach ([Preflight::ANALYTICS_DISABLED, Preflight::ANALYTICS_SCHEMA, Preflight::ANALYTICS_DATABASE] as $code) {
                $this->assertArrayHasKey('wb.integrations.analytics_problem.' . $code, $messages);
            }
            foreach (\Wbengine\Box\Integrations::scopesFor(true) as $scope) {
                $this->assertArrayHasKey('wb.integrations.scope.' . str_replace(':', '_', $scope), $messages);
            }
        }
        $cs = require dirname(__DIR__, 2) . '/I18n/messages/cs.php';
        $this->assertSame('Číst přehled návštěvnosti webu', $cs['wb.integrations.scope.analytics_read']);
        $this->assertCount(19, \Wbengine\Box\Integrations::scopesFor(true));
        $this->assertCount(18, \Wbengine\Box\Integrations::scopesFor(false));
    }

    public function testCatalogsHaveTheSameIntegrationKeys(): void
    {
        $en = array_keys(array_filter(require dirname(__DIR__, 2) . '/I18n/messages/en.php', fn ($k) => str_starts_with($k, 'wb.integrations.'), ARRAY_FILTER_USE_KEY));
        $cs = array_keys(array_filter(require dirname(__DIR__, 2) . '/I18n/messages/cs.php', fn ($k) => str_starts_with($k, 'wb.integrations.'), ARRAY_FILTER_USE_KEY));
        $this->assertSame($en, $cs);
    }

    public function testMessagesContainNoEmDashOrPictographs(): void
    {
        foreach (['en', 'cs'] as $locale) {
            $source = (string) file_get_contents(dirname(__DIR__, 2) . '/I18n/messages/' . $locale . '.php');
            $this->assertStringNotContainsString("\u{2014}", $source);
        }
    }
}
