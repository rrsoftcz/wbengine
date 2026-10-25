<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Api\Model\ApiSectionModel;
use Wbengine\Application\Env\Http;

class RequestInputRoundTripTest extends DbTestCase
{
    private string $dir;
    private array $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = $_POST;
        $this->dir = sys_get_temp_dir() . '/wb-input-' . bin2hex(random_bytes(5));
        mkdir($this->dir . '/tpl', 0700, true);
        mkdir($this->dir . '/compiled', 0700);
        file_put_contents($this->dir . '/tpl/title.tpl', '<h1>{$title}</h1>');
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        foreach (glob($this->dir . '/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->dir . '/*') ?: [] as $dir) {
            rmdir($dir);
        }
        rmdir($this->dir);
    }

    #[DataProvider('rendererAdapters')]
    public function testRawRequestIsStoredExactlyAndEscapedOnlyAtOutput(string $adapter): void
    {
        $value = "O'Brien\\docs <script>alert(\"x\")</script> &amp; text";
        $_POST = ['title' => $value];
        $model = new ApiSectionModel();
        $id = $model->addSection(['title' => Http::Post('title'), 'key' => 'input-roundtrip']);
        $stored = $model->getSectionById($id)['title'];
        $this->assertSame($value, $stored);

        $class = 'Wbengine\\Renderer\\Adapter\\' . $adapter;
        $renderer = new $class();
        $renderer->setTemplateDir($this->dir . '/tpl');
        $renderer->setCompileDir($this->dir . '/compiled');
        $renderer->assign('title', $stored);
        $this->assertSame('<h1>' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>',
            $renderer->fetch('title.tpl'));
    }

    public static function rendererAdapters(): array
    {
        return [['NativeRenderer'], ['SmartyRenderer']];
    }
}
