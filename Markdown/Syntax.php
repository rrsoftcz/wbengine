<?php
namespace Wbengine\Markdown;

/** Shared lexical HTML rules. Recognition does not grant permission to emit HTML. */
final class Syntax
{
    private const TAG = '(?:<[A-Za-z][A-Za-z0-9-]*(?:[ \\t\\n\\r\\f]+[A-Za-z_:][A-Za-z0-9_.:-]*(?:[ \\t\\n\\r\\f]*=[ \\t\\n\\r\\f]*(?:[^ \\t\\n\\r\\f"\x27=<>`]+|\x27[^\x27]*\x27|"[^"]*"))?)*[ \\t\\n\\r\\f]*/?>|</[A-Za-z][A-Za-z0-9-]*[ \\t\\n\\r\\f]*>)';

    public static function escapable(string $char): bool
    {
        return preg_match('/^[!"#$%&\x27()*+,\-.\/:;<=>?@\[\\\\\]^_`{|}~]$/', $char) === 1;
    }

    public static function utf8(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) { return $text; }
        // Preserve valid sequences and replace invalid bytes without changing global mb settings.
        $valid = '[\x00-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xec\xee-\xef][\x80-\xbf]{2}|\xed[\x80-\x9f][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf]{2}|[\xf1-\xf3][\x80-\xbf]{3}|\xf4[\x80-\x8f][\x80-\xbf]{2}';
        return preg_replace('~(?:' . $valid . ')(*SKIP)(*F)|[\x80-\xff]~', "\u{fffd}", $text);
    }

    public static function html(string $source, int $offset = 0): ?string
    {
        $pattern = '~\\G(?:<!--(?:>|->|[\\s\\S]*?-->)|<\\?[\\s\\S]*?\\?>|<![A-Z][^>]*>|<!\\[CDATA\\[[\\s\\S]*?\\]\\]>|' . self::TAG . ')~';
        return preg_match($pattern, $source, $m, 0, $offset) ? $m[0] : null;
    }

    public static function completeTag(string $line): bool
    {
        return preg_match('~^ {0,3}' . self::TAG . '[ \\t]*$~', $line) === 1;
    }
}
