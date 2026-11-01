<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** CHANGELOG.md is read by other software (the changelog page of the skeleton), so its shape is a contract. */
final class ChangelogTest extends TestCase
{
    private const GROUPS = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security', 'Documentation', 'Upgrade notes'];

    /** @return list<string> */
    private function lines(): array
    {
        return explode("\n", (string) file_get_contents(dirname(__DIR__, 2) . '/CHANGELOG.md'));
    }

    public function testEveryReleaseIsASectionWithAVersionAndADateNewestFirst(): void
    {
        $headings = array_values(array_filter($this->lines(), static fn(string $l): bool => str_starts_with($l, '## ')));
        self::assertSame('## [Unreleased]', $headings[0], 'Unreleased comes first');
        $previous = null;
        $previousDate = null;
        foreach (array_slice($headings, 1) as $heading) {
            self::assertMatchesRegularExpression('/^## \[(\d+\.\d+\.\d+)\] - (\d{4}-\d{2}-\d{2})$/D', $heading, 'a release heading has a version and a date: ' . $heading);
            preg_match('/\[(\d+\.\d+\.\d+)\] - (\S+)/', $heading, $m);
            if (null !== $previous) {
                self::assertSame(1, version_compare($previous, $m[1]), 'versions are strictly descending: ' . $previous . ' then ' . $m[1]);
                self::assertGreaterThanOrEqual($m[2], $previousDate, 'dates do not increase going down: ' . $heading);
            }
            self::assertTrue(checkdate((int) substr($m[2], 5, 2), (int) substr($m[2], 8, 2), (int) substr($m[2], 0, 4)), 'a real date: ' . $heading);
            [$previous, $previousDate] = [$m[1], $m[2]];
        }
    }

    public function testChangesSinceTheStructureWasIntroducedUseTheStandardGroups(): void
    {
        $inScope = false;
        $found = 0;
        foreach ($this->lines() as $line) {
            if (str_starts_with($line, '## ')) {
                $inScope = '## [Unreleased]' === $line || (preg_match('/^## \[(\d+\.\d+\.\d+)\]/', $line, $m) && version_compare($m[1], '4.1.0', '>='));
                continue;
            }
            if ($inScope && str_starts_with($line, '### ')) {
                $found++;
                $name = preg_replace('/ \(breaking\)$/', '', substr($line, 4));
                self::assertContains($name, self::GROUPS, 'an unknown group: ' . $line);
            }
        }
        self::assertGreaterThan(20, $found);
    }

    public function testEveryReleaseFrom41HasAtLeastOneEntry(): void
    {
        $release = null;
        $count = [];
        foreach ($this->lines() as $line) {
            if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/', $line, $m)) { $release = version_compare($m[1], '4.1.0', '>=') ? $m[1] : null; if (null !== $release) { $count[$release] = 0; } continue; }
            if (str_starts_with($line, '## ')) { $release = null; continue; }
            if (null !== $release && str_starts_with($line, '- ')) { $count[$release]++; }
        }
        self::assertNotEmpty($count);
        foreach ($count as $version => $entries) { self::assertGreaterThan(0, $entries, 'release ' . $version . ' has no entry'); }
    }
}
