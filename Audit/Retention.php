<?php
namespace Wbengine\Audit;

use Wbengine\Config;
use Wbengine\Db;

/**
 * Retention of cms_api_audit, cms_api_changes and cms_api_revisions, in bounded batches.
 *
 * Two rows are kept on purpose, however old: the newest change of every resource (its id is part of
 * the structure ETag and of recovery) and every revision a draft still points to.
 */
final class Retention
{
    public const BATCH = 500;

    /**
     * @param int|null $days Audit and change retention; null takes audit.retentionDays.
     * @param int|null $revisionDays Revision retention; null takes audit.revisionRetentionDays, or $days when given.
     * @return array{audit:int,changes:int,revisions:int,dry_run:bool,audit_days:int,revision_days:int}
     */
    public function prune(?int $days = null, ?int $revisionDays = null, bool $dryRun = false, ?int $now = null): array
    {
        $settings = Config::getAuditSettings();
        $revisionDays ??= null === $days ? $settings['revisionRetentionDays'] : $days;
        $days ??= $settings['retentionDays'];
        if ($days < 30 || $days > 3650 || $revisionDays < 30 || $revisionDays > 3650) {
            throw new \InvalidArgumentException('Retention must be 30 to 3650 days.');
        }
        $now ??= time();
        $cut = $now - $days * 86400; $revisionCut = $now - $revisionDays * 86400;
        $counts = [
            'audit' => $this->run('SELECT audit_id FROM cms_api_audit WHERE created_at < ? ORDER BY audit_id LIMIT ?', 'cms_api_audit', 'audit_id', $cut, $dryRun),
            'changes' => $this->run('SELECT c.change_id FROM cms_api_changes c WHERE c.created_at < ? AND EXISTS (SELECT 1 FROM cms_api_changes n WHERE n.resource = c.resource AND n.resource_id = c.resource_id AND n.change_id > c.change_id) ORDER BY c.change_id LIMIT ?', 'cms_api_changes', 'change_id', $cut, $dryRun),
            'revisions' => $this->run('SELECT r.revision_id FROM cms_api_revisions r LEFT JOIN cms_api_drafts d ON d.revision_id = r.revision_id WHERE r.created_at < ? AND d.revision_id IS NULL ORDER BY r.revision_id LIMIT ?', 'cms_api_revisions', 'revision_id', $revisionCut, $dryRun),
        ];
        return $counts + ['dry_run' => $dryRun, 'audit_days' => $days, 'revision_days' => $revisionDays];
    }

    /** Select a batch of ids, delete them, repeat. A dry run only counts. */
    private function run(string $select, string $table, string $idColumn, int $cut, bool $dryRun): int
    {
        if ($dryRun) {
            $count = preg_replace('/^SELECT [a-z_.]+ (FROM .*?) ORDER BY .*$/s', 'SELECT COUNT(*) AS n $1', $select);
            return (int) Db::fetchAssoc($count, [$cut])['n'];
        }
        $total = 0;
        do {
            $ids = array_map('intval', array_column(Db::fetchAllAssoc($select, [$cut, self::BATCH]) ?: [], $idColumn));
            if ($ids) {
                Db::query('DELETE FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($idColumn) . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
                $total += (int) Db::getAffected();
            }
        } while (count($ids) === self::BATCH);
        return $total;
    }
}
