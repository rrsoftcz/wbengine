<?php

namespace Wbengine\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\I18n\Plural;

class PluralTest extends TestCase
{
    #[DataProvider('cases')]
    public function testCategory(string $locale, $n, string $expected): void
    {
        $this->assertSame($expected, Plural::category($locale, $n), "$locale / " . var_export($n, true));
    }

    public static function cases(): array
    {
        $rows = [];
        // Czech and Slovak: one, few (2-4), many for fractions, other
        foreach (['cs', 'sk'] as $l) {
            foreach ([[0, 'other'], [1, 'one'], [2, 'few'], [3, 'few'], [4, 'few'], [5, 'other'], [11, 'other'], [22, 'other'], [100, 'other'], [101, 'other'], [1.5, 'many'], [0.5, 'many'], ['3', 'few'], [1.0, 'one']] as [$n, $c]) {
                $rows["$l $n"] = [$l, $n, $c];
            }
        }
        // Polish
        foreach ([[0, 'many'], [1, 'one'], [2, 'few'], [4, 'few'], [5, 'many'], [12, 'many'], [14, 'many'], [21, 'many'], [22, 'few'], [24, 'few'], [25, 'many'], [112, 'many'], [122, 'few'], [1.5, 'other']] as [$n, $c]) {
            $rows["pl $n"] = ['pl', $n, $c];
        }
        // Russian and Ukrainian
        foreach ([[0, 'many'], [1, 'one'], [2, 'few'], [5, 'many'], [11, 'many'], [12, 'many'], [21, 'one'], [22, 'few'], [101, 'one'], [111, 'many'], [1.5, 'other']] as [$n, $c]) {
            $rows["ru $n"] = ['ru', $n, $c];
            $rows["uk $n"] = ['uk', $n, $c];
        }
        // English, German and the default: one only for exactly 1
        foreach (['en', 'de', 'nl', 'xx'] as $l) {
            foreach ([[0, 'other'], [1, 'one'], [2, 'other'], [1.5, 'other'], [1.0, 'one'], [21, 'other']] as [$n, $c]) {
                $rows["$l $n"] = [$l, $n, $c];
            }
        }
        // French and Portuguese: 0 and 1 (and fractions below 2) are "one"
        foreach (['fr', 'pt'] as $l) {
            foreach ([[0, 'one'], [1, 'one'], [1.9, 'one'], [2, 'other']] as [$n, $c]) {
                $rows["$l $n"] = [$l, $n, $c];
            }
        }
        // Languages without plurals
        foreach (['ja', 'zh', 'ko'] as $l) {
            foreach ([0, 1, 2, 100] as $n) {
                $rows["$l $n"] = [$l, $n, 'other'];
            }
        }
        return $rows;
    }

    public function testLocaleFormsAndNegatives(): void
    {
        $this->assertSame('few', Plural::category('cs_CZ', 2));
        $this->assertSame('few', Plural::category('cs-CZ', 2));
        $this->assertSame('few', Plural::category('CS', 2));
        $this->assertSame('one', Plural::category('cs', -1), 'the sign does not matter');
        $this->assertSame('other', Plural::category('cs', 'abc'), 'not a number counts as 0');
        $this->assertSame('other', Plural::category('', null));
    }
}
