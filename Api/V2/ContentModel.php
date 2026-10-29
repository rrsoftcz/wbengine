<?php
namespace Wbengine\Api\V2;

use Wbengine\Db;
use Wbengine\Cache;

/** CMS content model with row-derived concurrency and transactional recovery records. */
class ContentModel extends Model
{
    private BoxRegistry $boxes;
    protected $flusher;

    public function __construct(?BoxRegistry $boxes = null, ?callable $flusher = null)
    {
        $this->boxes = $boxes ?? BoxRegistry::current();
        $this->flusher = $flusher ?? [Cache::class, 'flush'];
    }

    public function boxTypes(): array { return $this->boxes->catalog(); }

    public function collection(string $resource, array $query): array
    {
        $def = Resources::definition($resource);
        $pagination = Contract::pagination($query);
        // Only pages (title, link) and boxes (name) can be searched, on a prepared LIKE with escaped wildcards.
        $where = '';
        $params = [];
        $search = Contract::search($query);
        if (null !== $search && in_array($resource, ['pages', 'boxes'], true)) {
            $columns = 'pages' === $resource ? ['title', 'link'] : ['name'];
            $where = ' WHERE ' . implode(' OR ', array_map(fn ($column) => Db::identifier($column) . " LIKE ? ESCAPE '\\\\'", $columns));
            $params = array_fill(0, count($columns), Contract::likePattern($search));
        }
        $rows = Db::fetchAllAssoc('SELECT * FROM ' . Db::identifier($def['table']) . $where . ' ORDER BY ' . Db::identifier($def['id']) . ' LIMIT ? OFFSET ?', array_merge($params, [$pagination['per_page'], $pagination['offset']]));
        $count = (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM ' . Db::identifier($def['table']) . $where, $params)['n'];
        $items = array_map(fn ($row) => $this->publicRow($resource, $row), $rows);
        if ('articles' === $resource && $items) {
            $ids = array_column($items, 'id');
            $drafts = Db::fetchAllAssoc('SELECT article_id, payload FROM cms_api_drafts WHERE article_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
            $titles = [];
            foreach ($drafts as $draft) { $titles[$draft['article_id']] = json_decode($draft['payload'], true, 64, JSON_THROW_ON_ERROR)['content']['title']; }
            foreach ($items as &$item) { $item['has_draft'] = array_key_exists($item['id'], $titles); $item['draft_title'] = $titles[$item['id']] ?? null; }
            unset($item);
        }
        if ($items && in_array($resource, ['articles', 'pages', 'menu'], true)) { $items = $this->withTranslationLocales($resource, $items, $def['id']); }
        return Contract::page($items, $query, $count);
    }

    /**
     * Adds "translation_locales" (the languages that have a translation) to every item with one
     * query, so a client can show "3 of 20 languages" without asking for each language.
     * An article counts a language from its draft when a draft exists, as the editor does.
     */
    private function withTranslationLocales(string $resource, array $items, string $idField): array
    {
        [$table, $key] = match ($resource) { 'articles' => ['cms_article_translations', 'article_id'], 'pages' => ['cms_site_translations', 'site_id'], default => ['cms_menu_translations', 'menu_id'] };
        $ids = array_column($items, $idField);
        $found = [];
        foreach (Db::fetchAllAssoc('SELECT ' . Db::identifier($key) . ' AS record_id, locale FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($key) . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY locale', $ids) as $row) { $found[(int) $row['record_id']][$row['locale']] = true; }
        if ('articles' === $resource) {
            foreach (Db::fetchAllAssoc('SELECT article_id, payload FROM cms_api_drafts WHERE article_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $draft) {
                $found[(int) $draft['article_id']] = array_fill_keys(array_keys((array) (json_decode($draft['payload'], true, 64, JSON_THROW_ON_ERROR)['translations'] ?? [])), true);
            }
        }
        foreach ($items as &$item) { $locales = array_keys($found[(int) $item[$idField]] ?? []); sort($locales); $item['translation_locales'] = $locales; }
        unset($item);
        return $items;
    }

    /**
     * Ids of the boxes visible on a page (listed in its sites or shared, as Section\Model selects
     * them) that render articles. Read-only and derived, never part of the writable fields.
     */
    private function articleBoxIds(int $siteId): array
    {
        $ids = [];
        foreach (Db::fetchAllAssoc("SELECT id, module, method FROM cms_boxes WHERE (CASE WHEN JSON_VALID(sites) THEN JSON_SEARCH(sites, 'one', ?) IS NOT NULL ELSE 0 END OR shared = 1) ORDER BY id", [(string) $siteId]) as $box) {
            if ($this->boxes->rendersArticles((string) $box['module'], (string) $box['method'])) { $ids[] = (int) $box['id']; }
        }
        return $ids;
    }

    public function read(string $resource, $id): array
    {
        if ('articles' === $resource) { return $this->articleResponse($this->articleState(Resources::id($id))); }
        $row = $this->row($resource, Resources::id($id));
        $data = $this->publicRow($resource, $row);
        if (in_array($resource, ['pages', 'menu'], true)) { $data = $this->withTranslationLocales($resource, [$data], Resources::definition($resource)['id'])[0]; }
        if ('pages' === $resource) { $data['article_box_ids'] = $this->articleBoxIds((int) $row['site_id']); }
        return ['data' => $data, 'etag' => Resources::etag($row)];
    }

    public function create(string $resource, array $data, array $actor): array
    {
        if ('articles' === $resource) { return $this->createArticle($data, $actor); }
        $data = Resources::validate($resource, $data, null, true);
        $connection = $this->beginContentTransaction($resource);
        try {
            $data = $this->storageData($resource, $data);
            $this->relations($resource, $data);
            $def = Resources::definition($resource);
            if ('articles' === $resource) { $data['published'] = 0; }
            if ('pages' === $resource) { $data += ['html_keywords' => '']; }
            $id = Db::insert($def['table'], $data);
            $row = $this->row($resource, $id, true);
            $this->verifyStored($row, $data);
            $this->change($resource, $id, 'create', null, $row, $actor);
            $connection->commit();
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
        ($this->flusher)();
        return ['data' => $this->publicRow($resource, $row), 'etag' => Resources::etag($row)];
    }

    public function update(string $resource, $id, array $data, ?string $etag, array $actor): array
    {
        if ('articles' === $resource) { return $this->saveDraft(Resources::id($id), $data, $etag, $actor); }
        $id = Resources::id($id);
        $connection = $this->beginContentTransaction($resource);
        $changed = false;
        try {
            $before = $this->row($resource, $id, true);
            Resources::match($etag, $before);
            $data = Resources::validate($resource, $data, $before);
            $data = $this->storageData($resource, $data, $before);
            $this->relations($resource, $data, $id);
            $def = Resources::definition($resource);
            Db::update($def['table'], $data, [$def['id'] => $id]);
            $after = $this->row($resource, $id, true);
            $this->verifyStored($after, $data);
            $changed = $before !== $after;
            if ($changed) { $this->change($resource, $id, 'update', $before, $after, $actor); }
            $connection->commit();
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
        if ($changed) { ($this->flusher)(); }
        return ['data' => $this->publicRow($resource, $after), 'etag' => Resources::etag($after)];
    }

    public function delete(string $resource, $id, ?string $etag, array $actor): array
    {
        $id = Resources::id($id);
        $connection = $this->beginContentTransaction($resource);
        try {
            $before = 'articles' === $resource ? $this->articleState($id, true) : $this->row($resource, $id, true);
            Resources::match($etag, $before);
            $this->assertNotReferenced($resource, $id);
            if ('articles' === $resource) {
                Db::query('DELETE FROM cms_api_drafts WHERE article_id = ?', [$id]);
                Db::query('DELETE FROM cms_article_translations WHERE article_id = ?', [$id]);
            }
            foreach (['pages' => ['cms_site_translations', 'site_id'], 'menu' => ['cms_menu_translations', 'menu_id']] as $name => [$translationTable, $translationKey]) {
                if ($name === $resource) {
                    $translationBackup = Db::fetchAllAssoc('SELECT * FROM ' . Db::identifier($translationTable) . ' WHERE ' . Db::identifier($translationKey) . ' = ? ORDER BY locale FOR UPDATE', [$id]);
                    Db::query('DELETE FROM ' . Db::identifier($translationTable) . ' WHERE ' . Db::identifier($translationKey) . ' = ?', [$id]); }
            }
            $def = Resources::definition($resource);
            Db::query('DELETE FROM ' . Db::identifier($def['table']) . ' WHERE ' . Db::identifier($def['id']) . ' = ?', [$id]);
            if (in_array($resource, ['pages', 'menu'], true)) { $before['translations'] = $translationBackup ?? []; }
            $this->change($resource, $id, 'delete', $before, null, $actor);
            $connection->commit();
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
        ($this->flusher)();
        return ['data' => ['deleted' => true, 'id' => $id]];
    }

    /** Recovery history also supplies an If-Match token for a deleted record. */
    public function history(string $resource, $id, array $query): array
    {
        $this->structuralResource($resource); $id = Resources::id($id);
        [$row, $state] = $this->structuralState($resource, $id);
        $pagination = Contract::pagination($query);
        $records = Db::fetchAllAssoc('SELECT * FROM cms_api_changes WHERE resource = ? AND resource_id = ? ORDER BY change_id DESC LIMIT ? OFFSET ?', [$resource, (string) $id, $pagination['per_page'], $pagination['offset']]);
        foreach ($records as &$record) {
            foreach (['before_data', 'after_data'] as $field) { $record[$field] = null === $record[$field] ? null : json_decode($record[$field], true, 64, JSON_THROW_ON_ERROR); }
        }
        unset($record);
        $total = (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_changes WHERE resource = ? AND resource_id = ?', [$resource, (string) $id])['n'];
        return ['body' => Contract::page($records, $query, $total), 'etag' => Resources::etag($state)];
    }

    public function restoreStructure(string $resource, $id, $changeId, ?string $etag, array $actor): array
    {
        $this->structuralResource($resource); $id = Resources::id($id); $changeId = Resources::id($changeId);
        $def = Resources::definition($resource);
        $connection = $this->beginContentTransaction($resource);
        try {
            [$before, $state] = $this->structuralState($resource, $id, true);
            Resources::match($etag, $state);
            $change = Db::fetchAssoc('SELECT before_data, after_data FROM cms_api_changes WHERE resource = ? AND resource_id = ? AND change_id = ? FOR UPDATE', [$resource, (string) $id, $changeId]);
            if (!$change) { throw new Problem('not_found', 'Recovery record not found.', 404); }
            $snapshot = json_decode($change['before_data'] ?? $change['after_data'], true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($snapshot) || isset($snapshot['locale']) || ($snapshot[$def['id']] ?? null) !== $id) { throw new Problem('invalid_recovery', 'This recovery record cannot restore the requested resource.', 409); }
            $data = array_intersect_key($snapshot, $def['fields']);
            if ('boxes' === $resource) {
                $type = $this->boxes->typeFor($snapshot['module'] ?? '', $snapshot['method'] ?? '');
                if (null === $type) { throw new Problem('invalid_box_type', 'The saved box type is not currently registered.'); }
                $data['type'] = $type;
                $data['settings'] = json_decode($snapshot['api_settings'] ?? '{}', true, 32, JSON_THROW_ON_ERROR);
                if (isset($data['sites'])) { $data['sites'] = json_decode($data['sites'], true, 32, JSON_THROW_ON_ERROR); }
            }
            // NULL values come from an immutable server snapshot, never HTTP fields.
            $nulls = array_filter($data, static fn ($value) => null === $value);
            $validated = Resources::validate($resource, array_filter($data, static fn ($value) => null !== $value), $before);
            $data = $this->storageData($resource, $validated + $nulls, $before);
            $this->relations($resource, $data, $id);
            if (null === $before) { Db::insert($def['table'], [$def['id'] => $id] + $data); }
            else { Db::update($def['table'], $data, [$def['id'] => $id]); }
            $after = $this->row($resource, $id, true);
            $this->verifyStored($after, $data);
            if (array_key_exists('translations', $snapshot)) {
                $this->restoreStructuralTranslations($resource, $id, $snapshot['translations']);
                $after['translations'] = $snapshot['translations'];
            }
            if (in_array($resource, ['pages', 'menu'], true)) {
                $after['translations'] = $this->structuralTranslations($resource, $id, true);
                if (null !== $before) { $before['translations'] = $state['translations']; }
            }
            $this->change($resource, $id, 'restore:' . $changeId, $before, $after, $actor);
            $result = $this->read($resource, $id);
            $connection->commit();
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
        ($this->flusher)();
        return $result;
    }

    private function structuralResource(string $resource): void
    {
        if (!in_array($resource, ['pages', 'menu', 'sections', 'boxes'], true)) { throw new Problem('invalid_resource', 'This resource does not support structural recovery.'); }
    }

    private function structuralState(string $resource, int $id, bool $lock = false): array
    {
        $def = Resources::definition($resource);
        $row = Db::fetchAssoc('SELECT * FROM ' . Db::identifier($def['table']) . ' WHERE ' . Db::identifier($def['id']) . ' = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        $row = $row ? array_change_key_case($row, CASE_LOWER) : null;
        $latest = Db::fetchAssoc('SELECT change_id FROM cms_api_changes WHERE resource = ? AND resource_id = ? ORDER BY change_id DESC LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), [$resource, (string) $id]);
        if (!$row && !$latest) { throw new Problem('not_found', 'Resource and recovery history not found.', 404); }
        return [$row, ['resource' => $resource, 'id' => $id, 'record' => $row, 'translations' => $this->structuralTranslations($resource, $id, $lock), 'last_change_id' => $latest['change_id'] ?? 0]];
    }

    private function structuralTranslations(string $resource, int $id, bool $lock = false): array
    {
        if (!in_array($resource, ['pages', 'menu'], true)) { return []; }
        [$table, $key] = 'pages' === $resource ? ['cms_site_translations', 'site_id'] : ['cms_menu_translations', 'menu_id'];
        return Db::fetchAllAssoc('SELECT * FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($key) . ' = ? ORDER BY locale' . ($lock ? ' FOR UPDATE' : ''), [$id]);
    }

    private function restoreStructuralTranslations(string $resource, int $id, array $translations): void
    {
        [$table, $key, $fields] = match ($resource) {
            'pages' => ['cms_site_translations', 'site_id', ['title', 'html_title', 'html_description', 'html_keywords']],
            'menu' => ['cms_menu_translations', 'menu_id', ['name', 'description']],
            default => throw new Problem('invalid_recovery', 'This resource cannot restore translations.', 409),
        };
        Db::query('DELETE FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($key) . ' = ?', [$id]);
        foreach ($translations as $translation) {
            $this->locale($translation['locale'] ?? '');
            $data = array_intersect_key($translation, array_flip($fields));
            $values = [$key => $id, 'locale' => $translation['locale']] + $data;
            Db::insert($table, $values);
            $this->verifyStored(Db::fetchAssoc('SELECT * FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($key) . ' = ? AND locale = ?', [$id, $translation['locale']]), $values);
        }
    }

    private function createArticle(array $data, array $actor): array
    {
        $data = Resources::validate('articles', $data, null, true);
        $connection = $this->beginContentTransaction('articles');
        try {
            $this->relations('articles', $data);
            // Existing front-end readers do not filter published. Never attach a
            // reservation to a visible site or place private draft text in this row.
            $empty = ['site_id' => 0, 'title' => '', 'introtext' => '', 'bodytext' => '', 'content_format' => 'markdown', 'published' => 0];
            $id = Db::insert('cms_articles', $empty);
            $row = $this->row('articles', $id, true);
            $this->verifyStored($row, $empty);
            $state = $this->articleState($id, true);
            $payload = ['content' => array_merge($this->articleFields($row), $data), 'translations' => []];
            $this->recordDraft($id, $payload, $this->sourceEtag($state), 'create', $actor);
            $this->change('articles', $id, 'draft.create', null, $payload, $actor);
            $result = $this->articleResponse($this->articleState($id, true));
            $connection->commit();
            return $result;
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
    }

    private function saveDraft(int $id, array $data, ?string $etag, array $actor): array
    {
        $connection = $this->beginContentTransaction('articles');
        try {
            $state = $this->articleState($id, true);
            Resources::match($etag, $state);
            $payload = $this->draftPayload($state);
            $data = Resources::validate('articles', $data, $payload['content']);
            $this->relations('articles', $data, $id);
            $before = $payload;
            $payload['content'] = array_merge($payload['content'], $data);
            if ($before !== $payload || null === $state['draft']) {
                $source = $state['draft']['source_etag'] ?? $this->sourceEtag($state);
                $this->recordDraft($id, $payload, $source, 'save', $actor);
                $this->change('articles', $id, 'draft.save', $before, $payload, $actor);
            }
            $result = $this->articleResponse($this->articleState($id, true));
            $connection->commit();
            return $result;
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
    }

    public function revisions($id, array $query): array
    {
        $id = Resources::id($id);
        $this->row('articles', $id);
        $page = Contract::pagination($query);
        $rows = Db::fetchAllAssoc('SELECT revision_id, article_id, payload, action, integration_id, central_actor, created_at FROM cms_api_revisions WHERE article_id = ? ORDER BY revision_id DESC LIMIT ? OFFSET ?', [$id, $page['per_page'], $page['offset']]);
        foreach ($rows as &$row) { $row['payload'] = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR); $row['payload']['translations'] = (object) $row['payload']['translations']; }
        unset($row);
        $total = (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_revisions WHERE article_id = ?', [$id])['n'];
        return Contract::page($rows, $query, $total);
    }

    public function restore($id, $revisionId, ?string $etag, array $actor): array
    {
        $id = Resources::id($id); $revisionId = Resources::id($revisionId);
        $connection = $this->beginContentTransaction('articles');
        try {
            $state = $this->articleState($id, true);
            Resources::match($etag, $state);
            $revision = Db::fetchAssoc('SELECT payload FROM cms_api_revisions WHERE article_id = ? AND revision_id = ?', [$id, $revisionId]);
            if (!$revision) { throw new Problem('not_found', 'Revision not found.', 404); }
            $payload = json_decode($revision['payload'], true, 64, JSON_THROW_ON_ERROR);
            $this->recordDraft($id, $payload, $this->sourceEtag($state), 'restore', $actor);
            $this->change('articles', $id, 'draft.restore', $this->draftPayload($state), $payload, $actor);
            $result = $this->articleResponse($this->articleState($id, true));
            $connection->commit();
            return $result;
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
    }

    public function publish($id, ?string $etag, array $actor): array
    {
        $id = Resources::id($id);
        $connection = $this->beginContentTransaction('articles');
        try {
            $state = $this->articleState($id, true);
            Resources::match($etag, $state);
            if (null === $state['draft']) { throw new Problem('no_draft', 'There is no draft to publish.', 409); }
            if (!hash_equals($this->sourceEtag($state), $state['draft']['source_etag'])) { throw new Problem('precondition_failed', 'Published content changed since this draft was based on it.', 412); }
            $payload = $this->draftPayload($state);
            $this->assertPublicationFormats($payload, $state);
            $data = $payload['content'];
            $this->relations('articles', $data, $id);
            if (!empty($data['site_id']) && Db::fetchAssoc('SELECT id FROM cms_articles WHERE site_id = ? AND id <> ? LIMIT 1 FOR UPDATE', [$data['site_id'], $id])) {
                throw new Problem('article_site_conflict', 'Another article already occupies this page.', 409);
            }
            if (empty($data['site_id'])) { throw new Problem('missing_site', 'Choose a page before publishing this article.', 422); }
            $data['published'] = time();
            Db::update('cms_articles', $data, ['id' => $id]);
            $this->verifyStored($this->row('articles', $id, true), $data);
            Db::query('DELETE FROM cms_article_translations WHERE article_id = ?', [$id]);
            foreach ($payload['translations'] as $locale => $translation) {
                $values = ['article_id' => $id, 'locale' => $locale] + $translation;
                Db::insert('cms_article_translations', $values);
                $this->verifyStored(Db::fetchAssoc('SELECT * FROM cms_article_translations WHERE article_id = ? AND locale = ?', [$id, $locale]), $values);
            }
            $this->revision($id, $payload, 'publish', $actor);
            Db::query('DELETE FROM cms_api_drafts WHERE article_id = ?', [$id]);
            $after = $this->articleState($id, true);
            $this->change('articles', $id, 'publish', $state, $after, $actor);
            $result = $this->articleResponse($after);
            $connection->commit();
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
        ($this->flusher)();
        return $result;
    }

    public function metrics($id): array
    {
        $row = $this->row('articles', Resources::id($id));
        return ['views' => (int) ($row['views'] ?? 0)];
    }

    public function preview($id, ?string $locale = null): array
    {
        $state = $this->articleState(Resources::id($id));
        $payload = $this->draftPayload($state);
        $content = $payload['content'];
        if (null !== $locale) {
            $this->locale($locale);
            $translation = $payload['translations'][$locale] ?? [];
            foreach (['title', 'introtext', 'bodytext'] as $field) {
                if (isset($translation[$field]) && '' !== $translation[$field]) {
                    $content[$field] = $translation[$field];
                    if ('title' !== $field) { $content[$field . '_format'] = $translation['content_format'] ?? 'legacy_html'; }
                }
            }
        }
        foreach (['introtext', 'bodytext'] as $field) {
            $format = $content[$field . '_format'] ?? $content['content_format'] ?? 'legacy_html';
            $content[$field] = 'markdown' === $format ? (new \Wbengine\Markdown())->render($content[$field] ?? '') : htmlspecialchars($content[$field] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return ['title' => $content['title'], 'introtext' => $content['introtext'], 'bodytext' => $content['bodytext'], 'private' => true];
    }

    public function readTranslation(string $entity, $id, string $locale): array
    {
        [$resource, $table, $key, $fields] = $this->translationDefinition($entity);
        $id = Resources::id($id); $this->locale($locale);
        if ('article' === $entity) {
            $state = $this->articleState($id);
            $payload = $this->draftPayload($state);
            return ['data' => (object) ($payload['translations'][$locale] ?? []), 'etag' => Resources::etag($state)];
        }
        $source = $this->row($resource, $id);
        $row = Db::fetchAssoc('SELECT * FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($key) . ' = ? AND locale = ?', [$id, $locale]);
        return ['data' => (object) ($row ? array_intersect_key($row, array_flip($fields)) : []), 'etag' => Resources::etag([$source, $row])];
    }

    public function writeTranslation(string $entity, $id, string $locale, ?array $data, ?string $etag, array $actor): array
    {
        [$resource, $table, $key, $fields] = $this->translationDefinition($entity);
        $id = Resources::id($id); $this->locale($locale);
        if (null !== $data && (!$data || array_diff(array_keys($data), $fields))) { throw new Problem('invalid_field', 'Unsupported translation fields.'); }
        $connection = $this->beginContentTransaction($resource);
        $changed = false;
        try {
            if ('article' === $entity) {
                $state = $this->articleState($id, true);
                Resources::match($etag, $state);
                $before = $this->draftPayload($state); $payload = $before;
                if (null === $data) { unset($payload['translations'][$locale]); }
                else {
                    $current = $payload['translations'][$locale] ?? ['content_format' => 'markdown'];
                    $validated = Resources::validate('articles', $data, $current);
                    if (!isset($payload['translations'][$locale])) { $validated['content_format'] = 'markdown'; }
                    $payload['translations'][$locale] = array_merge($current, $validated);
                }
                if ($before !== $payload) {
                    $this->recordDraft($id, $payload, $state['draft']['source_etag'] ?? $this->sourceEtag($state), 'translation', $actor);
                    $this->change('articles', $id, 'draft.translation', $before, $payload, $actor);
                }
                $result = $this->articleResponse($this->articleState($id, true));
            } else {
                $source = $this->row($resource, $id, true);
                $before = Db::fetchAssoc('SELECT * FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($key) . ' = ? AND locale = ? FOR UPDATE', [$id, $locale]);
                Resources::match($etag, [$source, $before]);
                $translationsBefore = $this->structuralTranslations($resource, $id, true);
                if (null !== $data) { $data = Resources::validate($resource, $data); }
                if (null === $data) { Db::query('DELETE FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($key) . ' = ? AND locale = ?', [$id, $locale]); }
                elseif ($before) { Db::update($table, $data, [$key => $id, 'locale' => $locale]); }
                else { Db::insert($table, [$key => $id, 'locale' => $locale] + $data); }
                $after = Db::fetchAssoc('SELECT * FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($key) . ' = ? AND locale = ?', [$id, $locale]);
                if (null !== $data) { $this->verifyStored($after, $data); }
                $changed = $before !== $after;
                if ($changed) { $this->change($resource, $id, 'translation.' . $locale, $source + ['translations' => $translationsBefore], $source + ['translations' => $this->structuralTranslations($resource, $id, true)], $actor); }
                $result = ['data' => (object) ($after ? array_intersect_key($after, array_flip($fields)) : []), 'etag' => Resources::etag([$source, $after])];
            }
            $connection->commit();
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
        if ($changed) { ($this->flusher)(); }
        return $result;
    }

    private function translationDefinition(string $entity): array
    {
        return match ($entity) {
            'site' => ['pages', 'cms_site_translations', 'site_id', ['title', 'html_title', 'html_description', 'html_keywords']],
            'menu' => ['menu', 'cms_menu_translations', 'menu_id', ['name', 'description']],
            'article' => ['articles', 'cms_article_translations', 'article_id', ['title', 'introtext', 'bodytext', 'content_format']],
            default => throw new Problem('invalid_resource', 'Unsupported translation entity.'),
        };
    }

    private function locale(string $locale): void
    {
        if (!preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', $locale)) { throw new Problem('invalid_locale', 'Invalid translation locale.'); }
    }

    private function articleState(int $id, bool $lock = false): array
    {
        $row = $this->row('articles', $id, $lock);
        // Views are telemetry, not editable content. Keep the edit representation
        // and its strong ETag stable while front-end requests count visits.
        unset($row['views']);
        $translations = Db::fetchAllAssoc('SELECT * FROM cms_article_translations WHERE article_id = ? ORDER BY locale' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        $draft = Db::fetchAssoc('SELECT * FROM cms_api_drafts WHERE article_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        return ['published' => $row, 'translations' => $translations, 'draft' => $draft ?: null];
    }

    /** Existing trusted HTML may be preserved byte-for-byte, never newly introduced. */
    private function assertPublicationFormats(array $payload, array $state): void
    {
        if (array_key_exists('source', $payload['content'])) { Resources::validate('articles', ['source' => $payload['content']['source']], $state['published']); }
        $sources = ['base' => $state['published']];
        foreach ($state['translations'] as $translation) { $sources[$translation['locale']] = $translation; }
        $candidates = ['base' => $payload['content']] + $payload['translations'];
        foreach ($candidates as $locale => $content) {
            $format = $content['content_format'] ?? 'legacy_html';
            if ('markdown' === $format) { continue; }
            $source = $sources[$locale] ?? null;
            if ('legacy_html' !== $format || null === $source || ($source['content_format'] ?? 'legacy_html') !== 'legacy_html') {
                throw new Problem('format_replacement_required', 'Publication requires Markdown for changed legacy text.');
            }
            foreach (['introtext', 'bodytext'] as $field) {
                if (($content[$field] ?? null) !== ($source[$field] ?? null)) { throw new Problem('format_replacement_required', 'Publication requires Markdown for changed legacy text.'); }
            }
        }
    }

    private function sourceEtag(array $state): string { return Resources::etag([$state['published'], $state['translations']]); }

    private function articleFields(array $row): array { return array_intersect_key($row, Resources::definition('articles')['fields']); }

    private function draftPayload(array $state): array
    {
        if (null !== $state['draft']) { return json_decode($state['draft']['payload'], true, 64, JSON_THROW_ON_ERROR); }
        $translations = [];
        foreach ($state['translations'] as $row) { $translations[$row['locale']] = array_intersect_key($row, array_flip(['title', 'introtext', 'bodytext', 'content_format'])); }
        return ['content' => $this->articleFields($state['published']), 'translations' => $translations];
    }

    private function articleResponse(array $state): array
    {
        $data = $this->publicRow('articles', $state['published']);
        $data['translation_locales'] = array_keys((array) $this->draftPayload($state)['translations']);
        sort($data['translation_locales']);
        $data['translations'] = (object) $this->draftPayload(['draft' => null] + $state)['translations'];
        $data['draft'] = null === $state['draft'] ? null : $this->draftPayload($state) + ['revision_id' => $state['draft']['revision_id']];
        if (null !== $data['draft']) { $data['draft']['translations'] = (object) $data['draft']['translations']; }
        return ['data' => $data, 'etag' => Resources::etag($state)];
    }

    private function recordDraft(int $id, array $payload, string $sourceEtag, string $action, array $actor): void
    {
        if (count($payload['translations']) > 64 || strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > 1048576) { throw new Problem('draft_too_large', 'Draft exceeds supported content limits.', 413); }
        $revision = $this->revision($id, $payload, $action, $actor);
        $data = ['source_etag' => $sourceEtag, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'revision_id' => $revision];
        Db::query('INSERT INTO cms_api_drafts (article_id, source_etag, payload, revision_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE source_etag = VALUES(source_etag), payload = VALUES(payload), revision_id = VALUES(revision_id)', [$id, $sourceEtag, $data['payload'], $revision]);
        $this->verifyStored(Db::fetchAssoc('SELECT * FROM cms_api_drafts WHERE article_id = ?', [$id]), $data);
    }

    private function revision(int $id, array $payload, string $action, array $actor): int
    {
        $data = ['article_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'action' => $action, 'integration_id' => $actor['integration_id'], 'central_actor' => $actor['central_actor'] ?? null, 'created_at' => time()];
        $revision = Db::insert('cms_api_revisions', $data);
        $this->verifyStored(Db::fetchAssoc('SELECT * FROM cms_api_revisions WHERE revision_id = ?', [$revision]), $data);
        return $revision;
    }

    protected function beginContentTransaction(string $resource, array $extra = []): \mysqli
    {
        $def = Resources::definition($resource);
        return $this->beginOwnedTransaction(array_unique(array_merge([$def['table'], self::AUDIT_TABLE, 'cms_api_changes', 'cms_api_drafts', 'cms_api_revisions', 'cms_articles', 'cms_sites', 'cms_menu', 'cms_sections', 'cms_boxes', 'cms_article_translations', 'cms_site_translations', 'cms_menu_translations'], $extra)));
    }

    protected function row(string $resource, int $id, bool $lock = false): array
    {
        $def = Resources::definition($resource);
        $row = Db::fetchAssoc('SELECT * FROM ' . Db::identifier($def['table']) . ' WHERE ' . Db::identifier($def['id']) . ' = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!is_array($row)) { throw new Problem('not_found', 'Resource not found.', 404); }
        // The skeleton historically spells Description with a capital D.
        return array_change_key_case($row, CASE_LOWER);
    }

    protected function verifyStored(array $stored, array $expected): void
    {
        foreach ($expected as $field => $value) {
            if ('sites' === $field && isset($stored[$field]) && is_string($value)) {
                if (json_decode($stored[$field], true, 32, JSON_THROW_ON_ERROR) === json_decode($value, true, 32, JSON_THROW_ON_ERROR)) { continue; }
            }
            if (!array_key_exists($field, $stored) || $stored[$field] !== $value) {
                throw new Problem('storage_mismatch', 'Storage cannot preserve the submitted content exactly.', 409);
            }
        }
    }

    protected function change(string $resource, int|string $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        \Wbengine\Audit\ChangeLog::record($resource, $id, $action, $before, $after, $actor, 'v2');
        $this->audit($actor['integration_id'], $actor['central_actor'] ?? null, $resource . '.' . $action . ':' . $id, 'allowed');
    }

    private function publicRow(string $resource, array $row): array
    {
        $row = array_change_key_case($row, CASE_LOWER);
        $def = Resources::definition($resource);
        $fields = array_merge([$def['id']], array_keys($def['fields']));
        if ('articles' === $resource) { $fields = array_merge($fields, ['published']); }
        if ('boxes' === $resource) {
            $row['type'] = $this->boxes->typeFor($row['module'], $row['method']);
            $row['settings'] = (object) json_decode($row['api_settings'] ?? '{}', true, 32, JSON_THROW_ON_ERROR);
            $row['sites'] = json_decode($row['sites'] ?? '[]', true, 32, JSON_THROW_ON_ERROR);
            // Read-only, derived from the stored row: none of these is a writable field.
            $row['renders_articles'] = $this->boxes->rendersArticles((string) $row['module'], (string) $row['method']);
            $fields = array_merge($fields, ['module', 'method', 'renders_articles']);
        }
        return array_intersect_key($row, array_fill_keys($fields, true));
    }

    private function storageData(string $resource, array $data, ?array $before = null): array
    {
        if ('boxes' === $resource) {
            $type = $data['type'] ?? (null !== $before ? $this->boxes->typeFor($before['module'], $before['method']) : null);
            if (null === $type) { throw new Problem('invalid_box_type', 'This box type is not registered.'); }
            $sectionId = $data['section_id'] ?? $before['section_id'] ?? null;
            if (null === $sectionId) { throw new Problem('invalid_box_section', 'A registered section is required.'); }
            $section = $this->row('sections', (int) $sectionId, true);
            $this->boxes->assertSection($type, $section['key']);
            $settings = $data['settings'] ?? (null !== $before ? json_decode($before['api_settings'] ?? '{}', true, 32, JSON_THROW_ON_ERROR) : []);
            $data = array_merge($data, $this->boxes->resolve($type, $settings));
            $data['api_settings'] = json_encode((object) $settings, JSON_THROW_ON_ERROR);
            unset($data['type'], $data['settings']);
            if (isset($data['sites'])) { $data['sites'] = json_encode(array_map('strval', $data['sites']), JSON_THROW_ON_ERROR); }
        }
        return $data;
    }

    private function relations(string $resource, array $data, ?int $id = null): void
    {
        if ('pages' === $resource && isset($data['link']) && Db::fetchAssoc('SELECT site_id FROM cms_sites WHERE link = ? AND site_id <> ? LIMIT 1 FOR UPDATE', [$data['link'], $id ?? 0])) { throw new Problem('duplicate_link', 'A page already uses this link.', 409); }
        if ('sections' === $resource && isset($data['key']) && null !== $id) {
            $section = Db::fetchAssoc('SELECT `key` FROM cms_sections WHERE section_id = ? FOR UPDATE', [$id]);
            if (!$section || $section['key'] !== $data['key']) {
                foreach (Db::fetchAllAssoc('SELECT module, method FROM cms_boxes WHERE section_id = ? ORDER BY id FOR UPDATE', [$id]) as $box) {
                    $type = $this->boxes->typeFor($box['module'], $box['method']);
                    if (null === $type) { throw new Problem('invalid_box_section', 'An unregistered box prevents changing this section namespace.'); }
                    $this->boxes->assertSection($type, $data['key']);
                }
            }
        }
        if ('sections' === $resource && isset($data['key']) && Db::fetchAssoc('SELECT section_id FROM cms_sections WHERE `key` = ? AND section_id <> ? LIMIT 1 FOR UPDATE', [$data['key'], $id ?? 0])) { throw new Problem('duplicate_key', 'A section already uses this key.', 409); }
        $references = match ($resource) {
            'articles', 'menu' => ['site_id' => 'pages'],
            'pages' => ['parent_id' => 'pages'],
            'boxes' => ['section_id' => 'sections'],
            default => [],
        };
        if ('menu' === $resource) { $references['parent'] = 'menu'; }
        foreach ($references as $field => $target) {
            if (!empty($data[$field])) {
                if ($resource === $target && $data[$field] === $id) { throw new Problem('invalid_parent', 'An item cannot be its own parent.'); }
                $this->row($target, (int) $data[$field], true);
                // Existing ancestor chains must not cycle back into this item.
                if ($resource === $target && null !== $id) {
                    $parent = $data[$field]; $visited = [];
                    for ($depth = 0; $parent > 0; $depth++) {
                        if ($depth > 100 || $parent === $id || isset($visited[$parent])) { throw new Problem('invalid_parent', 'A parent cycle is not allowed.'); }
                        $visited[$parent] = true;
                        $parent = (int) ($this->row($target, $parent, true)[$field] ?? 0);
                    }
                }
            }
        }
        if (isset($data['sites'])) {
            foreach (json_decode($data['sites'], true, 32, JSON_THROW_ON_ERROR) as $siteId) { $this->row('pages', (int) $siteId, true); }
        }
    }

    private function assertNotReferenced(string $resource, int $id): void
    {
        $references = match ($resource) {
            'pages' => [['cms_sites', 'parent_id'], ['cms_menu', 'site_id'], ['cms_articles', 'site_id']],
            'sections' => [['cms_boxes', 'section_id']],
            'menu' => [['cms_menu', 'parent']],
            default => [],
        };
        foreach ($references as [$table, $field]) {
            if (Db::fetchAssoc('SELECT ' . Db::identifier($field) . ' FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($field) . ' = ? LIMIT 1 FOR UPDATE', [$id])) {
                throw new Problem('resource_in_use', 'Remove dependent resources before deleting this item.', 409);
            }
        }
        if ('pages' === $resource) {
            foreach (Db::fetchAllAssoc('SELECT sites FROM cms_boxes FOR UPDATE') as $box) {
                $sites = json_decode($box['sites'] ?? '[]', true);
                if (is_array($sites) && in_array((string) $id, array_map('strval', $sites), true)) { throw new Problem('resource_in_use', 'A box still references this page.', 409); }
            }
        }
    }
}
