<?php

namespace Wbengine\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\I18n\Format;
use Wbengine\I18n\Translator;

class FormatTest extends TestCase
{
    #[DataProvider('numbers')]
    public function testNumber($value, int $decimals, string $locale, string $expected): void
    {
        $this->assertSame($expected, Format::number($value, $decimals, $locale));
    }

    public static function numbers(): array
    {
        return [
            'en'                 => [1234567.891, 2, 'en', '1,234,567.89'],
            'cs'                 => [1234567.891, 2, 'cs', "1\u{00A0}234\u{00A0}567,89"],
            'de'                 => [1234567.891, 2, 'de', '1.234.567,89'],
            'fr'                 => [1234.5, 1, 'fr', "1\u{202F}234,5"],
            'region'             => [1234.5, 1, 'cs_CZ', "1\u{00A0}234,5"],
            'no decimals'        => [1234.5, 0, 'en', '1,235'],
            'negative'           => [-1234.5, 1, 'de', '-1.234,5'],
            'string number'      => ['1234', 0, 'en', '1,234'],
            'unknown language'   => [1234.5, 1, 'xx', '1,234.5'],
            'not a number'       => ['abc', 2, 'en', ''],
            'null'               => [null, 2, 'en', ''],
            'array'              => [[1], 2, 'en', ''],
            'huge decimals capped' => [1.5, 99, 'en', '1.5000000000'],
            'negative decimals'  => [1.5, -3, 'en', '2'],
        ];
    }

    public function testDates(): void
    {
        $ts = gmmktime(12, 0, 0, 10, 5, 2026);
        $date = static fn ($style, $locale, $translator = null) => Format::date($ts, $style, $locale, $translator);

        $this->assertSame('10/5/2026', $date('short', 'en'));
        $this->assertSame('5. 10. 2026', $date('short', 'cs'));
        $this->assertSame('05.10.2026', $date('short', 'de'));
        $this->assertSame('5. 10. 2026', $date('medium', 'cs_CZ'));
        $this->assertSame('10/5/2026', $date('short', 'xx'), 'an unknown language uses English patterns');
        $this->assertSame('Oct 5, 2026', $date('medium', 'en'), 'without a translator the English month comes from PHP');
    }

    public function testMonthNamesComeFromTheCatalog(): void
    {
        $translator = new Translator([dirname(__DIR__, 3) . '/I18n/messages'], 'en');
        $ts = gmmktime(12, 0, 0, 10, 5, 2026);

        $this->assertSame('October 5, 2026', Format::date($ts, 'long', 'en', $translator));
        $this->assertSame('5. října 2026', Format::date($ts, 'long', 'cs', $translator));
        $this->assertSame('5. 10. 2026', Format::date($ts, 'medium', 'cs', $translator));
        $this->assertSame('5. října 2026', Format::date('2026-10-05', 'long', 'cs', $translator), 'date text works too');
    }

    public function testAMissingMonthNameFallsBackToEnglishNotToAKey(): void
    {
        $empty = new Translator([], 'en');
        $ts = gmmktime(12, 0, 0, 10, 5, 2026);

        $this->assertSame('5. October 2026', Format::date($ts, 'long', 'cs', $empty));
    }

    public function testBadDatesGiveNothing(): void
    {
        $this->assertSame('', Format::date('not a date', 'long', 'en'));
        $this->assertSame('', Format::date(null, 'long', 'en'));
        $this->assertSame('', Format::date([], 'long', 'en'));
    }

    public function testUnknownStyleFallsBackToMedium(): void
    {
        $ts = gmmktime(12, 0, 0, 10, 5, 2026);

        $this->assertSame(Format::date($ts, 'medium', 'cs'), Format::date($ts, 'weird', 'cs'));
    }
}
