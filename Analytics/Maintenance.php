<?php
namespace Wbengine\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Wbengine\Config;
use Wbengine\Db;

final class Maintenance
{
    /** Delete at most one bounded batch of expired daily aggregates. */
    public function prune(?DateTimeImmutable $today = null, int $batch = 1000): int
    {
        if ($batch < 1 || $batch > 1000) { throw new InvalidArgumentException('Analytics prune batch must be between 1 and 1000.'); }
        $settings = Config::getAnalyticsSettings();
        $zone = new DateTimeZone($settings['timezone']);
        $current = ($today ?? new DateTimeImmutable('today', $zone))->setTimezone($zone)->setTime(0, 0);
        $oldest = $current->modify('-' . ($settings['retentionDays'] - 1) . ' days')->format('Y-m-d');
        Db::query('DELETE FROM cms_analytics_daily WHERE day < ? ORDER BY day LIMIT ?', [$oldest, $batch]);
        return (int) Db::getAffected();
    }
}
