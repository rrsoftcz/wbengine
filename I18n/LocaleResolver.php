<?php

namespace Wbengine\I18n;

/**
 * Chooses the locale of a request from what the client sends. Every method is pure: it
 * takes the request data and the list of supported locales and returns a supported
 * locale or null, so each rule can be tested on its own.
 */
class LocaleResolver
{
    /**
     * "/cs/blog/" -> array('cs', '/blog/'); "/blog/" -> array(null, '/blog/').
     * Only the first path segment is considered and only a supported locale counts, so
     * "/about/" is never mistaken for a language.
     *
     * @param string $path the path of the request, without the query string
     * @param string[] $supported
     * @return array locale or null, and the path without the locale prefix
     */
    public static function fromPath($path, array $supported)
    {
        $path = (string) $path;
        if (!preg_match('~^/([A-Za-z]{2,3}(?:[_-][A-Za-z]{2})?)(/.*)?$~', $path, $m)) {
            return array(null, $path);
        }

        $locale = self::match($m[1], $supported);
        if (null === $locale) {
            return array(null, $path);
        }
        return array($locale, ($m[2] ?? '') === '' ? '/' : $m[2]);
    }

    /**
     * A value from a cookie or a query string.
     */
    public static function fromValue($value, array $supported)
    {
        return is_string($value) ? self::match($value, $supported) : null;
    }

    /**
     * The best supported locale for an Accept-Language header such as
     * "cs-CZ,cs;q=0.9,en;q=0.8". Higher q wins, the order of the header breaks ties, and a
     * language matches a supported locale of the same language ("cs-CZ" matches "cs").
     */
    public static function fromAcceptLanguage($header, array $supported)
    {
        if (!is_string($header) || '' === $header || strlen($header) > 500) {
            return null;
        }

        $candidates = array();
        foreach (explode(',', $header) as $position => $part) {
            $part = trim($part);
            if (!preg_match('/^([A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*|\*)(?:\s*;\s*q\s*=\s*([01](?:\.\d{0,3})?))?$/', $part, $m)) {
                continue;
            }
            $quality = isset($m[2]) ? (float) $m[2] : 1.0;
            if ($quality > 0) {
                $candidates[] = array($m[1], $quality, $position);
            }
        }
        usort($candidates, static function ($a, $b) {
            return ($b[1] <=> $a[1]) ?: ($a[2] <=> $b[2]);
        });

        foreach ($candidates as list($tag)) {
            if ('*' === $tag) {
                continue;
            }
            $locale = self::match($tag, $supported);
            if (null !== $locale) {
                return $locale;
            }
        }
        return null;
    }

    /**
     * The supported locale that a tag stands for: an exact match first ("pt-BR"), then the
     * same language ("cs-CZ" -> "cs", "pt-PT" -> "pt").
     */
    public static function match($tag, array $supported)
    {
        $tag = Translator::normalize($tag);
        if (null === $tag) {
            return null;
        }

        $normalized = array();
        foreach ($supported as $locale) {
            $n = Translator::normalize($locale);
            if (null !== $n) {
                $normalized[$n] = $locale;
            }
        }

        if (isset($normalized[$tag])) {
            return $normalized[$tag];
        }
        $language = explode('_', $tag)[0];
        if (isset($normalized[$language])) {
            return $normalized[$language];
        }
        foreach ($normalized as $n => $original) {
            if (explode('_', $n)[0] === $language) {
                return $original;
            }
        }
        return null;
    }
}
