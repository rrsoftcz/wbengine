<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Template\Compiler;
use Wbengine\Template\Exception\TemplateException;

class TemplateUnsupportedTagTest extends TestCase
{
    public static function unsupported(): array
    {
        $tags = ['assign var="x" value=1', 'append var="x" value=1', 'extends file="base.tpl"',
            'block name="body"', '/block', 'capture name="body"', '/capture', 'section name="i" loop=$items',
            '/section', 'sectionelse', 'config_load file="settings.conf"', 'eval var=$code',
            'fetch file="x"', 'insert name="x"', 'php', '/php', 'include_php file="x"',
            'strip', '/strip', 'nocache', '/nocache', 'ldelim', 'rdelim',
            'function name="card"', 'function greeting="Hello" name="card"', '/function', 'call name="card"', 'for $i=0 to 3', '/for',
            'forelse', 'while $flag', 'while true', '/while'];
        return array_combine($tags, array_map(static fn ($tag) => ['{' . $tag . '}'], $tags));
    }

    #[DataProvider('unsupported')]
    public function testUnsupportedTagReportsSourceAndActualLine(string $tag): void
    {
        try {
            (new Compiler())->compile("first\r\n{if true}\r\n  text\r\n" . $tag . '\n{/if}', 'layout/main.tpl');
            $this->fail('Unsupported Smarty syntax was accepted: ' . $tag);
        } catch (TemplateException $e) {
            $this->assertStringContainsString('layout/main.tpl', $e->getMessage());
            $this->assertStringContainsString('line 4:', $e->getMessage());
            $this->assertStringContainsString('Unsupported Smarty tag', $e->getMessage());
        }
    }

    public static function plainText(): array
    {
        return array_map(static fn ($source) => [$source], [
            '<style>.card{block-size:10px;display:block} .x { color: red; }</style>',
            '<script>const x={capture:true,block:"x",function:"fn",while:1};</script>',
            '<script>if (ready) {function named() { return 1; }}</script>',
            '<script>if (ready) {for (let i=0;i<2;i++) { console.log(i); }}</script>',
            '<script>if (ready) {while (ready) { ready=false; }}</script>',
            '<script>{while' . str_repeat(' ', 100) . '(ready) { ready=false; }}</script>',
            '<script>const re={pattern:/block/};</script>',
            '<script>function run() {while ($ready) { $ready = false; }}</script>',
            '{assigned x=1}{blockade}{custom_plugin value="x"}',
        ]);
    }

    #[DataProvider('plainText')]
    public function testNonTemplateBracesStillCompile(string $source): void
    {
        $this->assertStringContainsString($source, (new Compiler())->compile($source));
    }

    public function testCommentsAndLiteralProtectUnsupportedExamples(): void
    {
        $compiled = (new Compiler())->compile('{* {extends file="base.tpl"} *}{literal}{assign var="x"}<script>const x={capture};</script>{/literal}');
        $this->assertStringContainsString('{assign var="x"}', $compiled);
        $this->assertStringNotContainsString('extends file', $compiled);
    }

    public function testLongWhitespaceCannotHideUnsupportedTag(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Unsupported Smarty tag');
        (new Compiler())->compile('{function' . str_repeat(' ', 100) . 'name="x"}');
    }

    public function testOldCompiledTemplateCannotBypassNewDiagnostics(): void
    {
        $directory = sys_get_temp_dir() . '/wb-old-compiler-' . bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $source = $directory . '/old.tpl';
            file_put_contents($source, '{assign var="x" value=1}');
            $old = $directory . '/' . hash('sha256', $source . '|3|1') . '.php';
            file_put_contents($old, '<?php return static function (array &$_v, \Wbengine\Template\Engine $_engine): void { echo "legacy"; };');
            $engine = (new \Wbengine\Template\Engine())->setTemplateDir($directory)->setCompileDir($directory)->setCompileCheck(false);
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessage('Unsupported Smarty tag');
            $engine->fetch('old.tpl');
        } finally {
            foreach (glob($directory . '/*') as $file) { unlink($file); }
            rmdir($directory);
        }
    }

}
