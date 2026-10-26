<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\ContentModel;
use Wbengine\Api\V2\Problem;
use Wbengine\Db;

/** Two real connections; touches one unique core row and removes its own bookkeeping. */
class ApiV2ConcurrencyTest extends TestCase
{
    public function testWriterChecksEtagAfterAcquiringTheTargetRowLock(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) { $this->markTestSkipped('pcntl and posix required.'); }
        try { Db::query('SELECT 1'); } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable.'); }
        $created = []; $id = null; $child = null; $sockets = [];
        try {
            $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql') . file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/005-api-content.sql');
            $sql = preg_replace('/^--.*$/m', '', $sql);
            foreach (explode(';', $sql) as $statement) {
                if (preg_match('/^\s*CREATE TABLE IF NOT EXISTS\s+([a-z0-9_]+)/i', $statement, $match)) {
                    $exists = Db::fetchAssoc('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$match[1]]);
                    if (!$exists) { Db::query($statement); $created[] = $match[1]; }
                }
            }
            $model = new ContentModel(null, static function () {});
            $key = 'api_concurrency_' . bin2hex(random_bytes(8));
            $actor = ['integration_id' => 2147483647, 'central_actor' => $key];
            $row = $model->create('sections', ['title' => $key, 'key' => $key], $actor);
            $id = $row['data']['section_id'];
            $connection = Db::getConnection();
            $connection->begin_transaction();
            Db::fetchAssoc('SELECT * FROM cms_sections WHERE section_id = ? FOR UPDATE', [$id]);
            Db::query('UPDATE cms_sections SET title = ? WHERE section_id = ?', ['Winner', $id]);
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $child = pcntl_fork();
            if (0 === $child) {
                fclose($sockets[0]);
                Db::forgetConnection();
                fwrite($sockets[1], "ready\n");
                try {
                    $writer = new class($sockets[1]) extends ContentModel {
                        private $signal;
                        public function __construct($signal) { parent::__construct(null, static function () {}); $this->signal = $signal; }
                        protected function row(string $resource, int $id, bool $lock = false): array
                        {
                            $row = parent::row($resource, $id, $lock);
                            if ('sections' === $resource && $lock) { fwrite($this->signal, "row-locked\n"); fflush($this->signal); }
                            return $row;
                        }
                    };
                    $writer->update('sections', $id, ['title' => 'Lost update'], $row['etag'], $actor);
                    $status = 200;
                } catch (Problem $e) { $status = $e->getCode(); }
                catch (\Throwable $e) { $status = 500; }
                fwrite($sockets[1], json_encode(['status' => $status]) . "\n");
                fflush($sockets[1]);
                posix_kill(getmypid(), SIGKILL);
                exit;
            }
            self::assertGreaterThan(0, $child);
            fclose($sockets[1]); unset($sockets[1]);
            stream_set_timeout($sockets[0], 5);
            self::assertSame("ready\n", fgets($sockets[0]));
            $readable = [$sockets[0]]; $writable = $except = null;
            self::assertSame(0, stream_select($readable, $writable, $except, 0, 150000), 'ETag source must not be read before acquiring the target row lock.');
            self::assertSame(0, pcntl_waitpid($child, $status, WNOHANG), 'Writer should wait for the target row lock.');
            $connection->commit();
            self::assertSame("row-locked\n", fgets($sockets[0]));
            $result = json_decode((string) fgets($sockets[0]), true, 32, JSON_THROW_ON_ERROR);
            pcntl_waitpid($child, $status); $child = null;
            self::assertSame(412, $result['status']);
            self::assertSame('Winner', Db::fetchAssoc('SELECT title FROM cms_sections WHERE section_id = ?', [$id])['title']);
            self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_changes WHERE resource = ? AND resource_id = ?', ['sections', (string) $id])['n']);
        } finally {
            if (null !== $child && $child > 0) { posix_kill($child, SIGKILL); pcntl_waitpid($child, $status); }
            foreach ($sockets as $socket) { if (is_resource($socket)) { fclose($socket); } }
            Db::getConnection()->rollback();
            if (null !== $id) {
                Db::query('DELETE FROM cms_sections WHERE section_id = ?', [$id]);
                Db::query('DELETE FROM cms_api_changes WHERE resource = ? AND resource_id = ?', ['sections', (string) $id]);
                Db::query('DELETE FROM cms_api_audit WHERE central_actor = ?', [$key]);
            }
            foreach (array_reverse($created) as $table) { Db::query('DROP TABLE ' . Db::identifier($table)); }
        }
    }
}
