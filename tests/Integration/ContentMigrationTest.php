<?php
namespace Wbengine\Tests\Integration;
use PHPUnit\Framework\TestCase;
use Wbengine\Content\Migration\HtmlConverter;
use Wbengine\Content\Migration\Model;
use Wbengine\Content\Migration\Migrator;
use Wbengine\Db;

class ContentMigrationTest extends TestCase
{
    private bool $connected = false;
    private Migrator $migrator;
    private Model $model;
    protected function setUp(): void
    {
        try { @Db::query('SELECT 1'); $this->connected = true; } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable'); }
        Db::query("CREATE TEMPORARY TABLE wb_md_articles (id INT PRIMARY KEY, introtext TEXT NULL, bodytext TEXT NULL, content_format VARCHAR(20) NOT NULL DEFAULT 'legacy_html') ENGINE=InnoDB");
        Db::query("CREATE TEMPORARY TABLE wb_md_translations (article_id INT, locale VARCHAR(10), introtext TEXT NULL, bodytext TEXT NULL, content_format VARCHAR(20) NOT NULL DEFAULT 'legacy_html', PRIMARY KEY(article_id,locale)) ENGINE=InnoDB");
        self::assertTrue(class_exists(Migrator::class));
        $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/004-content-migration-ledger.sql');
        $sql = preg_replace('/^--[^\n]*(?:\n|$)/m', '', $sql);
        Db::query(str_replace('CREATE TABLE cms_content_migrations', 'CREATE TEMPORARY TABLE wb_md_ledger', trim($sql)));
        $this->model = new Model('wb_md_articles', 'wb_md_translations', 'wb_md_ledger');
        $this->migrator = new Migrator($this->model, new HtmlConverter());
        Db::query('INSERT INTO wb_md_articles (id,introtext,bodytext) VALUES (1,?,?)', ['<p>Intro</p>', '<h2>Body</h2>']);
        Db::query('INSERT INTO wb_md_translations (article_id,locale,introtext,bodytext) VALUES (1,?,?,?)', ['cs', null, '<p>Překlad</p>']);
    }
    protected function tearDown(): void
    {
        if (!$this->connected) { return; }
        foreach (['wb_md_ledger', 'wb_md_translations', 'wb_md_articles'] as $t) { Db::query('DROP TEMPORARY TABLE IF EXISTS ' . $t); }
    }
    public function testDryRunKeepsOriginalsAndIncludesTranslations(): void
    {
        $plans = $this->migrator->planArticle(1, 'html');
        self::assertCount(2, $plans);
        self::assertSame('<p>Intro</p>', $plans[0]['before']['introtext']);
        self::assertTrue($plans[0]['safe']);
        self::assertSame('legacy_html', $this->model->read('article', 1)['content_format']);
        self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM wb_md_ledger')['n']);
    }
    public function testApprovedApplyIsAtomicIdempotentAndBackedUp(): void
    {
        $plan = $this->migrator->plan('article', 1, null, 'html');
        $id = $this->migrator->apply($plan, $plan['approval_hash']);
        self::assertSame($id, $this->migrator->apply($plan, $plan['approval_hash']));
        self::assertSame('markdown', $this->model->read('article', 1)['content_format']);
        $backup = Db::fetchAssoc('SELECT before_data FROM wb_md_ledger WHERE migration_id = ?', [$id]);
        self::assertSame($plan['before'], json_decode($backup['before_data'], true, 512, JSON_THROW_ON_ERROR));
        self::assertTrue($this->migrator->rollback($id));
        self::assertSame($plan['before'], $this->model->read('article', 1));
        self::assertFalse($this->migrator->rollback($id));
    }
    public function testTranslationFallbackNullsSurviveRoundTrip(): void
    {
        $plan = $this->migrator->plan('translation', 1, 'cs', 'html');
        $id = $this->migrator->apply($plan, $plan['approval_hash']);
        self::assertNull($this->model->read('translation', 1, 'cs')['introtext']);
        $this->migrator->rollback($id);
        self::assertSame($plan['before'], $this->model->read('translation', 1, 'cs'));
    }
    public function testStaleSourceRefusesWithoutBackupOrMutation(): void
    {
        $plan = $this->migrator->plan('article', 1, null, 'html');
        Db::query('UPDATE wb_md_articles SET bodytext = ? WHERE id = 1', ['changed']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('changed');
        $this->migrator->apply($plan, $plan['approval_hash']);
    }
    public function testRollbackPreservesSubsequentEdits(): void
    {
        $plan = $this->migrator->plan('article', 1, null, 'html');
        $id = $this->migrator->apply($plan, $plan['approval_hash']);
        Db::query('UPDATE wb_md_articles SET bodytext = ? WHERE id = 1', ['later edit']);
        try { $this->migrator->rollback($id); self::fail('Conflict must refuse rollback'); } catch (\RuntimeException $e) { self::assertStringContainsString('changed', $e->getMessage()); }
        self::assertSame('later edit', $this->model->read('article', 1)['bodytext']);
    }
    public function testManualRepairAndWrongApprovalCannotBeApplied(): void
    {
        Db::query('UPDATE wb_md_articles SET bodytext = ? WHERE id = 1', ['<script>alert(1)</script>']);
        $plan = $this->migrator->plan('article', 1, null, 'html');
        self::assertFalse($plan['safe']);
        $this->expectException(\RuntimeException::class);
        $this->migrator->apply($plan, $plan['approval_hash']);
    }
    public function testNonTransactionalSourceTablesAreRefusedBeforeWriting(): void
    {
        Db::query('ALTER TABLE wb_md_articles ENGINE=MyISAM');
        $plan = $this->migrator->plan('article', 1, null, 'html');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('InnoDB');
        $this->migrator->apply($plan, $plan['approval_hash']);
    }
    public function testApprovalHashCannotBeReplacedWithAnUnreviewedValue(): void
    {
        $plan = $this->migrator->plan('article', 1, null, 'html');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('approval');
        $this->migrator->apply($plan, str_repeat('a', 64));
    }
    public function testRehashedUnsafePlanIsStillIndependentlyRevalidated(): void
    {
        Db::query('UPDATE wb_md_articles SET bodytext = ? WHERE id = 1', ['<script>alert(1)</script>']);
        $plan = $this->migrator->plan('article', 1, null, 'html');
        unset($plan['approval_hash']); $plan['safe'] = true; $plan['issues'] = [];
        $plan['approval_hash'] = Model::hash($plan);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Conversion changed');
        $this->migrator->apply($plan, $plan['approval_hash']);
    }

    public function testFailedContentWriteRollsBackItsBackupAndSourceTogether(): void
    {
        Db::query('UPDATE wb_md_articles SET bodytext = ? WHERE id = 1', ['<p>**********</p>']);
        Db::query('ALTER TABLE wb_md_articles MODIFY bodytext VARCHAR(17)');
        $plan = $this->migrator->plan('article', 1, null, 'html');
        self::assertTrue($plan['safe']);
        try { $this->migrator->apply($plan, $plan['approval_hash']); self::fail('Too-small content column must reject the write'); }
        catch (\Wbengine\Db\Exception\DbException $e) { self::assertSame($plan['before'], $this->model->read('article', 1)); }
        self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM wb_md_ledger')['n']);
    }

    public function testArticleAndTranslationReportHasAnAggregateSizeBudget(): void
    {
        $migrator = new Migrator($this->model, new HtmlConverter(), maxReportBytes: 100);
        $this->expectException(\LengthException::class);
        $migrator->planArticle(1, 'html');
    }

    public function testPermissiveSqlModeCannotCommitATruncatedMigration(): void
    {
        Db::query('UPDATE wb_md_articles SET bodytext = ? WHERE id = 1', ['<p>**********</p>']);
        Db::query('ALTER TABLE wb_md_articles MODIFY bodytext VARCHAR(17)');
        $plan = $this->migrator->plan('article', 1, null, 'html');
        $mode = Db::fetchAssoc('SELECT @@session.sql_mode AS mode')['mode'];
        Db::query("SET SESSION sql_mode = ''");
        try {
            $failure = null;
            try { $this->migrator->apply($plan, $plan['approval_hash']); } catch (\RuntimeException $e) { $failure = $e; }
            self::assertNotNull($failure, 'Silent truncation must not commit');
            self::assertStringContainsString('preserve', $failure->getMessage());
            self::assertSame($plan['before'], $this->model->read('article', 1));
            self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM wb_md_ledger')['n']);
        } finally { Db::query('SET SESSION sql_mode = ?', [$mode]); }
    }

    public function testPermissiveSqlModeCannotCommitATruncatedRollback(): void
    {
        Db::query('UPDATE wb_md_articles SET bodytext = ? WHERE id = 1', ['<p>1234567890</p>']);
        $plan = $this->migrator->plan('article', 1, null, 'html');
        $id = $this->migrator->apply($plan, $plan['approval_hash']);
        Db::query('ALTER TABLE wb_md_articles MODIFY bodytext VARCHAR(12)');
        $mode = Db::fetchAssoc('SELECT @@session.sql_mode AS mode')['mode'];
        Db::query("SET SESSION sql_mode = ''");
        try {
            $failure = null;
            try { $this->migrator->rollback($id); } catch (\RuntimeException $e) { $failure = $e; }
            self::assertNotNull($failure, 'Silent rollback truncation must not commit');
            self::assertStringContainsString('preserve', $failure->getMessage());
            self::assertSame($plan['after'], $this->model->read('article', 1));
            self::assertNull($this->model->backup($id)['rolled_back_at']);
        } finally { Db::query('SET SESSION sql_mode = ?', [$mode]); }
    }

    public function testPermissiveSqlModeCannotCommitATruncatedBackup(): void
    {
        Db::query('ALTER TABLE wb_md_ledger MODIFY before_data VARCHAR(10)');
        $plan = $this->migrator->plan('article', 1, null, 'html');
        $mode = Db::fetchAssoc('SELECT @@session.sql_mode AS mode')['mode'];
        Db::query("SET SESSION sql_mode = ''");
        try {
            $failure = null;
            try { $this->migrator->apply($plan, $plan['approval_hash']); } catch (\RuntimeException $e) { $failure = $e; }
            self::assertNotNull($failure, 'Backup truncation must not commit');
            self::assertStringContainsString('backup', $failure->getMessage());
            self::assertSame($plan['before'], $this->model->read('article', 1));
            self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM wb_md_ledger')['n']);
        } finally { Db::query('SET SESSION sql_mode = ?', [$mode]); }
    }

    public function testApplyCannotCommitAnExistingTransaction(): void
    {
        $plan = $this->migrator->plan('article', 1, null, 'html');
        $connection = Db::getConnection(); $connection->begin_transaction();
        Db::query("UPDATE wb_md_articles SET bodytext = 'outer edit' WHERE id = 1");
        try { $this->migrator->apply($plan, $plan['approval_hash']); self::fail('Nested migration must refuse'); } catch (\LogicException $e) { self::assertStringContainsString('transaction', $e->getMessage()); }
        $connection->rollback();
        self::assertSame('<h2>Body</h2>', $this->model->read('article', 1)['bodytext']);
    }
}
