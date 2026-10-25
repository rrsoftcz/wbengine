<?php

namespace Wbengine\Template;

use Wbengine\Cache;
use Wbengine\I18n;

/**
 * Helpers called by compiled templates. They are total: whatever a template is given
 * (null, an array where text is expected, a missing property) they return something
 * harmless instead of raising a warning.
 */
class Runtime
{
    /**
     * HTML-escape a value for output.
     */
    public static function e($value)
    {
        return htmlspecialchars(self::s($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A value as text, without escaping.
     */
    public static function s($value)
    {
        if (is_string($value)) {
            return $value;
        }
        if (null === $value || false === $value) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        return '';
    }

    public static function n($value)
    {
        return is_numeric($value) ? $value + 0 : 0;
    }

    public static function arith($operator, $a, $b)
    {
        $a = self::n($a);
        $b = self::n($b);
        if (0 == $b) {
            return 0;
        }
        return '/' === $operator ? $a / $b : $a % $b;
    }

    public static function iter($value)
    {
        return (is_array($value) || $value instanceof \Traversable) ? $value : array();
    }

    // ------------------------------------------------------------- access

    public static function prop($object, $name)
    {
        if (is_object($object)) {
            if (isset($object->$name)) {
                return $object->$name;
            }
            if (method_exists($object, '__get')) {
                try {
                    return $object->$name;
                } catch (\Throwable $e) {
                    return null;
                }
            }
            return null;
        }
        return null;
    }

    public static function item($container, $key)
    {
        if (is_array($container) || $container instanceof \ArrayAccess) {
            return (is_scalar($key) && isset($container[$key])) ? $container[$key] : null;
        }
        return null;
    }

    public static function call($object, $method, array $args)
    {
        if (is_object($object) && is_callable(array($object, $method))) {
            return $object->$method(...$args);
        }
        return null;
    }

    // ---------------------------------------------------------- modifiers

    public static function upper($value)
    {
        return mb_strtoupper(self::s($value));
    }

    public static function lower($value)
    {
        return mb_strtolower(self::s($value));
    }

    public static function trim($value)
    {
        return trim(self::s($value));
    }

    public static function dflt($value, $default = '')
    {
        return empty($value) ? $default : $value;
    }

    public static function truncate($value, $length = 80, $suffix = '...')
    {
        $text = self::s($value);
        $length = max(0, (int) $length);
        return (mb_strlen($text) <= $length) ? $text : rtrim(mb_substr($text, 0, $length)) . self::s($suffix);
    }

    /**
     * Escapes, then turns line breaks into <br>. The result is HTML.
     */
    public static function nl2br($value)
    {
        return nl2br(self::e($value), false);
    }

    /**
     * JSON that is safe inside an HTML page and inside a script element.
     */
    public static function json($value)
    {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return false === $json ? 'null' : $json;
    }

    public static function url($value)
    {
        return rawurlencode(self::s($value));
    }

    public static function length($value)
    {
        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }
        return mb_strlen(self::s($value));
    }

    public static function date($value, $format = 'Y-m-d')
    {
        $time = is_numeric($value) ? (int) $value : strtotime(self::s($value));
        return false === $time ? '' : date(self::s($format), $time);
    }

    public static function join($value, $separator = ', ')
    {
        if (!is_array($value)) {
            return '';
        }
        return implode(self::s($separator), array_map(array(self::class, 's'), $value));
    }

    // ------------------------------------------------------ internationalisation

    /**
     * The {t 'key' ...} tag. With $raw the message is trusted HTML (a link in a sentence),
     * so the parameters are escaped one by one before they are put into it.
     */
    public static function t($key, array $params = array(), $count = null, $raw = false)
    {
        if ($raw) {
            $params = array_map(array(self::class, 'e'), $params);
        }
        return I18n::t(self::s($key), $params, null === $count ? null : (is_numeric($count) ? $count + 0 : 0));
    }

    /** The |t modifier: the value is the key. */
    public static function tr($key, $count = null)
    {
        return self::t($key, array(), $count);
    }

    /** The |lurl modifier: a site URL in the current locale ("/blog/" becomes "/cs/blog/"). */
    public static function lurl($url, $locale = null)
    {
        return I18n::url(self::s($url), null === $locale ? null : self::s($locale));
    }

    public static function number($value, $decimals = 0)
    {
        return I18n::number($value, self::n($decimals));
    }

    public static function ldate($value, $style = 'medium')
    {
        return I18n::date($value, self::s($style));
    }

    // ------------------------------------------------------- fragment cache

    /**
     * Render a fragment once and keep the HTML in the cache (see Wbengine\Cache).
     * Without an enabled cache the fragment is rendered every time.
     */
    public static function cache($key, $ttl, callable $render)
    {
        $locale = I18n::isEnabled() ? I18n::locale() : '';
        return (string) Cache::remember('tpl:v2:' . $locale . ':' . self::s($key), $render, $ttl);
    }
}
