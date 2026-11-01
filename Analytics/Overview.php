<?php
namespace Wbengine\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use Wbengine\Config;
use Wbengine\Db;
use Wbengine\Api\V2\Problem;

/** Read-only aggregation for the first-party analytics API. */
class Overview
{
    public function overview(array $query): array
    {
        $settings = Config::getAnalyticsSettings();
        if (!$settings['enabled']) { throw new Problem('analytics_disabled', 'Analytics is not enabled on this site.', 404); }
        $timezone = $settings['timezone'];
        $zone = new DateTimeZone($timezone);
        $from = $this->date($query['from'] ?? null, $zone);
        $to = $this->date($query['to'] ?? null, $zone);
        $granularity = $query['granularity'] ?? 'day';
        if (!in_array($granularity, ['day', 'week', 'month'], true)) { throw new Problem('invalid_granularity', 'Granularity must be day, week, or month.'); }
        if ($to < $from || $from->diff($to)->days >= $settings['retentionDays']) { throw new Problem('invalid_period', 'The date range must be ordered and fit within the configured analytics retention.'); }
        $oldest = (new DateTimeImmutable('today', $zone))->modify('-' . ($settings['retentionDays'] - 1) . ' days');
        if ($from < $oldest || $to > new DateTimeImmutable('today', $zone)) { throw new Problem('invalid_period', 'The requested dates are outside the available analytics period.'); }
        $fromText = $from->format('Y-m-d'); $toText = $to->format('Y-m-d');
        $trackingStart = Db::fetchAssoc('SELECT tracking_started_on FROM cms_analytics_meta WHERE id = 1');
        $complete = is_array($trackingStart) && $fromText >= (string) $trackingStart['tracking_started_on'];
        $locale = $this->locale($query['locale'] ?? null);
        $localeSql = null === $locale ? '' : ' AND locale = ?';
        $localeParams = null === $locale ? [] : [$locale];
        $periodExpr = match ($granularity) {
            'week' => "DATE_SUB(day, INTERVAL WEEKDAY(day) DAY)",
            'month' => "DATE_FORMAT(day, '%Y-%m-01')",
            default => 'day',
        };
        $rows = Db::fetchAllAssoc('SELECT ' . $periodExpr . ' AS period_start, entity_type, SUM(views) AS views FROM cms_analytics_daily WHERE day BETWEEN ? AND ?' . $localeSql . ' GROUP BY period_start, entity_type ORDER BY period_start', array_merge([$fromText, $toText], $localeParams));
        $series = $this->series($from, $to, $granularity, $rows);
        $totals = ['page_views' => 0, 'article_views' => 0, 'box_appearances' => 0];
        foreach ($series as $row) { foreach (array_keys($totals) as $key) { $totals[$key] += $row[$key]; } }
        return [
            'timezone' => $timezone,
            'period' => ['from' => $fromText, 'to' => $toText, 'granularity' => $granularity, 'complete' => $complete],
            'totals' => $totals,
            'lifetime_totals' => $this->lifetimeTotals(),
            'per_locale' => Schema::hasLocale(),
            'by_locale' => $this->byLocale($fromText, $toText, $locale),
            'series' => $series,
            'top_pages' => $this->top('page', 'cms_sites', 'site_id', 'title', $fromText, $toText, 'views', $locale),
            'top_articles' => $this->topArticles($fromText, $toText, $locale),
            'top_boxes' => $this->top('box', 'cms_boxes', 'id', 'name', $fromText, $toText, 'appearances', $locale),
        ];
    }

