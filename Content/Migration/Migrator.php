<?php
namespace Wbengine\Content\Migration;

use Wbengine\Cache;

/** Dry-run first. Apply is restricted to approved, freshly revalidated safe conversions. */
final class Migrator
{
    public function __construct(private readonly Model $model, private readonly Converter $converter, private readonly int $maxReportBytes = 8388608)
    {
        if ($maxReportBytes < 1) { throw new \InvalidArgumentException('Migration report budget must be positive'); }
    }

    public function planArticle(int $id, string $sourceFormat): array
    {
        $plans = []; $bytes = 0;
        foreach ([null, ...$this->model->locales($id)] as $locale) {
            $plan = $this->plan($locale === null ? 'article' : 'translation', $id, $locale, $sourceFormat);
            $bytes += strlen(Model::json($plan));
            if ($bytes > $this->maxReportBytes) { throw new \LengthException('Aggregate migration report exceeds its 8 MiB default budget; request one locale at a time'); }
            $plans[] = $plan;
        }
        return $plans;
    }
    public function plan(string $entity, int $id, ?string $locale, string $sourceFormat): array
    {
        if ($sourceFormat !== $this->converter->format()) { throw new \InvalidArgumentException('Explicit source_format must match the installed html or texy converter'); }
        return $this->prepare($entity, $id, $locale, $sourceFormat, $this->model->read($entity, $id, $locale));
    }
    private function prepare(string $entity, int $id, ?string $locale, string $sourceFormat, array $before): array
    {
        $issues = []; $fields = []; $after = $before;
        if ($before['content_format'] !== 'legacy_html') { $issues[] = 'Only explicit legacy_html records can be migrated; Markdown records are already converted'; }
        foreach (['introtext', 'bodytext'] as $field) {
            if ($before[$field] === null) { $fields[$field] = null; continue; }
            $result = $this->converter->convert($before[$field]); $fields[$field] = $result->jsonSerialize();
            $after[$field] = $result->markdown;
            foreach ($result->issues as $issue) { $issues[] = $field . ': ' . $issue; }
        }
        $after['content_format'] = 'markdown';
        $plan = ['version' => 1, 'scope' => $this->model->scope(), 'entity' => $entity, 'article_id' => $id, 'locale' => $locale, 'source_format' => $sourceFormat, 'converter' => $this->converter->version(), 'before' => $before, 'after' => $after, 'source_hash' => Model::hash($before), 'safe' => $issues === [], 'issues' => $issues, 'fields' => $fields];
        $plan['approval_hash'] = Model::hash($plan);
        return $plan;
    }
    public function apply(array $plan, string $approval): string
    {
        $hash = $plan['approval_hash'] ?? ''; unset($plan['approval_hash']);
        if (!is_string($hash) || !hash_equals(Model::hash($plan), $hash) || !hash_equals($hash, $approval)) { throw new \RuntimeException('Reviewed plan approval hash does not match'); }
        if (empty($plan['safe']) || $plan['source_format'] !== $this->converter->format() || $plan['converter'] !== $this->converter->version() || $plan['scope'] !== $this->model->scope()) { throw new \RuntimeException('Plan requires manual repair or a different converter/database'); }
        $this->model->begin();
        try {
            $current = $this->model->read($plan['entity'], $plan['article_id'], $plan['locale'], true);
            $backup = $this->model->backup($hash, true);
            if ($backup !== null && $backup['rolled_back_at'] === null && hash_equals($backup['target_hash'], Model::hash($current))) { $this->model->commit(); Cache::flush(); return $hash; }
            if (!hash_equals($plan['source_hash'], Model::hash($current))) { throw new \RuntimeException('Source content changed since the reviewed plan'); }
            $fresh = $this->prepare($plan['entity'], $plan['article_id'], $plan['locale'], $plan['source_format'], $current);
            if (!hash_equals($fresh['approval_hash'], $hash)) { throw new \RuntimeException('Conversion changed; generate and review a new plan'); }
            if ($backup === null) { $this->model->saveBackup($hash, $plan); }
            else { $this->model->markRollback($hash, null); }
            $this->model->write($plan['entity'], $plan['article_id'], $plan['locale'], $plan['after']);
            $this->model->commit();
        } catch (\Throwable $e) { $this->model->rollback(); throw $e; }
        Cache::flush(); return $hash;
    }
    public function rollback(string $id): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $id)) { throw new \InvalidArgumentException('Invalid migration id'); }
        $snapshot = $this->model->backup($id);
        if ($snapshot === null) { throw new \RuntimeException('Migration backup not found'); }
        $locale = $snapshot['locale'] === '' ? null : $snapshot['locale'];
        $this->model->begin();
        try {
            // Same lock order as apply: source record, then ledger record.
            $current = $this->model->read($snapshot['entity'], (int) $snapshot['article_id'], $locale, true);
            $backup = $this->model->backup($id, true);
            if ($backup['rolled_back_at'] !== null) {
                if (!hash_equals($backup['source_hash'], Model::hash($current))) { throw new \RuntimeException('Content changed after rollback'); }
                $this->model->commit(); return false;
            }
            if (!hash_equals($backup['target_hash'], Model::hash($current))) { throw new \RuntimeException('Content changed after migration; rollback refused'); }
            $before = json_decode($backup['before_data'], true, 512, JSON_THROW_ON_ERROR);
            if (!hash_equals($backup['source_hash'], Model::hash($before))) { throw new \RuntimeException('Migration backup is corrupt'); }
            $this->model->write($backup['entity'], (int) $backup['article_id'], $locale, $before);
            $this->model->markRollback($id, time()); $this->model->commit();
        } catch (\Throwable $e) { $this->model->rollback(); throw $e; }
        Cache::flush(); return true;
    }
}
