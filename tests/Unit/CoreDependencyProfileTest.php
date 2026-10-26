<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Renderer\Adapter;
use Wbengine\Renderer\Exception\RendererException;

class CoreDependencyProfileTest extends TestCase
{
    public function testCoreDoesNotRequireSmarty(): void
    {
        $manifest = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('smarty/smarty', $manifest['require']);
    }

    public function testMissingCompatibilityAdapterGivesAnActionableError(): void
    {
        $adapter = new Adapter();
        $adapter->setAdapterName('SmartyRenderer');
        $this->expectException(RendererException::class);
        $this->expectExceptionMessage('wbengine/smarty-compat');
        $adapter->fetch('not-used.tpl');
    }

    public function testConfigurationExceptionAutoloadsUnderItsPublicNamespace(): void
    {
        $this->assertTrue(class_exists(\Wbengine\Config\Exception\RuntimeException::class));
        require_once dirname(__DIR__, 2) . '/Config/Exception.php';
        $this->assertInstanceOf(\Exception::class, new \Wbengine\Config\Exception\RuntimeException('test'));
    }

    public function testCoreDoesNotRequireTexy(): void
    {
        $manifest = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('texy/texy', $manifest['require']);
    }

    public function testLegacyFormatterEntryCanExplicitlySelectSafeMarkdown(): void
    {
        $formatter = (new \Wbengine\Formater())->getFormater('markdown', null);
        $this->assertSame("<p><strong>Hello</strong> &lt;script&gt;bad&lt;/script&gt;</p>\n", $formatter->process('**Hello** <script>bad</script>'));
    }

    public function testTexyRuntimeSelectionExplainsOfflineMigration(): void
    {
        $this->expectException(\Wbengine\Exception\RuntimeException::class);
        $this->expectExceptionMessage('wbengine/texy-migration');
        (new \Wbengine\Formater())->getFormater('texy', null);
    }

}
