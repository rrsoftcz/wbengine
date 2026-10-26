<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Db;
use Wbengine\I18n;
use Wbengine\I18n\ContentTranslations;
use Wbengine\I18n\Translator;

class MarkdownContentConcurrencyTest extends TestCase
{
    public static function startingRows(): array { return ['existing translation' => [true], 'concurrent insert' => [false]]; }

    #[DataProvider('startingRows')]
    public function testPartialWriterCannotRestoreOldFormatAfterAnotherWriterSwitchesIt(bool $existing): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) { $this->markTestSkipped('Requires process isolation'); }
        try { @Db::query('SELECT 1'); } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable'); }
        $added = !Db::fetchAssoc("SHOW COLUMNS FROM cms_article_translations LIKE 'content_format'");
        $id = null; $pid = null; $sockets = [];
        $connection = Db::getConnection();
        try {
            if ($added) { Db::query("ALTER TABLE cms_article_translations ADD COLUMN content_format ENUM('legacy_html','markdown') NOT NULL DEFAULT 'legacy_html'"); }
            I18n::configure((new Translator([], 'en'))->setLocale('cs'), ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs']]);
            $id = (int) Db::insert('cms_articles', ['site_id' => 999999, 'title' => 'Concurrency fixture']);
            if ($existing) { ContentTranslations::set('article', $id, 'cs', ['introtext' => '<p>old intro</p>', 'bodytext' => '<p>old body</p>', 'content_format' => 'legacy_html']); }
            $connection->begin_transaction();
            Db::fetchAssoc("SELECT * FROM cms_article_translations WHERE article_id = ? AND locale = 'cs' FOR UPDATE", [$id]);
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            if ($pid === -1) { throw new \RuntimeException('Cannot fork concurrency test'); }
            if ($pid === 0) {
                fclose($sockets[0]); Db::forgetConnection(); $child = Db::getConnection();
                fwrite($sockets[1], json_encode(['thread' => $child->thread_id]) . "\n"); fflush($sockets[1]);
                try {
                    ContentTranslations::set('article', $id, 'cs', ['introtext' => '<p>partial old format</p>', 'content_format' => 'legacy_html']);
                    $result = ['status' => 'written'];
                } catch (\Throwable $e) { $result = ['status' => 'rejected', 'message' => $e->getMessage()]; }
                fwrite($sockets[1], json_encode($result) . "\n"); fflush($sockets[1]); fclose($sockets[1]); $child->close();
                posix_kill(getmypid(), SIGKILL); exit(1);
            }
            fclose($sockets[1]); unset($sockets[1]); stream_set_timeout($sockets[0], 10);
            $thread = json_decode((string) fgets($sockets[0]), true, 512, JSON_THROW_ON_ERROR)['thread'];
            $blocked = false;
            for ($attempt = 0; $attempt < 200; $attempt++) {
                foreach (Db::fetchAllAssoc('SHOW FULL PROCESSLIST') as $process) {
                    if ((int) $process['Id'] === $thread && str_starts_with((string) $process['Info'], 'INSERT IGNORE INTO `cms_article_translations`')) { $blocked = true; break 2; }
                }
                usleep(10000);
            }
            self::assertTrue($blocked, 'Partial writer must read the old format before blocking behind the second connection');
            if ($existing) {
                Db::query("UPDATE cms_article_translations SET introtext = ?, bodytext = ?, content_format = 'markdown' WHERE article_id = ? AND locale = 'cs'", ['**new intro**', '**new body**', $id]);
            } else {
                Db::insert('cms_article_translations', ['article_id' => $id, 'locale' => 'cs', 'introtext' => '**new intro**', 'bodytext' => '**new body**', 'content_format' => 'markdown']);
            }
            $connection->commit();
            $result = json_decode((string) fgets($sockets[0]), true, 512, JSON_THROW_ON_ERROR);
            pcntl_waitpid($pid, $status); $pid = null;
            self::assertSame('rejected', $result['status'], 'A stale partial write must not restore the old format');
            self::assertStringContainsString('concurrent', $result['message']);
            $stored = ContentTranslations::all('article', $id)['cs'];
            self::assertSame('markdown', $stored['content_format']);
            self::assertSame('**new intro**', $stored['introtext']);
            self::assertSame('**new body**', $stored['bodytext']);
        } finally {
            $connection->rollback();
            if ($pid !== null && $pid > 0) { posix_kill($pid, SIGKILL); pcntl_waitpid($pid, $status); }
            foreach ($sockets as $socket) { if (is_resource($socket)) { fclose($socket); } }
            if ($id !== null) { Db::query('DELETE FROM cms_article_translations WHERE article_id = ?', [$id]); Db::query('DELETE FROM cms_articles WHERE id = ?', [$id]); }
            if ($added) { Db::query('ALTER TABLE cms_article_translations DROP COLUMN content_format'); }
            I18n::configure(null, null);
        }
    }
}
