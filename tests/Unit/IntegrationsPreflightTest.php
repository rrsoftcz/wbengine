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
            foreach (\Wbengine\Api\V2\Model::SCOPES as $scope) {
                $this->assertArrayHasKey('wb.integrations.scope.' . str_replace(':', '_', $scope), $messages);
            }
        }
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
