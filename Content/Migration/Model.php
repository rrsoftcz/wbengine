<?php
namespace Wbengine\Content\Migration;

use Wbengine\Db;

/** Parameterized article/translation migration persistence within the existing DB layer. */
final class Model
{
    public function __construct(private readonly string $articles = 'cms_articles', private readonly string $translations = 'cms_article_translations', private readonly string $ledger = 'cms_content_migrations')
    {
        foreach ([$articles, $translations, $ledger] as $table) { Db::identifier($table); }
    }
    private function target(string $entity, int $id, ?string $locale): array
    {
        if ($id < 1) { throw new \InvalidArgumentException('Article id must be positive'); }
        if ($entity === 'article' && $locale === null) { return [Db::identifier($this->articles), '`id` = ?', [$id]]; }
        if ($entity === 'translation' && $locale !== null && preg_match('/^[a-zA-Z]{2,3}(?:_[a-zA-Z0-9]{2,6})?$/D', $locale)) { return [Db::identifier($this->translations), '`article_id` = ? AND BINARY `locale` = BINARY ?', [$id, $locale]]; }
        throw new \InvalidArgumentException('Explicit article or translation identity required');
    }
    public function read(string $entity, int $id, ?string $locale = null, bool $lock = false): array
    {
        [$table, $where, $params] = $this->target($entity, $id, $locale);
        $row = Db::fetchAssoc('SELECT introtext, bodytext, content_format FROM ' . $table . ' WHERE ' . $where . ($lock ? ' FOR UPDATE' : ''), $params);
        if (!is_array($row)) { throw new \RuntimeException('Content record not found'); }
        return $row;
    }
    public function locales(int $id): array
    {
        $locales = array_column(Db::fetchAllAssoc('SELECT locale FROM ' . Db::identifier($this->translations) . ' WHERE article_id = ? ORDER BY locale LIMIT 257', [$id]), 'locale');
        if (count($locales) > 256) { throw new \LengthException('Migration report exceeds 256 translations; request one locale at a time'); }
        return $locales;
    }
    public function write(string $entity, int $id, ?string $locale, array $data): void
    {
        [$table, $where, $params] = $this->target($entity, $id, $locale);
        Db::query('UPDATE ' . $table . ' SET introtext = ?, bodytext = ?, content_format = ? WHERE ' . $where, [$data['introtext'], $data['bodytext'], $data['content_format'], ...$params]);
        if (!hash_equals(self::hash($data), self::hash($this->read($entity, $id, $locale, true)))) {
            throw new \RuntimeException('Database did not preserve the approved content bytes; transaction refused');
        }
    }
    public function backup(string $id, bool $lock = false): ?array
    {
        $row = Db::fetchAssoc('SELECT * FROM ' . Db::identifier($this->ledger) . ' WHERE migration_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        return is_array($row) ? $row : null;
    }
    public function saveBackup(string $id, array $plan): void
    {
        Db::insert($this->ledger, ['migration_id' => $id, 'entity' => $plan['entity'], 'article_id' => $plan['article_id'], 'locale' => $plan['locale'] ?? '', 'source_format' => $plan['source_format'], 'before_data' => self::json($plan['before']), 'after_data' => self::json($plan['after']), 'source_hash' => $plan['source_hash'], 'target_hash' => self::hash($plan['after']), 'created_at' => time()]);
        $stored = $this->backup($id, true);
        if ($stored === null || $stored['before_data'] !== self::json($plan['before']) || $stored['after_data'] !== self::json($plan['after']) || $stored['source_hash'] !== $plan['source_hash'] || $stored['target_hash'] !== self::hash($plan['after']) || $stored['entity'] !== $plan['entity'] || (int) $stored['article_id'] !== $plan['article_id'] || $stored['locale'] !== ($plan['locale'] ?? '')) {
            throw new \RuntimeException('Database did not preserve the complete migration backup; transaction refused');
        }
    }
    public function markRollback(string $id, ?int $time): void { Db::query('UPDATE ' . Db::identifier($this->ledger) . ' SET rolled_back_at = ? WHERE migration_id = ?', [$time, $id]); }
    public function scope(): array
    {
        return ['database' => Db::fetchAssoc('SELECT DATABASE() AS name, @@hostname AS host, @@port AS port'), 'articles' => $this->articles, 'translations' => $this->translations, 'ledger' => $this->ledger];
    }
    /** Detect an outer transaction without committing it or requiring PROCESS privileges. */
    public function begin(): void
    {
        foreach ([$this->articles, $this->translations, $this->ledger] as $table) {
            $ddl = Db::fetchAssoc('SHOW CREATE TABLE ' . Db::identifier($table));
            $create = $ddl['Create Table'] ?? '';
            if (!preg_match('/\bENGINE=InnoDB\b/i', $create)) { throw new \LogicException('Content migration requires InnoDB source, translation and ledger tables'); }
        }
        $connection = Db::getConnection();
        if ((int) Db::fetchAssoc('SELECT @@autocommit AS enabled')['enabled'] !== 1) { throw new \LogicException('Migration requires its own transaction with autocommit enabled'); }
        $probe = 'wb_migration_' . bin2hex(random_bytes(8));
        $connection->query('SAVEPOINT ' . $probe);
        try {
            $connection->query('RELEASE SAVEPOINT ' . $probe);
            throw new \LogicException('Migration cannot run inside an existing transaction');
        } catch (\mysqli_sql_exception $e) {
            // Outside a transaction SAVEPOINT has no lasting effect (MySQL/MariaDB).
            if ($e->getCode() !== 1305) { throw $e; }
        }
        $connection->begin_transaction();
    }
    public function commit(): void { Db::getConnection()->commit(); }
    public function rollback(): void { Db::getConnection()->rollback(); }
    public static function json(array $data): string { return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    public static function hash(array $data): string { return hash('sha256', self::json($data)); }
}
