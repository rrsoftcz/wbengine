<?php

namespace Wbengine\Mcp\Tools;

/** Page links derived from a title: ASCII, lowercase, hyphens, under a prefix. */
final class Slug
{
    private const MAP = [
        'á' => 'a', 'ä' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ą' => 'a', 'č' => 'c', 'ć' => 'c', 'ç' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'ë' => 'e', 'è' => 'e', 'ê' => 'e', 'ę' => 'e',
        'í' => 'i', 'ï' => 'i', 'î' => 'i', 'ì' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ł' => 'l', 'ň' => 'n', 'ń' => 'n', 'ñ' => 'n', 'ó' => 'o', 'ö' => 'o', 'ô' => 'o', 'ò' => 'o', 'õ' => 'o', 'ő' => 'o', 'ø' => 'o',
        'ř' => 'r', 'ŕ' => 'r', 'š' => 's', 'ś' => 's', 'ß' => 'ss', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ù' => 'u', 'û' => 'u', 'ű' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
        'æ' => 'ae', 'œ' => 'oe', 'đ' => 'd', 'þ' => 'th',
    ];

    public static function fromTitle(string $title, string $prefix = '/'): string
    {
        $text = strtr(mb_strtolower($title, 'UTF-8'), self::MAP);
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $text) ?? '', '-');
        $slug = rtrim(substr($slug, 0, 60), '-');
        return self::prefix($prefix) . ('' === $slug ? 'article' : $slug) . '/';
    }

    /** A prefix as /a/b/: leading and trailing slash, only safe characters. Null when it is not usable. */
    public static function prefix(string $prefix): string
    {
        $clean = trim($prefix, '/');
        return '' === $clean ? '/' : '/' . $clean . '/';
    }

    /** Whether a link is a safe local path the API accepts and that stays inside 100 characters. */
    public static function valid(string $link): bool
    {
        return (bool) preg_match('~^/([a-z0-9._-]+/?)*$~D', $link) && strlen($link) <= 100 && !preg_match('~(^|/)\.\.?(/|$)~', $link);
    }
}
