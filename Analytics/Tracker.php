<?php
namespace Wbengine\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use Wbengine\Config;
use Wbengine\Db;

/** Request-local, identity-free analytics collector with one batch flush. */
class Tracker
{
    private const MAX_EVENTS = 250;
    private array $events = [];
    private int $eventCount = 0;
    private bool $flushed = false;

    public function record(int $siteId, string $type, int $entityId): void
    {
        if ($this->flushed || $siteId < 1 || $entityId < 1 || !in_array($type, ['page', 'article', 'box'], true)) { return; }
        try { if (!Config::getAnalyticsSettings()['enabled']) { return; } }
        catch (\Throwable) { $this->logFailure(); return; }
        $key = $siteId . ':' . $type . ':' . $entityId;
        if (isset($this->events[$key])) {
            if ('box' === $type) {
                if ($this->eventCount >= self::MAX_EVENTS) { $this->logFailure(); return; }
                $this->events[$key]['views']++;
                $this->eventCount++;
            }
            return;
        }
        if ($this->eventCount >= self::MAX_EVENTS) { $this->logFailure(); return; }
        $this->events[$key] = ['site_id' => $siteId, 'entity_type' => $type, 'entity_id' => $entityId, 'views' => 1];
        $this->eventCount++;
    }

    /** Cache payload is limited to public content identifiers, never request metadata. */
    public function metadata(): array
    {
        return array_values($this->events);
    }

    public function flush(?DateTimeImmutable $now = null): void
    {
        if ($this->flushed) { return; }
        $this->flushed = true;
        self::persist($this->metadata(), $now, false);
    }

    /** A cached page hit replays the same validated content dimensions. */
    public static function flushMetadata(array $events, ?DateTimeImmutable $now = null): void
    {
        $tracker = new self();
        $budget = self::MAX_EVENTS;
        foreach (array_slice($events, 0, self::MAX_EVENTS) as $event) {
            if ($budget < 1) { break; }
            if (!is_array($event)) { continue; }
            $type = (string) ($event['entity_type'] ?? '');
            $repeat = 'box' === $type ? min($budget, max(1, (int) ($event['views'] ?? 1))) : 1;
            for ($i = 0; $i < $repeat; $i++) { $tracker->record((int) ($event['site_id'] ?? 0), $type, (int) ($event['entity_id'] ?? 0)); }
            $budget -= $repeat;
        }
        $tracker->flushed = true;
        self::persist($tracker->metadata(), $now, true);
    }

    private static function persist(array $events, ?DateTimeImmutable $now, bool $cachedHit): void
    {
        if (!$events) { return; }
        try {
            $settings = Config::getAnalyticsSettings();
            if (!$settings['enabled']) { return; }
            $zone = new DateTimeZone($settings['timezone']);
            $day = ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone)->format('Y-m-d');
            $connection = Db::getConnection();
            // MySQL refuses SET TRANSACTION inside an active transaction instead of
            // implicitly committing caller-owned work.
            $connection->query('SET TRANSACTION READ WRITE');
            if (!$connection->begin_transaction()) { self::logFailure(); return; }
            try {
                self::upsertDaily($events, $day);
                self::upsertLifetime($events);
                if ($cachedHit) { self::incrementCachedArticleViews($events); }
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            self::logFailure(get_class($e) . ':' . $e->getCode());
        }
    }

    private static function upsertDaily(array $events, string $day): void
    {
        $rows = [];
        foreach ($events as $event) {
            $key = implode(':', [$day, $event['site_id'], $event['entity_type'], $event['entity_id']]);
            if (!isset($rows[$key])) { $rows[$key] = ['day' => $day] + $event; }
            else { $rows[$key]['views'] += $event['views']; }
        }
        $values = [];
        $params = [];
        foreach ($rows as $row) {
            $values[] = '(?, ?, ?, ?, ?)';
            array_push($params, $row['day'], $row['site_id'], $row['entity_type'], $row['entity_id'], $row['views']);
        }
        Db::query('INSERT INTO cms_analytics_daily (day, site_id, entity_type, entity_id, views) VALUES ' . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE views = views + VALUES(views)', $params);
    }

    private static function upsertLifetime(array $events): void
    {
        $rows = [];
        foreach ($events as $event) {
            if (!in_array($event['entity_type'], ['page', 'box'], true)) { continue; }
            $key = implode(':', [$event['site_id'], $event['entity_type'], $event['entity_id']]);
            if (!isset($rows[$key])) { $rows[$key] = $event; }
            else { $rows[$key]['views'] += $event['views']; }
        }
        if (!$rows) { return; }
        $values = [];
        $params = [];
        foreach ($rows as $row) {
            $values[] = '(?, ?, ?, ?)';
            array_push($params, $row['site_id'], $row['entity_type'], $row['entity_id'], $row['views']);
        }
        Db::query('INSERT INTO cms_analytics_totals (site_id, entity_type, entity_id, views) VALUES ' . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE views = views + VALUES(views)', $params);
    }

    private static function incrementCachedArticleViews(array $events): void
    {
        $bySite = [];
        foreach ($events as $event) {
            if ('article' === $event['entity_type']) {
                $bySite[$event['site_id']][$event['entity_id']] = $event['views'];
            }
        }
        foreach ($bySite as $siteId => $articles) {
            $cases = []; $ids = []; $params = [];
            foreach ($articles as $id => $views) { $cases[] = 'WHEN ? THEN ?'; $ids[] = '?'; array_push($params, $id, $views); }
            $params[] = $siteId;
            array_push($params, ...array_keys($articles));
            Db::query('UPDATE cms_articles SET views = views + CASE id ' . implode(' ', $cases) . ' ELSE 0 END WHERE site_id = ? AND id IN (' . implode(', ', $ids) . ')', $params);
        }
    }

    private static function logFailure(?string $reason = null): void
    {
        error_log(__CLASS__ . ': analytics aggregation was skipped because storage or configuration was unavailable.' . (null === $reason ? '' : ' (' . $reason . ').'));
    }
}
