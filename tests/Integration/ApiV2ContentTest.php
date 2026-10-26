<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\BoxRegistry;
use Wbengine\Api\V2\ContentModel;
use Wbengine\Api\V2\Model;
use Wbengine\Api\V2\Problem;
use Wbengine\Db;

class ApiV2ContentTest extends TestCase
{
    private array $tables = [];
    private ContentModel $content;
    private array $actor;
    private string $key;
    private array $flushCounts = [];

    protected function setUp(): void
    {
        try { Db::query('SELECT 1'); } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable.'); }
        $sql = file_get_contents(dirname(__DIR__, 2) . '/tests/schema.sql');
        $sql .= file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql');
        $sql .= file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/005-api-content.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql);
        foreach (explode(';', $sql) as $statement) {
            if ('' === trim($statement)) { continue; }
            Db::query($statement);
            if (preg_match('/CREATE TEMPORARY TABLE\s+`?([a-z0-9_]+)/i', $statement, $match)) { $this->tables[] = $match[1]; }
        }
        Db::query("ALTER TABLE cms_articles ADD content_format VARCHAR(20) NOT NULL DEFAULT 'legacy_html'");
        Db::query("ALTER TABLE cms_article_translations ADD content_format VARCHAR(20) NOT NULL DEFAULT 'legacy_html'");
        Db::insert('cms_users', ['user_id' => 11, 'username' => 'admin', 'role' => 'admin']);
        $issued = (new Model())->provision(11, 'Content test', Model::SCOPES);
        $this->key = $issued['key'];
        $this->actor = ['integration_id' => $issued['integration_id'], 'central_actor' => 'editor-9'];
        $registry = new BoxRegistry();
        $registry->register('story', 'blog', 'story', ['variant' => ['full', 'compact']]);
        $this->content = new ContentModel($registry, function (): void {
            // This SET is rejected while any transaction is active.
            Db::getConnection()->query('SET TRANSACTION READ WRITE');
            $this->flushCounts[] = (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_changes')['n'];
        });
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) { Db::query('DROP TEMPORARY TABLE IF EXISTS `' . $table . '`'); }
    }

    public function testStructureCrudHasStrongEtagRecoveryAuditAndOnlyFlushesAfterCommit(): void
    {
        $created = $this->content->create('pages', ['link' => '/example/', 'title' => "O'Brien"], $this->actor);
        $id = $created['data']['site_id'];
        self::assertSame("O'Brien", $created['data']['title']);
        self::assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/D', $created['etag']);
        $updated = $this->content->update('pages', $id, ['title' => 'New'], $created['etag'], $this->actor);
        self::assertNotSame($created['etag'], $updated['etag']);
        self::assertSame([1, 2], $this->flushCounts);
        $change = Db::fetchAssoc('SELECT * FROM cms_api_changes WHERE action = ?', ['update']);
        self::assertSame("O'Brien", json_decode($change['before_data'], true)['title']);
        self::assertSame('New', json_decode($change['after_data'], true)['title']);
        self::assertSame('editor-9', $change['central_actor']);
        $collection = $this->content->collection('pages', ['per_page' => '1']);
        self::assertSame(1, $collection['pagination']['total']);
        $this->content->delete('pages', $id, $updated['etag'], $this->actor);
        self::assertSame(0, $this->content->collection('pages', [])['pagination']['total']);
        self::assertSame([1, 2, 3], $this->flushCounts);
    }

    public function testExternalRowEditsRejectStaleAndMissingPreconditionsWithoutSideEffects(): void
    {
        $row = $this->content->create('sections', ['title' => 'Central', 'key' => 'central'], $this->actor);
        Db::query('UPDATE cms_sections SET title = ? WHERE section_id = ?', ['External edit', $row['data']['section_id']]);
        foreach ([[$row['etag'], 412], [null, 428]] as [$etag, $status]) {
            try { $this->content->update('sections', $row['data']['section_id'], ['title' => 'Overwrite'], $etag, $this->actor); self::fail('Unsafe update succeeded'); }
            catch (Problem $e) { self::assertSame($status, $e->getCode()); }
        }
        self::assertSame('External edit', Db::fetchAssoc('SELECT title FROM cms_sections')['title']);
        self::assertSame([1], $this->flushCounts);
        self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_changes')['n']);
    }

    public function testAuditFailureRollsBackContentAndDoesNotFlush(): void
    {
        $row = $this->content->create('sections', ['title' => 'Original', 'key' => 'central'], $this->actor);
        Db::query('DROP TEMPORARY TABLE cms_api_audit');
        Db::query('CREATE TEMPORARY TABLE cms_api_audit (unrelated INT) ENGINE=InnoDB');
        $oldLog = ini_set('error_log', '/dev/null');
        try {
            try { $this->content->update('sections', $row['data']['section_id'], ['title' => 'Lost'], $row['etag'], $this->actor); self::fail('Unaudited update succeeded'); }
            catch (\Wbengine\Db\Exception\DbException $e) { self::assertSame('Original', Db::fetchAssoc('SELECT title FROM cms_sections')['title']); }
        } finally { ini_set('error_log', $oldLog); }
        self::assertSame([1], $this->flushCounts);
    }

    public function testBoxWritesResolveRegisteredTypeAndRejectSourceNames(): void
    {
        $section = $this->content->create('sections', ['key' => 'central', 'title' => 'Central'], $this->actor);
        $box = $this->content->create('boxes', ['name' => 'Story', 'type' => 'story', 'section_id' => $section['data']['section_id'], 'settings' => ['variant' => 'full']], $this->actor);
        $stored = Db::fetchAssoc('SELECT * FROM cms_boxes');
        self::assertSame('blog', $stored['module']);
        self::assertSame('story', $stored['method']);
        self::assertSame('story', $box['data']['type']);
        try { $this->content->update('boxes', $stored['id'], ['module' => 'Auth'], $box['etag'], $this->actor); self::fail('Remote PHP selector accepted'); }
        catch (Problem $e) { self::assertSame(400, $e->getCode()); }
    }

    public function testDraftSaveAndRestoreNeverChangePublishedContentAndPublishAtomically(): void
    {
        $site = Db::insert('cms_sites', ['link' => '/draft-publish/']);
        $id = Db::insert('cms_articles', ['site_id' => $site, 'title' => 'Published', 'introtext' => '<p>Original</p>', 'bodytext' => '<p>Body</p>', 'published' => 100]);
        $original = $this->content->read('articles', $id);
        $draft = $this->content->update('articles', $id, ['title' => 'Draft', 'introtext' => '**new**', 'bodytext' => '# New', 'content_format' => 'markdown'], $original['etag'], $this->actor);
        self::assertSame('Published', Db::fetchAssoc('SELECT title FROM cms_articles')['title']);
        self::assertSame([], $this->flushCounts);
        self::assertSame('Draft', $draft['data']['draft']['content']['title']);
        $revision = $draft['data']['draft']['revision_id'];
        $draft2 = $this->content->update('articles', $id, ['title' => 'Later draft'], $draft['etag'], $this->actor);
        $restored = $this->content->restore($id, $revision, $draft2['etag'], $this->actor);
        self::assertSame('Draft', $restored['data']['draft']['content']['title']);
        self::assertNotSame($revision, $restored['data']['draft']['revision_id']);
        $preview = $this->content->preview($id);
        self::assertStringContainsString('<h1>New</h1>', $preview['bodytext']);
        self::assertSame([], $this->flushCounts);
        $published = $this->content->publish($id, $restored['etag'], $this->actor);
        self::assertSame('Draft', Db::fetchAssoc('SELECT title FROM cms_articles')['title']);
        self::assertNull($published['data']['draft']);
        self::assertCount(1, $this->flushCounts);
        self::assertSame(4, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_revisions')['n']);
    }

    public function testNewArticleKeepsContentAndTargetSitePrivateUntilPublish(): void
    {
        $page = $this->content->create('pages', ['title' => 'Page', 'link' => '/story/'], $this->actor);
        $article = $this->content->create('articles', ['site_id' => $page['data']['site_id'], 'title' => 'Secret draft', 'introtext' => '', 'bodytext' => '# Private'], $this->actor);
        $stored = Db::fetchAssoc('SELECT * FROM cms_articles');
        self::assertSame(0, $stored['site_id']);
        self::assertSame('', $stored['bodytext']);
        self::assertSame('# Private', $article['data']['draft']['content']['bodytext']);
        self::assertSame([1], $this->flushCounts);
        $this->content->publish($stored['id'], $article['etag'], $this->actor);
        self::assertSame($page['data']['site_id'], Db::fetchAssoc('SELECT site_id FROM cms_articles')['site_id']);
    }

    public function testPublishRejectsExternalSourceChangeEvenWithFreshAggregateEtag(): void
    {
        $id = Db::insert('cms_articles', ['title' => 'Published', 'introtext' => '', 'bodytext' => 'Old', 'content_format' => 'markdown']);
        $original = $this->content->read('articles', $id);
        $this->content->update('articles', $id, ['bodytext' => 'Draft'], $original['etag'], $this->actor);
        Db::query('UPDATE cms_articles SET bodytext = ? WHERE id = ?', ['External', $id]);
        $fresh = $this->content->read('articles', $id);
        try { $this->content->publish($id, $fresh['etag'], $this->actor); self::fail('Draft overwrote changed source'); }
        catch (Problem $e) { self::assertSame(412, $e->getCode()); }
        self::assertSame('External', Db::fetchAssoc('SELECT bodytext FROM cms_articles')['bodytext']);
    }

    public function testPublishAuditFailurePreservesDraftAndAllPublishedTranslations(): void
    {
        $site = Db::insert('cms_sites', ['link' => '/translation-publish/']);
        $id = Db::insert('cms_articles', ['site_id' => $site, 'title' => 'Published', 'introtext' => '', 'bodytext' => 'Old', 'content_format' => 'markdown']);
        Db::insert('cms_article_translations', ['article_id' => $id, 'locale' => 'cs', 'title' => 'Puvodni', 'content_format' => 'markdown']);
        $original = $this->content->read('articles', $id);
        $draft = $this->content->writeTranslation('article', $id, 'cs', ['title' => 'Novy'], $original['etag'], $this->actor);
        Db::query('DROP TEMPORARY TABLE cms_api_audit');
        Db::query('CREATE TEMPORARY TABLE cms_api_audit (unrelated INT) ENGINE=InnoDB');
        $oldLog = ini_set('error_log', '/dev/null');
        try {
            try { $this->content->publish($id, $draft['etag'], $this->actor); self::fail('Unaudited publication succeeded'); }
            catch (\Wbengine\Db\Exception\DbException $e) { self::assertSame('Puvodni', Db::fetchAssoc('SELECT title FROM cms_article_translations')['title']); }
        } finally { ini_set('error_log', $oldLog); }
        self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_drafts')['n']);
        self::assertSame([], $this->flushCounts);
    }

    public function testImageUploadReadDeleteAuditAndRollbackCleanup(): void
    {
        $directory = sys_get_temp_dir() . '/api-image-integration-' . bin2hex(random_bytes(8));
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
        $model = new \Wbengine\Api\V2\ImageModel(new \Wbengine\Api\V2\ImageStore($directory), static function () {});
        try {
            $upload = $model->upload(['base64' => $png, 'mime' => 'image/png'], $this->actor);
            $id = $upload['data']['image_id'];
            self::assertTrue($upload['data']['public']);
            self::assertSame('/api/v2/media/' . $id, $upload['data']['url']);
            self::assertSame(base64_decode($png), $model->media($id)['bytes']);
            self::assertSame($png, $model->image($id, true)['data']['base64']);
            self::assertArrayNotHasKey('path', $upload['data']);
            $model->deleteImage($id, $upload['etag'], $this->actor);
            self::assertSame([], glob($directory . '/*'));
            Db::query('DROP TEMPORARY TABLE cms_api_audit');
            Db::query('CREATE TEMPORARY TABLE cms_api_audit (unrelated INT) ENGINE=InnoDB');
            $oldLog = ini_set('error_log', '/dev/null');
            try {
                try { $model->upload(['base64' => $png, 'mime' => 'image/png'], $this->actor); self::fail('Unaudited image persisted'); }
                catch (\Wbengine\Db\Exception\DbException $e) { self::assertSame([], glob($directory . '/*')); }
            } finally { ini_set('error_log', $oldLog); }
            self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_images')['n']);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
            if (is_dir($directory)) { rmdir($directory); }
        }
    }

    public function testControllerScopesBodyShapeEtagsRevocationAndPrivatePreviewHeaders(): void
    {
        $directory = sys_get_temp_dir() . '/api-controller-rate-' . bin2hex(random_bytes(8));
        $keyModel = new Model();
        $controller = new \Wbengine\Api\V2(new \Wbengine\Api(), $keyModel, $this->content, new \Wbengine\RateLimit\Limiter($directory));
        try {
            $token = 'Bearer ' . $this->key;
            self::assertSame(403, $controller->handle('GET', '/api/v2/pages', $token, false)['status']);
            self::assertSame(401, $controller->handle('GET', '/api/v2/pages', '', true)['status']);
            $restricted = $keyModel->provision(11, 'Discovery only', ['discovery:read']);
            self::assertSame(403, $controller->handle('POST', '/api/v2/pages', 'Bearer ' . $restricted['key'], true, 'role:admin', '{"title":"x","link":"/x/"}')['status']);
            self::assertSame(400, $controller->handle('POST', '/api/v2/pages', $token, true, null, '[]')['status']);
            $created = $controller->handle('POST', '/api/v2/pages', $token, true, 'editor-9', '{"title":"Page","link":"/page/"}');
            self::assertSame(201, $created['status']);
            $id = $created['body']['data']['site_id'];
            self::assertSame(428, $controller->handle('PATCH', '/api/v2/pages/' . $id, $token, true, null, '{"title":"No etag"}')['status']);
            $updated = $controller->handle('PATCH', '/api/v2/pages/' . $id, $token, true, null, '{"title":"Changed"}', [], $created['headers']['ETag']);
            self::assertSame(200, $updated['status']);
            self::assertSame('Changed', $updated['body']['data']['title']);
            $history = $controller->handle('GET', '/api/v2/pages/' . $id . '/changes', $token, true, 'editor-9');
            self::assertSame(200, $history['status']);
            $change = $history['body']['data'][0]['change_id'];
            self::assertSame(428, $controller->handle('POST', '/api/v2/pages/' . $id . '/restore', $token, true, 'editor-9', json_encode(['change_id' => $change]))['status']);
            $restored = $controller->handle('POST', '/api/v2/pages/' . $id . '/restore', $token, true, 'editor-9', json_encode(['change_id' => $change]), [], $history['headers']['ETag']);
            self::assertSame(200, $restored['status']);
            self::assertSame('Page', $restored['body']['data']['title']);
            self::assertSame(412, $controller->handle('DELETE', '/api/v2/pages/' . $id, $token, true, null, '', [], $updated['headers']['ETag'])['status']);
            $article = $controller->handle('POST', '/api/v2/articles', $token, true, null, '{"title":"Draft","introtext":"","bodytext":"# Preview"}');
            $preview = $controller->handle('GET', '/api/v2/articles/' . $article['body']['data']['id'] . '/preview', $token, true, 'editor-9');
            self::assertSame('private, no-store', $preview['headers']['Cache-Control']);
            self::assertTrue($preview['body']['data']['private']);
            self::assertSame('editor-9', Db::fetchAssoc('SELECT central_actor FROM cms_api_audit WHERE action = ?', ['articles.preview'])['central_actor'] ?? null);
            $keyModel->revoke(11, $this->actor['integration_id']);
            self::assertSame(401, $controller->handle('GET', '/api/v2/pages', $token, true)['status']);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
            if (is_dir($directory)) { rmdir($directory); }
        }
    }

    public function testNoOpDoesNotAppendRecoveryRecordsOrFlushAndMyisamIsRefused(): void
    {
        $row = $this->content->create('sections', ['title' => 'Original', 'key' => 'central'], $this->actor);
        $unchanged = $this->content->update('sections', $row['data']['section_id'], ['title' => 'Original'], $row['etag'], $this->actor);
        self::assertSame($row['etag'], $unchanged['etag']);
        self::assertSame([1], $this->flushCounts);
        self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_changes')['n']);
        Db::query('ALTER TABLE cms_sections ENGINE=MyISAM');
        try { $this->content->update('sections', $row['data']['section_id'], ['title' => 'Unsafe'], $row['etag'], $this->actor); self::fail('Nontransactional write accepted'); }
        catch (Problem $e) { self::assertSame('unsafe_storage', $e->errorCode); }
        self::assertSame('Original', Db::fetchAssoc('SELECT title FROM cms_sections')['title']);
    }

    public function testPublishingCannotSilentlyCollideWithAnotherArticleOnThePage(): void
    {
        $site = Db::insert('cms_sites', ['link' => '/occupied/']);
        Db::insert('cms_articles', ['site_id' => $site, 'title' => 'Already visible', 'introtext' => '', 'bodytext' => 'Visible', 'content_format' => 'markdown']);
        $draft = $this->content->create('articles', ['site_id' => $site, 'title' => 'New private draft', 'introtext' => '', 'bodytext' => 'Private'], $this->actor);
        try { $this->content->publish($draft['data']['id'], $draft['etag'], $this->actor); self::fail('Article collision accepted'); }
        catch (Problem $e) { self::assertSame('article_site_conflict', $e->errorCode); }
        self::assertSame('Already visible', Db::fetchAssoc('SELECT title FROM cms_articles WHERE site_id = ?', [$site])['title']);
        self::assertSame([], $this->flushCounts);
        $list = $this->content->collection('articles', []);
        self::assertTrue($list['data'][1]['has_draft']);
        self::assertSame('New private draft', $list['data'][1]['draft_title']);
    }

    public function testBoxCannotMoveToAnUnregisteredExecutionNamespace(): void
    {
        $central = $this->content->create('sections', ['key' => 'central', 'title' => 'Central'], $this->actor);
        $api = $this->content->create('sections', ['key' => 'api', 'title' => 'Api'], $this->actor);
        $box = $this->content->create('boxes', ['name' => 'Story', 'type' => 'story', 'section_id' => $central['data']['section_id']], $this->actor);
        try { $this->content->update('boxes', $box['data']['id'], ['section_id' => $api['data']['section_id']], $box['etag'], $this->actor); self::fail('Unregistered namespace accepted'); }
        catch (Problem $e) { self::assertSame('invalid_box_section', $e->errorCode); }
        self::assertSame($central['data']['section_id'], Db::fetchAssoc('SELECT section_id FROM cms_boxes')['section_id']);
    }

    public function testStructuralTranslationRoundTripAndMixedArticleFormatGuard(): void
    {
        $page = $this->content->create('pages', ['title' => 'Page', 'link' => '/translate/'], $this->actor);
        $translation = $this->content->readTranslation('site', $page['data']['site_id'], 'cs');
        $saved = $this->content->writeTranslation('site', $page['data']['site_id'], 'cs', ['title' => 'Preklad'], $translation['etag'], $this->actor);
        self::assertSame('Preklad', $saved['data']->title);
        self::assertSame('Page', Db::fetchAssoc('SELECT title FROM cms_sites')['title']);
        $id = Db::insert('cms_articles', ['title' => 'Base', 'introtext' => '**Base**', 'bodytext' => '# Body', 'content_format' => 'markdown']);
        Db::insert('cms_article_translations', ['article_id' => $id, 'locale' => 'cs', 'introtext' => '', 'bodytext' => '<p>Old</p>', 'content_format' => 'legacy_html']);
        $source = $this->content->read('articles', $id);
        try { $this->content->writeTranslation('article', $id, 'cs', ['bodytext' => 'New', 'content_format' => 'markdown'], $source['etag'], $this->actor); self::fail('Mixed format translation accepted'); }
        catch (Problem $e) { self::assertSame('format_replacement_required', $e->errorCode); }
        $preview = $this->content->preview($id, 'cs');
        self::assertStringContainsString('<strong>Base</strong>', $preview['introtext']);
        self::assertSame('&lt;p&gt;Old&lt;/p&gt;', $preview['bodytext']);
        self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_drafts')['n']);
    }

    public function testLegacyArticleAndTranslationOmissionCannotPublishNewHtml(): void
    {
        $site = Db::insert('cms_sites', ['link' => '/legacy-guard/']);
        $id = Db::insert('cms_articles', ['site_id' => $site, 'title' => 'Trusted', 'introtext' => '<p>Intro</p>', 'bodytext' => '<p>Body</p>']);
        Db::insert('cms_article_translations', ['article_id' => $id, 'locale' => 'cs', 'introtext' => '<p>Uvod</p>', 'bodytext' => '<p>Text</p>']);
        $read = $this->content->read('articles', $id);
        foreach ([fn () => $this->content->update('articles', $id, ['bodytext' => '<script>bad()</script>'], $read['etag'], $this->actor), fn () => $this->content->writeTranslation('article', $id, 'cs', ['bodytext' => '<script>bad()</script>'], $read['etag'], $this->actor)] as $work) {
            try { $work(); self::fail('Remote legacy HTML accepted'); }
            catch (Problem $e) { self::assertSame('format_replacement_required', $e->errorCode); }
        }
        self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_drafts')['n']);
        $metadata = $this->content->update('articles', $id, ['title' => 'Safe title'], $read['etag'], $this->actor);
        $this->content->publish($id, $metadata['etag'], $this->actor);
        self::assertSame('<p>Body</p>', Db::fetchAssoc('SELECT bodytext FROM cms_articles')['bodytext']);
        self::assertSame('<p>Text</p>', Db::fetchAssoc('SELECT bodytext FROM cms_article_translations')['bodytext']);
    }

    public function testViewCountersDoNotInvalidateContentEtagsOrPendingPublication(): void
    {
        $site = Db::insert('cms_sites', ['link' => '/counter-guard/']);
        $id = Db::insert('cms_articles', ['site_id' => $site, 'title' => 'Article', 'introtext' => '', 'bodytext' => 'Published', 'content_format' => 'markdown']);
        $initial = $this->content->read('articles', $id);
        Db::query('UPDATE cms_articles SET views = views + 1 WHERE id = ?', [$id]);
        self::assertSame($initial['etag'], $this->content->read('articles', $id)['etag']);
        $draft = $this->content->update('articles', $id, ['bodytext' => 'Draft'], $initial['etag'], $this->actor);
        Db::query('UPDATE cms_articles SET views = views + 3 WHERE id = ?', [$id]);
        self::assertSame($draft['etag'], $this->content->read('articles', $id)['etag']);
        $this->content->publish($id, $draft['etag'], $this->actor);
        self::assertSame('Draft', Db::fetchAssoc('SELECT bodytext FROM cms_articles')['bodytext']);
        self::assertSame(4, Db::fetchAssoc('SELECT views FROM cms_articles')['views']);
        self::assertArrayNotHasKey('views', $initial['data']);
    }

    public function testSectionRenameCannotChangeTheExecutionNamespaceOfAttachedBoxes(): void
    {
        $section = $this->content->create('sections', ['key' => 'central', 'title' => 'Central'], $this->actor);
        $this->content->create('boxes', ['name' => 'Story', 'type' => 'story', 'section_id' => $section['data']['section_id']], $this->actor);
        try { $this->content->update('sections', $section['data']['section_id'], ['key' => 'api'], $section['etag'], $this->actor); self::fail('Section namespace bypass accepted'); }
        catch (Problem $e) { self::assertSame('invalid_box_section', $e->errorCode); }
        self::assertSame('central', Db::fetchAssoc('SELECT `key` FROM cms_sections')['key']);
        $changed = $this->content->update('sections', $section['data']['section_id'], ['title' => 'Renamed title'], $section['etag'], $this->actor);
        self::assertSame('Renamed title', $changed['data']['title']);
    }

    public function testStructuralHistoryRestoresUpdatedAndDeletedRowsWithTranslationsAndNewAudit(): void
    {
        $page = $this->content->create('pages', ['title' => 'Original', 'link' => '/recover/'], $this->actor);
        $id = $page['data']['site_id'];
        Db::insert('cms_site_translations', ['site_id' => $id, 'locale' => 'cs', 'title' => 'Preklad']);
        $updated = $this->content->update('pages', $id, ['title' => 'Changed'], $page['etag'], $this->actor);
        $history = $this->content->history('pages', $id, []);
        $changeId = $history['body']['data'][0]['change_id'];
        $restored = $this->content->restoreStructure('pages', $id, $changeId, $history['etag'], $this->actor);
        self::assertSame('Original', $restored['data']['title']);
        self::assertSame(3, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_changes')['n']);
        try { $this->content->restoreStructure('pages', $id, $changeId, $history['etag'], $this->actor); self::fail('Stale restore accepted'); }
        catch (Problem $e) { self::assertSame(412, $e->getCode()); }
        $this->content->delete('pages', $id, $restored['etag'], $this->actor);
        self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_site_translations')['n']);
        $deletedHistory = $this->content->history('pages', $id, []);
        $deleteId = $deletedHistory['body']['data'][0]['change_id'];
        self::assertSame('delete', $deletedHistory['body']['data'][0]['action']);
        $undeleted = $this->content->restoreStructure('pages', $id, $deleteId, $deletedHistory['etag'], $this->actor);
        self::assertSame($id, $undeleted['data']['site_id']);
        self::assertSame('Original', $undeleted['data']['title']);
        self::assertSame('Preklad', Db::fetchAssoc('SELECT title FROM cms_site_translations')['title']);
        self::assertSame([1, 2, 3, 4, 5], $this->flushCounts);
    }

    public function testStructuralRestoreRejectsUnregisteredBoxSnapshots(): void
    {
        $section = $this->content->create('sections', ['key' => 'central', 'title' => 'Central'], $this->actor);
        $id = Db::insert('cms_boxes', ['section_id' => $section['data']['section_id'], 'name' => 'Legacy executable box', 'module' => 'Auth', 'method' => 'deleteUser']);
        $read = $this->content->read('boxes', $id);
        $this->content->delete('boxes', $id, $read['etag'], $this->actor);
        $history = $this->content->history('boxes', $id, []);
        try { $this->content->restoreStructure('boxes', $id, $history['body']['data'][0]['change_id'], $history['etag'], $this->actor); self::fail('Unregistered executable snapshot restored'); }
        catch (Problem $e) { self::assertSame('invalid_box_type', $e->errorCode); }
        self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_boxes')['n']);
    }

    public function testPreviouslyStoredUnsafeLegacyDraftCannotBypassPublicationValidation(): void
    {
        $site = Db::insert('cms_sites', ['link' => '/old-draft/']);
        $id = Db::insert('cms_articles', ['site_id' => $site, 'title' => 'Trusted', 'introtext' => '<p>Intro</p>', 'bodytext' => '<p>Body</p>']);
        $read = $this->content->read('articles', $id);
        $this->content->update('articles', $id, ['title' => 'Metadata'], $read['etag'], $this->actor);
        $stored = Db::fetchAssoc('SELECT payload FROM cms_api_drafts')['payload'];
        $payload = json_decode($stored, true); $payload['content']['bodytext'] = '<script>bad()</script>';
        Db::query('UPDATE cms_api_drafts SET payload = ? WHERE article_id = ?', [json_encode($payload), $id]);
        $current = $this->content->read('articles', $id);
        try { $this->content->publish($id, $current['etag'], $this->actor); self::fail('Unsafe historical legacy draft published'); }
        catch (Problem $e) { self::assertSame('format_replacement_required', $e->errorCode); }
        self::assertSame('<p>Body</p>', Db::fetchAssoc('SELECT bodytext FROM cms_articles')['bodytext']);
    }

    public function testSectionAndNullableMenuRowsCanBeUndeletedSafely(): void
    {
        $section = $this->content->create('sections', ['title' => 'Central', 'key' => 'central'], $this->actor);
        $id = $section['data']['section_id'];
        $this->content->delete('sections', $id, $section['etag'], $this->actor);
        $history = $this->content->history('sections', $id, []);
        $restored = $this->content->restoreStructure('sections', $id, $history['body']['data'][0]['change_id'], $history['etag'], $this->actor);
        self::assertSame('central', $restored['data']['key']);
        $page = $this->content->create('pages', ['title' => 'Page', 'link' => '/menu-recovery/'], $this->actor);
        $menu = $this->content->create('menu', ['site_id' => $page['data']['site_id'], 'name' => 'Entry'], $this->actor);
        $menuId = $menu['data']['menu_id'];
        $this->content->delete('menu', $menuId, $menu['etag'], $this->actor);
        $history = $this->content->history('menu', $menuId, []);
        $restored = $this->content->restoreStructure('menu', $menuId, $history['body']['data'][0]['change_id'], $history['etag'], $this->actor);
        self::assertNull($restored['data']['type']);
        self::assertNull($restored['data']['order']);
        self::assertNull($restored['data']['description']);
    }

    public function testTranslationRecoveryPreservesParentAndRejectsConcurrentTranslationChanges(): void
    {
        $page = $this->content->create('pages', ['title' => 'English page', 'link' => '/translation-recover/'], $this->actor);
        $id = $page['data']['site_id'];
        $translation = $this->content->readTranslation('site', $id, 'cs');
        $old = $this->content->writeTranslation('site', $id, 'cs', ['title' => 'Old translation'], $translation['etag'], $this->actor);
        $this->content->writeTranslation('site', $id, 'cs', ['title' => 'New translation'], $old['etag'], $this->actor);
        $history = $this->content->history('pages', $id, []);
        $change = $history['body']['data'][0]['change_id'];
        $this->content->restoreStructure('pages', $id, $change, $history['etag'], $this->actor);
        self::assertSame('English page', Db::fetchAssoc('SELECT title FROM cms_sites')['title']);
        self::assertSame('Old translation', Db::fetchAssoc('SELECT title FROM cms_site_translations')['title']);
        $history = $this->content->history('pages', $id, []);
        $translation = $this->content->readTranslation('site', $id, 'cs');
        $this->content->writeTranslation('site', $id, 'cs', ['title' => 'Concurrent translation'], $translation['etag'], $this->actor);
        try { $this->content->restoreStructure('pages', $id, $change, $history['etag'], $this->actor); self::fail('Concurrent translation overwritten by restore'); }
        catch (Problem $e) { self::assertSame(412, $e->getCode()); }
        self::assertSame('Concurrent translation', Db::fetchAssoc('SELECT title FROM cms_site_translations')['title']);
    }

    public function testPermissiveSqlTruncationRollsBackInsteadOfReturningFalseSuccess(): void
    {
        $oldMode = Db::fetchAssoc('SELECT @@SESSION.sql_mode AS mode')['mode'];
        Db::query("SET SESSION sql_mode = ''");
        Db::query('ALTER TABLE cms_sections MODIFY title VARCHAR(3)');
        try {
            try { $this->content->create('sections', ['title' => 'Too long', 'key' => 'central'], $this->actor); self::fail('Silently truncated content accepted'); }
            catch (Problem $e) { self::assertSame('storage_mismatch', $e->errorCode); }
            self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_sections')['n']);
            self::assertSame([], $this->flushCounts);
        } finally { Db::query('SET SESSION sql_mode = ?', [$oldMode]); }
    }
}
