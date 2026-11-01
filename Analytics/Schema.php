<?php
namespace Wbengine\Analytics;

use Wbengine\Db;

/**
 * What the analytics tables of this database can do. A site that upgraded the framework but has not applied
 * migrations/6.7.0/013 has no locale column: counting must go on exactly as before, never stop.
 */
final class Schema
{
    private static ?bool $locale = null;

    /** True when both tables have the locale column. Probed once per process. */
    public static function hasLocale(): bool
    {
        if (null !== self::$locale) { return self::$locale; }
        try {
            // SHOW COLUMNS does not raise (and log) an SQL error on a database without the migration,
            // and it sees temporary tables, which information_schema does not.
            foreach (['cms_analytics_daily', 'cms_analytics_totals'] as $table) {
                if (!is_array(Db::fetchAssoc('SHOW COLUMNS FROM ' . $table . " LIKE 'locale'"))) { return self::$locale = false; }
            }
            return self::$locale = true;
        } catch (\Throwable) {
            return self::$locale = false;
        }
    }

    /** For tests and long running processes: probe again. */
    public static function reset(): void
    {
        self::$locale = null;
    }

    /** A locale as stored: "cs", "en", "pt_BR". Anything else is not recorded. */
    public static function cleanLocale(string $locale): string
    {
        return preg_match('/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,5})?$/D', $locale) ? $locale : '';
    }
}
