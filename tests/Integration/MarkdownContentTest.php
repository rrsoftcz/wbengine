<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Box\Article\ArticleModel;
use Wbengine\Db;
use Wbengine\I18n;
use Wbengine\I18n\ContentTranslations;
use Wbengine\I18n\TranslationException;
use Wbengine\I18n\Translator;

/** Connection-local tables isolate migration and mixed-format tests from the shared DB. */
class MarkdownContentTest extends TestCase
{
    private bool $connected = false;
    protected function setUp(): void
    {
        try { @Db::query('SELECT 1'); $this->connected = true; } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable: ' . $e->getMessage()); }
        foreach (['cms_articles', 'cms_article_translations'] as $table) {
            $ddl = Db::fetchAssoc('SHOW CREATE TABLE ' . Db::identifier($table))['Create Table'];
            Db::query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $ddl));
        }
        $this->locale('en');
    }
    protected function tearDown(): void
    {
        if (!$this->connected) { return; }
        Db::query('DROP TEMPORARY TABLE IF EXISTS cms_article_translations');
        Db::query('DROP TEMPORARY TABLE IF EXISTS cms_articles');
        I18n::configure(null, null);
    }
    private function locale(string $locale): void
    {
        I18n::configure((new Translator([], 'en'))->setLocale($locale), ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'pt', 'pt_BR']]);
    }
    private function migrate(): void
    {
        $file = dirname(__DIR__, 2) . '/migrations/4.0.0/003-markdown-content.sql';
        self::assertFileExists($file);
        $sql = preg_replace('/^--[^\n]*(?:\n|$)/m', '', file_get_contents($file));
        foreach (explode(';', $sql) as $statement) { if (trim($statement) !== '') { Db::query($statement); } }
    }
    private function model(): ArticleModel
    {
        $site = new class { public function getSiteid() { return 52; } };
        $parent = new class($site) { public function __construct(private $site) {} public function getSite() { return $this->site; } };
        $box = new class($parent) { public function __construct(private $parent) {} public function getParent() { return $this->parent; } };
        return new ArticleModel($box);
    }
    private function article(array $values = []): int
    {
        return (int) Db::insert('cms_articles', $values + ['site_id' => 52, 'title' => 'Base', 'introtext' => '<p>Base intro</p>', 'bodytext' => '<p>Base body</p>']);
    }

    public function testLegacySchemaRemainsReadableWithOneArticleQuery(): void
    {
        $id = $this->article(); $before = Db::getQueriesCount();
        $row = $this->model()->getArticleRow();
        self::assertSame(1, Db::getQueriesCount() - $before);
        self::assertSame($id, (int) $row->id);
        self::assertSame('<p>Base intro</p>', $row->introtext);
        self::assertFalse(property_exists($row, 'content_format'));
    }

    public function testMigrationPreservesLegacyRowsAndExposesExplicitFormat(): void
    {
        $this->article(); $this->migrate();
        self::assertSame('legacy_html', $this->model()->getArticleRow()->content_format);
        Db::query("UPDATE cms_articles SET content_format = 'markdown', bodytext = '**Body**'");
        $row = $this->model()->getArticleRow();
        self::assertSame('markdown', $row->content_format);
        self::assertSame('**Body**', $row->bodytext);
    }

    public function testMixedTranslationFallbackKeepsFormatOfEachSelectedField(): void
    {
        $this->migrate();
        $id = $this->article(['content_format' => 'markdown', 'introtext' => '**Base intro**']);
        ContentTranslations::set('article', $id, 'pt', ['bodytext' => '<p>Portuguese body</p>', 'content_format' => 'legacy_html']);
        ContentTranslations::set('article', $id, 'pt_BR', ['introtext' => '**Brazil intro**', 'content_format' => 'markdown']);
        $this->locale('pt_BR');
        $row = $this->model()->getArticleRow();
        self::assertSame('**Brazil intro**', $row->introtext);
        self::assertSame('markdown', $row->introtext_format);
        self::assertSame('<p>Portuguese body</p>', $row->bodytext);
        self::assertSame('legacy_html', $row->bodytext_format);
        self::assertSame('markdown', $row->content_format, 'Base row format is not overwritten by translated field metadata');
    }

    public function testLegacyTranslationAndMarkdownBaseKeepDistinctFormats(): void
    {
        $id = $this->article();
        ContentTranslations::set('article', $id, 'pt', ['bodytext' => '<p>Old translated body</p>']);
        $this->migrate();
        Db::query("UPDATE cms_articles SET content_format = 'markdown', introtext = '**Base**'");
        $this->locale('pt');
        $row = $this->model()->getArticleRow();
        self::assertSame('**Base**', $row->introtext);
        self::assertSame('markdown', $row->content_format);
        self::assertSame('legacy_html', $row->bodytext_format);
        self::assertFalse(property_exists($row, 'introtext_format'), 'Fallback inherits base format');
    }

    public function testFormatChangeCannotReinterpretAnOmittedStoredTranslationField(): void
    {
        $this->migrate(); $id = $this->article();
        ContentTranslations::set('article', $id, 'pt', ['introtext' => '<p>Old intro</p>', 'bodytext' => '<p>Old body</p>']);
        try {
            ContentTranslations::set('article', $id, 'pt', ['introtext' => '**New intro**', 'content_format' => 'markdown']);
            self::fail('A format change must not reinterpret an omitted stored body');
        } catch (TranslationException $e) { self::assertStringContainsString('bodytext', $e->getMessage()); }
        $stored = ContentTranslations::all('article', $id)['pt'];
        self::assertSame('legacy_html', $stored['content_format']);
        self::assertSame('<p>Old intro</p>', $stored['introtext']);
        self::assertSame('<p>Old body</p>', $stored['bodytext']);
    }

    public function testFormatChangeCanReplaceOrClearEveryPreviouslyStoredField(): void
    {
        $this->migrate(); $id = $this->article();
        ContentTranslations::set('article', $id, 'pt', ['introtext' => '<p>Old intro</p>', 'bodytext' => '<p>Old body</p>']);
        $stored = ContentTranslations::set('article', $id, 'pt', ['introtext' => '**New intro**', 'bodytext' => null, 'content_format' => 'markdown']);
        self::assertSame('markdown', $stored['content_format']);
        self::assertSame('**New intro**', $stored['introtext']);
        self::assertNull($stored['bodytext']);
    }

    public function testUnchangedFormatWriteIsALegitimateNoOp(): void
    {
        $this->migrate(); $id = $this->article();
        $fields = ['introtext' => '**Intro**', 'bodytext' => '**Body**', 'content_format' => 'markdown'];
        $first = ContentTranslations::set('article', $id, 'pt', $fields);
        self::assertSame($first, ContentTranslations::set('article', $id, 'pt', $fields));
    }

    public function testUnknownTranslationContentFormatIsRejected(): void
    {
        $this->migrate(); $id = $this->article();
        $this->expectException(TranslationException::class);
        $this->expectExceptionMessage('content_format');
        ContentTranslations::set('article', $id, 'pt', ['content_format' => 'html']);
    }
}
