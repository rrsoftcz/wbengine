<?php

namespace Wbengine\Seo;

/** The text of robots.txt for the "seo" settings of the site. */
final class Robots
{
    /** @param array{allowIndexing: bool, disallow: string[]} $settings */
    public static function text(array $settings, string $origin): string
    {
        if (!$settings['allowIndexing']) {
            return "User-agent: *\nDisallow: /\n";
        }
        $lines = ['User-agent: *'];
        if ([] === $settings['disallow']) { $lines[] = 'Disallow:'; }
        foreach ($settings['disallow'] as $path) { $lines[] = 'Disallow: ' . $path; }
        if ('' !== $origin) { $lines[] = ''; $lines[] = 'Sitemap: ' . $origin . '/sitemap.xml'; }
        return implode("\n", $lines) . "\n";
    }
}
