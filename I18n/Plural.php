<?php

namespace Wbengine\I18n;

/**
 * Plural categories (CLDR): zero, one, two, few, many or other.
 *
 * Czech, for example, needs "1 soubor", "2 soubory", "5 souborů", which is the
 * categories one, few and other. A language that is not listed uses the English rule.
 */
class Plural
{
    /**
     * @param string $locale a language ("cs") or a locale ("cs_CZ", "cs-CZ")
     * @param int|float|string $n
     * @return string
     */
    public static function category($locale, $n)
    {
        $language = strtolower(substr(preg_split('/[_-]/', (string) $locale)[0], 0, 3));
        $n = is_numeric($n) ? abs($n + 0) : 0;
        $isInteger = (floor($n) == $n);
        $i = (int) floor($n);

        switch ($language) {
            case 'cs':
            case 'sk':
                if (!$isInteger) {
                    return 'many';
                }
                if (1 === $i) {
                    return 'one';
                }
                return ($i >= 2 && $i <= 4) ? 'few' : 'other';

            case 'pl':
                if (!$isInteger) {
                    return 'other';
                }
                if (1 === $i) {
                    return 'one';
                }
                $mod10 = $i % 10;
                $mod100 = $i % 100;
                if ($mod10 >= 2 && $mod10 <= 4 && !($mod100 >= 12 && $mod100 <= 14)) {
                    return 'few';
                }
                return 'many';

            case 'ru':
            case 'uk':
                if (!$isInteger) {
                    return 'other';
                }
                $mod10 = $i % 10;
                $mod100 = $i % 100;
                if (1 === $mod10 && 11 !== $mod100) {
                    return 'one';
                }
                if ($mod10 >= 2 && $mod10 <= 4 && !($mod100 >= 12 && $mod100 <= 14)) {
                    return 'few';
                }
                return 'many';

            case 'fr':
            case 'pt':
                return ($n >= 0 && $n < 2) ? 'one' : 'other';

            case 'ja':
            case 'zh':
            case 'ko':
            case 'vi':
            case 'th':
            case 'id':
                return 'other';

            default:
                return (1 == $n && $isInteger) ? 'one' : 'other';
        }
    }
}