    private function date(mixed $value, DateTimeZone $zone): DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) { throw new Problem('invalid_date', 'Dates must use YYYY-MM-DD format.'); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
        if (!$date || $date->format('Y-m-d') !== $value) { throw new Problem('invalid_date', 'Dates must use YYYY-MM-DD format.'); }
        return $date;
    }

    private function series(DateTimeImmutable $from, DateTimeImmutable $to, string $granularity, array $rows): array
    {
        $values = [];
        foreach ($rows as $row) { $values[$row['period_start']][$row['entity_type']] = (int) $row['views']; }
        $cursor = match ($granularity) { 'week' => $from->modify('monday this week'), 'month' => $from->modify('first day of this month'), default => $from };
        $last = match ($granularity) { 'week' => $to->modify('monday this week'), 'month' => $to->modify('first day of this month'), default => $to };
        $out = [];
        while ($cursor <= $last) {
            $key = $cursor->format('Y-m-d'); $counts = $values[$key] ?? [];
            $out[] = ['period_start' => $key, 'page_views' => $counts['page'] ?? 0, 'article_views' => $counts['article'] ?? 0, 'box_appearances' => $counts['box'] ?? 0];
            $cursor = $cursor->modify('day' === $granularity ? '+1 day' : ('week' === $granularity ? '+1 week' : '+1 month'));
        }
        return $out;
    }

    private function lifetimeTotals(): array
    {
        $rows = Db::fetchAllAssoc('SELECT entity_type, SUM(views) AS views FROM cms_analytics_totals GROUP BY entity_type');
        $totals = ['page_views' => 0, 'article_views' => 0, 'box_appearances' => 0];
        foreach ($rows as $row) { $key = match ($row['entity_type']) { 'page' => 'page_views', 'box' => 'box_appearances', default => null }; if ($key) { $totals[$key] = (int) $row['views']; } }
        $article = Db::fetchAssoc('SELECT COALESCE(SUM(views),0) AS views FROM cms_articles');
        $totals['article_views'] = (int) ($article['views'] ?? 0);
        return $totals;
    }

    private function top(string $type, string $table, string $idColumn, string $nameColumn, string $from, string $to, string $countName, ?string $locale = null): array
    {
        $rows = Db::fetchAllAssoc('SELECT e.' . Db::identifier($idColumn) . ' AS id, e.' . Db::identifier($nameColumn) . ' AS label, SUM(d.views) AS views FROM cms_analytics_daily d JOIN ' . Db::identifier($table) . ' e ON e.' . Db::identifier($idColumn) . ' = d.entity_id WHERE d.entity_type = ? AND d.day BETWEEN ? AND ?' . (null === $locale ? '' : ' AND d.locale = ?') . ' GROUP BY e.' . Db::identifier($idColumn) . ', e.' . Db::identifier($nameColumn) . ' ORDER BY views DESC, id ASC LIMIT 10', null === $locale ? [$type, $from, $to] : [$type, $from, $to, $locale]);
        return array_map(static fn($row) => ['id' => (int) $row['id'], ('page' === $type ? 'title' : 'name') => $row['label'], $countName => (int) $row['views']], $rows);
    }

    private function topArticles(string $from, string $to, ?string $locale = null): array
    {
        $rows = Db::fetchAllAssoc('SELECT a.id, a.title, SUM(d.views) AS views FROM cms_analytics_daily d JOIN cms_articles a ON a.id = d.entity_id WHERE d.entity_type = ? AND d.day BETWEEN ? AND ?' . (null === $locale ? '' : ' AND d.locale = ?') . ' GROUP BY a.id, a.title ORDER BY views DESC, a.id ASC LIMIT 10', null === $locale ? ['article', $from, $to] : ['article', $from, $to, $locale]);
        return array_map(static fn($row) => ['id' => (int) $row['id'], 'title' => $row['title'], 'views' => (int) $row['views']], $rows);
    }
    /** The optional language filter: null for all languages. A language needs migration 6.7.0/013. */
    private function locale(mixed $value): ?string
    {
        if (null === $value || '' === $value) { return null; }
        if (!is_string($value) || '' === Schema::cleanLocale($value)) { throw new Problem('invalid_locale', 'The language must be a locale such as en or cs.', 400); }
        if (!Schema::hasLocale()) { throw new Problem('locale_unavailable', 'Views per language need the database update migrations/6.7.0/013-analytics-locale.sql.', 409); }
        return $value;
    }

    /**
     * The period split by language, most viewed first. The locale null is the views counted before the split
     * existed (or on a site without languages): they are in the totals and belong to no language.
     */
    private function byLocale(string $from, string $to, ?string $locale): array
    {
        if (!Schema::hasLocale()) { return []; }
        $rows = Db::fetchAllAssoc('SELECT locale, entity_type, SUM(views) AS views FROM cms_analytics_daily WHERE day BETWEEN ? AND ?' . (null === $locale ? '' : ' AND locale = ?') . ' GROUP BY locale, entity_type', null === $locale ? [$from, $to] : [$from, $to, $locale]);
        $by = [];
        foreach ($rows as $row) {
            $key = (string) $row['locale'];
            $by[$key] ??= ['locale' => '' === $key ? null : $key, 'page_views' => 0, 'article_views' => 0, 'box_appearances' => 0];
            $field = match ($row['entity_type']) { 'page' => 'page_views', 'article' => 'article_views', default => 'box_appearances' };
            $by[$key][$field] = (int) $row['views'];
        }
        $list = array_values($by);
        usort($list, static fn(array $a, array $b): int => [$b['page_views'], (string) $a['locale']] <=> [$a['page_views'], (string) $b['locale']]);
        return $list;
    }
}
