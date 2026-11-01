<?php

namespace Wbengine\Seo;

use Wbengine\Application\Application;
use Wbengine\Config;
use Wbengine\I18n;

/**
 * Opt-in routes for crawlers: /robots.txt and /sitemap.xml, generated for every request from the
 * pages and the "seo" settings. An application turns them on with Crawlers::register($app) before run().
 * The routes finish before the page cache and the analytics tracker, so a request for them is neither
 * cached nor counted as a page view.
 */
final class Crawlers
{
    public static function register(Application $app): void
    {
        $app->get('/robots.txt', static function (): bool {
            self::send('text/plain; charset=UTF-8', Robots::text(Config::getSeoSettings(), I18n::origin()));
            return true;
        });
        $app->get('/sitemap.xml', static function (): bool {
            $settings = Config::getSeoSettings();
            $origin = I18n::origin();
            if ('' === $origin || !$settings['allowIndexing']) {
                self::send('text/plain; charset=UTF-8', "Not found\n", 404);
                return true;
            }
            // The default language first: the sitemap marks it x-default and always lists it.
            $default = I18n::translator()->getDefaultLocale();
            $locales = array_values(array_unique(array_merge([$default], I18n::locales())));
            $xml = Sitemap::xml(Sitemap::pages($settings['sitemapExclude'], $locales), $origin, static fn(string $link, string $locale): string => I18n::url($link, $locale));
            self::send('application/xml; charset=UTF-8', $xml);
            return true;
        });
    }

    private static function send(string $type, string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: ' . $type);
        header(200 === $status ? 'Cache-Control: public, max-age=300' : 'Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo $body;
    }
}
