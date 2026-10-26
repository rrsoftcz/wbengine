<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Application\Path\File;
use Wbengine\Config;
use Wbengine\Config\Adapter\Exception\ConfigException;

final class ConfigBootstrapTest extends TestCase
{
    private string $directory;
    private mixed $previousConfig;
    private string|false $previousDebug;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/wb-config-bootstrap-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->previousConfig = Config::$config;
        $this->previousDebug = getenv('WBENGINE_DEBUG');
        putenv('WBENGINE_DEBUG');
    }

    protected function tearDown(): void
    {
        Config::$config = $this->previousConfig;
        $this->previousDebug === false ? putenv('WBENGINE_DEBUG') : putenv('WBENGINE_DEBUG=' . $this->previousDebug);
        foreach (glob($this->directory . '/*') ?: [] as $file) { unlink($file); }
        rmdir($this->directory);
    }

    public function testInvalidJsonIsReportedAsActionableConfigException(): void
    {
        $path = $this->directory . '/Settings.json';
        file_put_contents($path, '{"debug":,}');

        try {
            Config::load(new File($path, true));
            self::fail('Expected a configuration parse error.');
        } catch (ConfigException $exception) {
            self::assertStringContainsString('Settings.json', $exception->getMessage());
            self::assertStringContainsString('JSON', $exception->getMessage());
        }
    }

    public function testJsonArrayRootIsRejected(): void
    {
        $path = $this->directory . '/Settings.json';
        file_put_contents($path, '[]');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('JSON object');
        Config::load(new File($path, true));
    }

    public function testDebugCheckHandlesUnloadedArrayAndCanUseEnvironmentFallback(): void
    {
        Config::$config = [];
        self::assertFalse(Config::isDebugEnabled());
        putenv('WBENGINE_DEBUG=1');
        self::assertTrue(Config::isDebugEnabled());
        putenv('WBENGINE_DEBUG=0');
        self::assertFalse(Config::isDebugEnabled());
    }

    public function testStartupExceptionUsesEscapedDebugPageWhenConfigCannotLoad(): void
    {
        $fixture = dirname(__DIR__) . '/fixtures/show-application-exception.php';
        putenv('WBENGINE_DEBUG=1');
        $process = proc_open([PHP_BINARY, $fixture], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
        proc_close($process);

        self::assertStringContainsString('ConfigException', $error);
        self::assertStringNotContainsString('Attempt to read property', $error);
        self::assertStringContainsString('Invalid &lt;settings&gt; JSON', $output);
        self::assertStringNotContainsString('<settings>', $output);
        self::assertStringContainsString('WBengine caught an exception', $output);
    }

    public function testProductionExceptionPageDoesNotExposeDetails(): void
    {
        $fixture = dirname(__DIR__) . '/fixtures/show-runtime-exception.php';
        $process = proc_open([PHP_BINARY, $fixture], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        self::assertStringContainsString('temporarily unavailable', $output);
        self::assertStringContainsString('Reference:', $output);
        self::assertStringNotContainsString('Private runtime detail', $output);
    }

    public function testProductionConfigExceptionExplainsWhatToCheckWithoutExposingDetails(): void
    {
        $fixture = dirname(__DIR__) . '/fixtures/show-application-exception.php';
        putenv('WBENGINE_DEBUG=0');
        $process = proc_open([PHP_BINARY, $fixture], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        self::assertStringContainsString('configuration needs attention', $output);
        self::assertStringContainsString('Reference:', $output);
        self::assertStringNotContainsString('Invalid <settings> JSON', $output);
        self::assertStringNotContainsString('Internal Server Error', $output);
    }
}
