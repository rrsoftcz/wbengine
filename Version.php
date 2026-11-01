<?php

namespace Wbengine;

/**
 * The version of the framework that is running, for a footer or a header: "6.7.0", or "dev" when the
 * framework is not an installed release (a checkout, a branch). A number is never made up.
 */
final class Version
{
    private static ?string $current = null;

    public static function current(): string
    {
        return self::$current ??= self::detect();
    }

    /** The version Composer recorded for the package, as a plain release number or "dev". */
    private static function detect(): string
    {
        if (!class_exists(\Composer\InstalledVersions::class)) { return 'dev'; }
        try {
            return self::normalize(\Composer\InstalledVersions::getPrettyVersion('wbengine/wbengine'));
        } catch (\Throwable) {
            return 'dev';
        }
    }

    /** "v6.7.0" and "6.7.0" give "6.7.0", a pre-release suffix is kept, a branch or an empty value gives "dev". */
    public static function normalize(?string $pretty): string
    {
        $pretty = trim((string) $pretty);
        return preg_match('/^v?(\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?)$/D', $pretty, $m) ? $m[1] : 'dev';
    }

    /** For tests and long running processes. */
    public static function reset(): void
    {
        self::$current = null;
    }
}
