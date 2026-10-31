<?php

namespace Wbengine\Content;

use Wbengine\Config;
use Wbengine\Db;

/**
 * Whether articles can be hidden (migration 6.2.0/012 applied). A hidden article is not shown and the
 * page it belongs to is not found. A site that does not use API v2 has no way to hide an article, so
 * nothing is probed there and no query is added.
 */
final class ArticleVisibility
{
    private static ?bool $supported = null;

    public static function isSupported(): bool
    {
        if (null !== self::$supported) { return self::$supported; }
        $api = is_object(Config::$config ?? null) ? (Config::$config->apiV2 ?? null) : null;
        if (!is_object($api) || true !== ($api->enabled ?? false)) { return self::$supported = false; }
        try {
            Db::fetchAssoc('SELECT hidden FROM `' . S_TABLE_ARTICLES . '` LIMIT 1');
            return self::$supported = true;
        } catch (\Throwable $e) {
            return self::$supported = false;
        }
    }

    /** SQL condition: the page `$siteColumn` has no hidden article. Empty when hiding is not available. */
    public static function pageCondition(string $siteColumn): string
    {
        return self::isSupported()
            ? ' AND NOT EXISTS (SELECT 1 FROM `' . S_TABLE_ARTICLES . '` hid WHERE hid.site_id = ' . $siteColumn . ' AND hid.hidden = 1)'
            : '';
    }

    /** For tests and long running processes: probe again. */
    public static function reset(): void
    {
        self::$supported = null;
    }
}
