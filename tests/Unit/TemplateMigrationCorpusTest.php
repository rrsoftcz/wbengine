<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Cache;
use Wbengine\Cache\NullCache;
use Wbengine\I18n;
use Wbengine\Renderer\Adapter\NativeRenderer;

class TemplateMigrationCorpusTest extends TestCase
{
    public static function cases(): array
    {
        $file = dirname(__DIR__) . '/fixtures/template-migration/corpus.json';
        // A missing corpus is a failing test, not an empty provider silently passing CI.
        if (!is_file($file)) { return ['missing corpus' => [[]]]; }
        $cases = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $result = [];
        foreach ($cases as $name => $case) {
            foreach ($case['expected'] as $adapter => $expected) {
                if ($adapter !== 'native') { continue; }
                $result[$name . '/' . $adapter] = [$case + ['adapter' => $adapter, 'output' => $expected]];
            }
        }
        return $result;
    }

    #[DataProvider('cases')]
    public function testDocumentedMigrationOutput(array $case): void
    {
        $this->assertNotEmpty($case, 'The versioned migration corpus is missing');
        if (is_array($case['output']) && isset($case['output']['exception'])) {
            $this->expectException(\Wbengine\Template\Exception\TemplateException::class);
            $this->expectExceptionMessage($case['output']['exception']);
            $this->render($case);
            return;
        }
        $this->assertSame($case['output'], $this->render($case));
    }

    private function render(array $case): string
    {
        $directory = sys_get_temp_dir() . '/wb-migration-' . bin2hex(random_bytes(8));
        mkdir($directory);
        Cache::setDriver(new NullCache());
        I18n::configure(null, null);
        try {
            $source = is_array($case['source']) ? $case['source'][$case['adapter']] : $case['source'];
            file_put_contents($directory . '/main.tpl', $source);
            foreach ($case['partials'] ?? [] as $name => $body) { file_put_contents($directory . '/' . $name, $body); }
            $renderer = new NativeRenderer();
            $renderer->setTemplateDir($directory);
            $renderer->setCompileDir($directory . '/compiled');
            $renderer->setAutoEscape($case['autoEscape'] ?? true);
            $renderer->assign($case['vars'] ?? []);
            $renderer->assign('menu', [new class {
                public string $name = 'A & B';
                public string $link = '/a/';
                public function active() { return 'selected'; }
            }]);
            return $renderer->fetch('main.tpl');
        } finally {
            foreach (glob($directory . '/compiled/*') ?: [] as $file) { unlink($file); }
            foreach (glob($directory . '/*') ?: [] as $file) { if (is_file($file)) { unlink($file); } }
            if (is_dir($directory . '/compiled')) { rmdir($directory . '/compiled'); }
            rmdir($directory);
            Cache::setDriver(null);
            I18n::configure(null, null);
        }
    }

    public static function frameworkTemplates(): array
    {
        return [
            'login no captcha' => ['Box/Login/View/Login.tpl', false],
            'login captcha' => ['Box/Login/View/Login.tpl', true],
            'logout' => ['Box/Login/View/Logout.tpl', false],
            'article' => ['Box/Article/View/Article.tpl', false],
            'error' => ['Application/error.tpl', false],
        ];
    }

    #[DataProvider('frameworkTemplates')]
    public function testBundledTemplateHasTheSameEscapedOutput(string $path, bool $captcha): void
    {
        $vars = [
            't' => array_fill_keys(['title', 'username', 'password', 'submit', 'author', 'source', 'info'], 'A & B'),
            'Article' => (object) ['title' => '<script>title</script>', 'author' => 'A & B',
                'source' => '<source>', 'introtext' => '<p>Intro</p>', 'bodytext' => '<p>Body</p>'],
            'formAction' => '/login/?x="', 'logoutAction' => '/login/', 'logoutLabel' => 'Log & out',
            'userName' => '<script>name</script>', 'token' => 'token"&', 'captcha' => $captcha,
            'error' => $path === 'Application/error.tpl' ? ['title' => '<error>', 'code' => '403', 'msg' => 'A & B'] : 'A & B',
        ];
        $case = ['source' => file_get_contents(dirname(__DIR__, 2) . '/' . $path), 'vars' => $vars];
        $native = $this->render($case + ['adapter' => 'native']);
        $this->assertNotSame('', $native);
        $this->assertStringNotContainsString('<script>', $native);
        $this->assertStringContainsString($path === 'Box/Login/View/Logout.tpl' ? 'Log &amp; out' : 'A &amp; B', $native);
        if ($path === 'Box/Article/View/Article.tpl') {
            $this->assertStringContainsString('<p>Intro</p>', $native);
            $this->assertStringContainsString('<p>Body</p>', $native);
        }
    }

}
