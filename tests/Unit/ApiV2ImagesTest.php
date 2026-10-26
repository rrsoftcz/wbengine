<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\ImageStore;
use Wbengine\Api\V2\Problem;

class ApiV2ImagesTest extends TestCase
{
    private string $dir;
    private string $png;
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/api-v2-images-' . bin2hex(random_bytes(8));
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    }
    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) { if (is_link($file) || is_file($file)) { unlink($file); } }
        if (is_dir($this->dir)) { rmdir($this->dir); }
    }
    public function testStorageUsesOpaqueIdsAndRejectsPathsWithoutTouchingOtherFiles(): void
    {
        $store = new ImageStore($this->dir);
        $image = $store->put(base64_encode($this->png), 'image/png');
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $image['image_id']);
        self::assertSame($this->png, $store->read($image['image_id']));
        self::assertSame(hash('sha256', $this->png), $image['sha256']);
        foreach (['../secret', '/etc/passwd', 'file:///etc/passwd', 'https://evil.example/image.png'] as $id) {
            try { $store->read($id); self::fail('Path accepted'); }
            catch (Problem $e) { self::assertSame(400, $e->getCode()); }
        }
    }
    public function testMimeMagicSizeAndExecutablePayloadsAreRejected(): void
    {
        $store = new ImageStore($this->dir);
        foreach ([['%%%invalid', 'image/png'], [base64_encode('<svg onload="alert(1)"/>'), 'image/svg+xml'], [base64_encode('<?php phpinfo();'), 'image/png'], [base64_encode($this->png . '<?php echo 1;'), 'image/png'], [base64_encode($this->png), 'image/jpeg'], [base64_encode(str_repeat('a', 524289)), 'image/png']] as [$data, $mime]) {
            try { $store->put($data, $mime); self::fail('Invalid image accepted'); }
            catch (Problem $e) { self::assertContains($e->getCode(), [400, 413, 415]); }
        }
        self::assertSame([], glob($this->dir . '/*') ?: []);
    }
    public function testStorageInsideDocumentRootAndSymlinkedFilesAreRefused(): void
    {
        $saved = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir();
        try {
            try { (new ImageStore($this->dir))->put(base64_encode($this->png), 'image/png'); self::fail('Executable webroot storage accepted'); }
            catch (Problem $e) { self::assertSame('unsafe_image_storage', $e->errorCode); }
        } finally {
            if (null === $saved) { unset($_SERVER['DOCUMENT_ROOT']); } else { $_SERVER['DOCUMENT_ROOT'] = $saved; }
        }
        $store = new ImageStore($this->dir);
        $image = $store->put(base64_encode($this->png), 'image/png');
        unlink($this->dir . '/' . $image['image_id']);
        symlink('/etc/passwd', $this->dir . '/' . $image['image_id']);
        try { $store->read($image['image_id']); self::fail('Symlink followed'); }
        catch (Problem $e) { self::assertSame(404, $e->getCode()); }
    }
}
