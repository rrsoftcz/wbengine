<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class ConsoleCliTest extends TestCase
{
    private function cli(array $arguments): array
    {
        $process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/bin/wbengine.php'], $arguments), [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $out . $err];
    }
    public function testInstalledComposerDependencyAndBinProxyFindHostAutoloader(): void
    {
        $root = sys_get_temp_dir() . '/wb-installed-console-' . bin2hex(random_bytes(6));
        $package = $root . '/vendor/wbengine/wbengine';
        mkdir($package . '/bin', 0755, true);
        mkdir($package . '/Console');
        mkdir($package . '/Application/Env', 0755, true);
        $source = dirname(__DIR__, 2);
        foreach (['wbengine.php','migrate-content.php'] as $entry) { copy($source . '/bin/' . $entry, $package . '/bin/' . $entry); }
        if (is_file($source . '/Console/Bootstrap.php')) { copy($source . '/Console/Bootstrap.php', $package . '/Console/Bootstrap.php'); }
        copy($source . '/Application/Env/Const.php', $package . '/Application/Env/Const.php');
        $autoload = '<?php require ' . var_export($source . '/vendor/autoload.php', true) . ';';
        file_put_contents($root . '/vendor/autoload.php', $autoload);
        try {
            foreach ([false,true] as $proxy) {
                foreach (['wbengine.php'=>'help','migrate-content.php'=>'--help'] as $entry=>$argument) {
                    $script = $package . '/bin/' . $entry;
                    if ($proxy) {
                        // Custom Composer vendor-dir: only its proxy-provided path can resolve it.
                        $custom = $root . '/custom-autoload.php'; file_put_contents($custom, $autoload);
                        @unlink($root . '/vendor/autoload.php');
                        file_put_contents($root . '/proxy.php', '<?php $_composer_autoload_path = ' . var_export($custom, true) . '; require ' . var_export($script, true) . ';');
                        $script = $root . '/proxy.php';
                    }
                    $process = proc_open([PHP_BINARY, $script, $argument], [1=>['pipe','w'],2=>['pipe','w']], $pipes, '/');
                    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
                    self::assertSame(0, proc_close($process), $output);
                    self::assertStringContainsString($entry === 'wbengine.php' ? 'Exit codes:' : 'Dry-run (default)', $output);
                }
            }
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
            rmdir($root);
        }
    }
    public function testInvalidConfigNeverLeaksItsContents(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wb-console-') . '.json';
        file_put_contents($path, '{"password":"SECRET-DO-NOT-PRINT",BROKEN}');
        try { [$code,$output] = $this->cli(['doctor','--config=' . $path]); self::assertSame(2, $code); self::assertStringNotContainsString('SECRET-DO-NOT-PRINT', $output); }
        finally { unlink($path); unlink(substr($path, 0, -5)); }
    }
    public function testMigrationStatusDoesNotRequireDisabledOptionalFeatures(): void
    {
        [$code,$output] = $this->cli(['migration:status','--config=' . dirname(__DIR__) . '/config/Settings.test.json']);
        self::assertSame(0, $code); $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(5, $report); self::assertNotContains('fail', array_column($report, 'status'));
    }
    public function testUnknownOptionsFailAndHelpExplainsDeployment(): void
    {
        [$code] = $this->cli(['doctor','--password=SECRET']); self::assertSame(2, $code);
        [$code,$output] = $this->cli(['help']); self::assertSame(0, $code); self::assertStringContainsString('--no-dev --optimize-autoloader', $output);
    }
}
