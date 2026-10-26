<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Db;

class ApiV2HttpTest extends TestCase
{
    public function testPublicBinaryMediaUsesActualMimeAndMetadataGateWithoutExecutingOrCachingDrafts(): void
    {
        try { Db::query('SELECT 1'); } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable.'); }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false); fclose($socket);
        $directory = sys_get_temp_dir() . '/api-http-images-' . bin2hex(random_bytes(8)); mkdir($directory, 0700);
        $environment = array_merge(getenv(), ['WBENGINE_IMAGE_DIR' => $directory]);
        $process = proc_open([PHP_BINARY, '-S', $address, dirname(__DIR__) . '/fixtures/api-v2-http.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes, dirname(__DIR__, 2), $environment);
        self::assertIsResource($process); fclose($pipes[0]);
        try {
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
                if (is_resource($probe)) { fclose($probe); break; } usleep(20000);
            }
            $request = static function (string $path) use ($address): array {
                $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
                $stream = fopen('http://' . $address . $path, 'rb', false, $context);
                $headers = stream_get_meta_data($stream)['wrapper_data'];
                $body = stream_get_contents($stream); fclose($stream);
                return ['body' => $body, 'headers' => $headers];
            };
            $valid = $request('/api/v2/media/' . str_repeat('1', 32));
            self::assertStringContainsString('200', $valid['headers'][0]);
            self::assertContains('Content-Type: image/png', $valid['headers']);
            self::assertContains('X-Content-Type-Options: nosniff', $valid['headers']);
            self::assertContains('Cache-Control: public, max-age=0, must-revalidate', $valid['headers']);
            self::assertContains('Content-Disposition: inline; filename="' . str_repeat('1', 32) . '.png"', $valid['headers']);
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $valid['body']);
            $orphan = $request('/api/v2/media/' . str_repeat('2', 32));
            self::assertStringContainsString('404', $orphan['headers'][0]);
            self::assertSame('not_found', json_decode($orphan['body'], true)['error']['code']);
            $staging = $request('/api/v2/media/.upload-' . str_repeat('4', 32));
            self::assertStringContainsString('404', $staging['headers'][0]);
            $tamperedMime = $request('/api/v2/media/' . str_repeat('3', 32));
            self::assertStringContainsString('503', $tamperedMime['headers'][0]);
            self::assertStringNotContainsString($directory, $tamperedMime['body']);
            $private = $request('/api/v2/articles/1/preview');
            self::assertContains('Cache-Control: private, no-store', $private['headers']);
            self::assertStringNotContainsString('SQL', $private['body']);
        } finally {
            proc_terminate($process); proc_close($process);
            foreach (array_merge(glob($directory . '/*') ?: [], glob($directory . '/.upload-*') ?: []) as $file) { unlink($file); }
            rmdir($directory);
        }
    }
}
