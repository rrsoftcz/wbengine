<?php
namespace Wbengine\Console;

/** Composer CLI bootstrap, resolved only from the proxy or this package's location. */
final class Bootstrap
{
    public static function load(?string $composerAutoloadPath = null): void
    {
        $package = dirname(__DIR__);
        $paths = $composerAutoloadPath !== null
            ? [$composerAutoloadPath]
            : [$package . '/vendor/autoload.php', dirname($package, 2) . '/autoload.php'];
        foreach ($paths as $path) {
            // Never use the caller's working directory or PHP include_path to find executable code.
            if (!preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $path) || !is_file($path) || !is_readable($path)) { continue; }
            require_once $path;
            return;
        }
        fwrite(STDERR, "Composer autoloader unavailable. Install dependencies or invoke the Composer bin proxy.\n");
        exit(2);
    }
}
