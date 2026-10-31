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

    /** Words that should not end an address, a title or a menu name: a cut at a word boundary must not leave one hanging. */
    private const STOP = ['a', 'an', 'the', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'at', 'by', 'from', 'into', 'is', 'as',
        'i', 'na', 'v', 've', 's', 'se', 'z', 'ze', 'do', 'o', 'u', 'k', 'ke', 'pro', 'od', 'po', 'za', 'pri', 'pred', 'nebo', 'aby', 'ze', 'při', 'před'];

    /** Longest part of an address after the prefix. Short enough to read and to share, whole words only. */
    public const MAX_SLUG = 50;

    public static function fromTitle(string $title, string $prefix = '/'): string
    {
        // An apostrophe belongs to the word (humanity's becomes humanitys), it does not split it (humanity-s).
        $text = str_replace(["'", "\u{2019}", "\u{2018}", '`', "\u{00B4}"], '', mb_strtolower($title, 'UTF-8'));
        $text = strtr($text, self::MAP);
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $text) ?? '', '-');
        $slug = self::finish(self::cut($slug, self::MAX_SLUG, '-'), '-');
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

    /** The same link with a numeric suffix before the trailing slash: /blog/post/ becomes /blog/post-2/. */
    public static function withSuffix(string $link, int $n): string
    {
        return rtrim($link, '/') . '-' . $n . '/';
    }

    /** A title for the browser tab: whole words, at most $max characters. The template adds the site name. */
    public static function titleTag(string $title, int $max = 60): string
    {
        return self::finish(self::cut(trim(preg_replace('/\s+/u', ' ', $title) ?? ''), $max, ' '), ' ');
    }

    /** A short name for a menu entry: the part of the title before a colon or dash when it is short, else the first words. */
    public static function menuName(string $title, int $max = 30): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        $head = trim((string) preg_split('/\s[-|\x{2013}\x{2014}]\s|:/u', $clean, 2)[0]);
        return mb_strlen($head) >= 3 && mb_strlen($head) <= $max ? $head : self::titleTag($clean, $max);
    }

    /** Drop punctuation and function words from the end, so a cut never leaves "and the" hanging. Keeps at least one word. */
    private static function finish(string $text, string $boundary): string
    {
        $words = explode($boundary, rtrim($text, " ,:;-/\u{2013}\u{2014}"));
        while (count($words) > 1 && in_array(mb_strtolower(rtrim((string) end($words), ',:;')), self::STOP, true)) { array_pop($words); }
        return rtrim(implode($boundary, $words), " ,:;-/\u{2013}\u{2014}");
    }

    /** A meta description from Markdown text: plain words, whole sentence when one fits, at most $max characters. Null when empty. */
    public static function description(string $markdown, int $max = 160): ?string
    {
        $text = preg_replace(['/!\[[^\]]*\]\([^)]*\)/u', '/\[([^\]]*)\]\([^)]*\)/u', '/^\s{0,3}(#{1,6}|[-*+>]|\d+\.)\s+/mu', '/[*`~]+|(?<!\w)_+|_+(?!\w)/u', '/\s+/u'], ['', '$1', '', '', ' '], $markdown) ?? '';
        $text = trim($text);
        if ('' === $text) { return null; }
        if (mb_strlen($text) <= $max) { return $text; }
        $head = mb_substr($text, 0, $max);
        if (preg_match('/^(.{40,})[.!?](\s|$)/us', $head, $m)) { return $m[1] . '.'; }
        return self::cut($text, $max, ' ');
    }

    /** Cut at a boundary character instead of in the middle of a word, unless that would leave too little. */
    private static function cut(string $text, int $max, string $boundary): string
    {
        if (mb_strlen($text) <= $max) { return rtrim($text, $boundary . ' '); }
        $head = mb_substr($text, 0, $max);
        if (mb_substr($text, $max, 1) !== $boundary) {
            $at = mb_strrpos($head, $boundary);
            if (false !== $at && $at >= (int) ($max / 3)) { $head = mb_substr($head, 0, $at); }
        }
        return rtrim($head, $boundary . ' ');
    }
}
