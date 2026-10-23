<?php

namespace Wbengine\I18n;

/**
 * Numbers and dates in the conventions of a language. The tables are built in, so this
 * does not need the intl extension and gives the same result on every server.
 */
class Format
{
    /** language => array(decimal separator, thousands separator) */
    private const NUMBERS = array(
        'en' => array('.', ','),
        'cs' => array(',', "\u{00A0}"),
        'sk' => array(',', "\u{00A0}"),
        'pl' => array(',', "\u{00A0}"),
        'ru' => array(',', "\u{00A0}"),
        'uk' => array(',', "\u{00A0}"),
        'de' => array(',', '.'),
        'nl' => array(',', '.'),
        'it' => array(',', '.'),
        'es' => array(',', '.'),
        'pt' => array(',', '.'),
        'fr' => array(',', "\u{202F}"),
    );

    /** language => style => pattern (d j n m Y y H i s are numbers, F and M are month names) */
    private const DATES = array(
        'en' => array('short' => 'n/j/Y', 'medium' => 'M j, Y', 'long' => 'F j, Y'),
        'cs' => array('short' => 'j. n. Y', 'medium' => 'j. n. Y', 'long' => 'j. F Y'),
        'sk' => array('short' => 'j. n. Y', 'medium' => 'j. n. Y', 'long' => 'j. F Y'),
        'pl' => array('short' => 'd.m.Y', 'medium' => 'j M Y', 'long' => 'j F Y'),
        'de' => array('short' => 'd.m.Y', 'medium' => 'd.m.Y', 'long' => 'j. F Y'),
        'fr' => array('short' => 'd/m/Y', 'medium' => 'j M Y', 'long' => 'j F Y'),
    );

    /**
     * @param int|float|string $value
     * @param int $decimals
     * @param string $locale
     * @return string
     */
    public static function number($value, $decimals = 0, $locale = 'en')
    {
        if (!is_numeric($value)) {
            return '';
        }
        list($decimal, $thousands) = self::NUMBERS[self::language($locale)] ?? self::NUMBERS['en'];

        return number_format($value + 0, max(0, min(10, (int) $decimals)), $decimal, $thousands);
    }

    /**
     * @param int|string $value a timestamp or a date text
     * @param string $style short, medium or long
     * @param string $locale
     * @param Translator|null $translator supplies the month names ("wb.month.1", "wb.month_short.1")
     * @return string
     */
    public static function date($value, $style = 'medium', $locale = 'en', ?Translator $translator = null)
    {
        if (!is_scalar($value) || '' === (string) $value) {
            return '';
        }
        $time = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (false === $time) {
            return '';
        }

        $patterns = self::DATES[self::language($locale)] ?? self::DATES['en'];
        $pattern = $patterns[$style] ?? $patterns['medium'];

        $out = '';
        foreach (str_split($pattern) as $char) {
            if ('F' === $char || 'M' === $char) {
                $key = ('F' === $char ? 'wb.month.' : 'wb.month_short.') . (int) date('n', $time);
                $name = $translator ? $translator->translate($key, array(), null, $locale) : $key;
                // A missing translation must never put a message key into a date.
                $out .= ($name === $key) ? date($char, $time) : $name;
            } elseif (false !== strpos('djnmYyHis', $char)) {
                $out .= date($char, $time);
            } else {
                $out .= $char;
            }
        }
        return $out;
    }

    private static function language($locale)
    {
        return strtolower(substr(preg_split('/[_-]/', (string) $locale)[0], 0, 3));
    }
}
