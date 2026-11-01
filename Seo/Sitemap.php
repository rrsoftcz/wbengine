<?php

namespace Wbengine\Seo;

use Wbengine\Content\ArticleVisibility;
use Wbengine\Db;

/**
 * The pages of the site as a sitemap. The pages are the ones a visitor can open by their link:
 * visible, public (acl 0) and without a hidden article, the conditions of Site\SiteModel::loadSiteData.
 * Only the link of a page is listed: the framework cannot know which parameter addresses a dynamic
 * (not strict) box accepts, and an address the box would answer with a 404 must never be listed.
 */
final class Sitemap
{
    /** The protocol limit of one sitemap file. */
    public const MAX_URLS = 50000;

    /**
     * @param string[] $exclude links to leave out
     * @param string[] $locales the locales of the site, the first one is the default
     * @return list<array{link: string, lastmod: ?int, locales: string[]}>
     */
    public static function pages(array $exclude, array $locales): array
    {
        $rows = Db::fetchAllAssoc(
            'SELECT s.site_id, s.link, (SELECT MAX(a.published) FROM `' . S_TABLE_ARTICLES . '` a WHERE a.site_id = s.site_id AND a.published > 0) AS lastmod'
            . ' FROM `' . S_TABLE_SITES . '` s WHERE s.visible = 1 AND COALESCE(s.acl, 0) = 0 AND s.link <> \'\'' . ArticleVisibility::pageCondition('s.site_id')
            . ' ORDER BY s.link, s.site_id'
        );
        $translated = [];
        foreach (Db::fetchAllAssoc('SELECT site_id, locale FROM `' . S_TABLE_SITE_TRANSLATIONS . '` WHERE COALESCE(title, \'\') <> \'\'') as $row) {
            $translated[(int) $row['site_id']][] = (string) $row['locale'];
        }
        $pages = [];
        $seen = [];
        foreach ($rows as $row) {
            $link = (string) $row['link'];
            if ('/' !== $link[0] || isset($seen[$link]) || in_array($link, $exclude, true)) { continue; }
            $seen[$link] = true;
            $has = $translated[(int) $row['site_id']] ?? [];
            // The default language is always there, another language only when the page has a translation.
            $listed = array_values(array_filter($locales, static fn(string $l, int $i): bool => 0 === $i || in_array($l, $has, true), ARRAY_FILTER_USE_BOTH));
            $pages[] = ['link' => $link, 'lastmod' => null !== $row['lastmod'] ? (int) $row['lastmod'] : null, 'locales' => $listed];
        }
        return $pages;
    }

    /**
     * @param list<array{link: string, lastmod: ?int, locales: string[]}> $pages
     * @param callable(string, string): string $url the address of a link in a locale (I18n::url)
     */
    public static function xml(array $pages, string $origin, callable $url): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        $count = 0;
        foreach ($pages as $page) {
            $alternates = '';
            if (count($page['locales']) > 1) {
                foreach ($page['locales'] as $locale) {
                    $alternates .= '    <xhtml:link rel="alternate" hreflang="' . self::e(str_replace('_', '-', $locale)) . '" href="' . self::e($origin . $url($page['link'], $locale)) . '"/>' . "\n";
                }
                $alternates .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . self::e($origin . $url($page['link'], $page['locales'][0])) . '"/>' . "\n";
            }
            $lastmod = null !== $page['lastmod'] ? '    <lastmod>' . gmdate('Y-m-d', $page['lastmod']) . "</lastmod>\n" : '';
            foreach ($page['locales'] as $locale) {
                if (++$count > self::MAX_URLS) { break 2; }
                $out .= "  <url>\n    <loc>" . self::e($origin . $url($page['link'], $locale)) . "</loc>\n" . $lastmod . $alternates . "  </url>\n";
            }
        }
        return $out . "</urlset>\n";
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
