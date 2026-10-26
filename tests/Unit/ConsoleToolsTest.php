<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Console\BoxGenerator;
use Wbengine\Console\Doctor;
use Wbengine\Console\RequestMatrix;

final class ConsoleToolsTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = sys_get_temp_dir() . '/wb-console-' . bin2hex(random_bytes(6)); mkdir($this->root); }
    protected function tearDown(): void {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($this->root);
    }
    public function testPreviewWritesNothingAndUsesExistingTree(): void {
        $files = (new BoxGenerator())->generate($this->root, 'App', 'Main', 'Welcome', 'index');
        self::assertArrayHasKey('Box/Main/Welcome.php', $files);
        self::assertStringContainsString('namespace App\\Box\\Main;', $files['Box/Main/Welcome.php']);
        self::assertStringContainsString('getRenderer()->render', $files['Box/Main/Welcome.php']);
        self::assertArrayHasKey('View/Main/Welcome/Index.tpl', $files);
        self::assertSame([], glob($this->root . '/*'));
    }
    public function testGeneratedSectionKeyMatchesRegistryAndRuntimeNamespace(): void {
        foreach (['central', 'Central', 'CENTRAL'] as $section) {
            $files = (new BoxGenerator())->generate($this->root, 'App', $section, 'Blog', 'story');
            self::assertArrayHasKey('Box/Central/Blog.php', $files);
            $sql = $files['migrations/box-Central-Blog-Story.sql'];
            preg_match("/INSERT INTO cms_sections .*VALUES \('Central', '([^']+)', 1\)/", $sql, $match);
            self::assertSame('central', $match[1] ?? null);
            $registry = new \Wbengine\Api\V2\BoxRegistry();
            $registry->register('story', 'Blog', 'story', [], [$match[1]]);
            $registry->assertSection('story', $match[1]);
            self::assertSame(['module' => 'Blog', 'method' => 'story'], $registry->resolve('story', []));
        }
    }
    public function testSectionKeyCannotExceedRegistryLimit(): void {
        $this->expectException(\InvalidArgumentException::class);
        (new BoxGenerator())->generate($this->root, 'App', str_repeat('a', 46), 'Blog', 'story');
    }
    public function testWriteNeverOverwritesAnyExistingFile(): void {
        $g = new BoxGenerator(); $g->generate($this->root, 'App', 'Main', 'Welcome', 'index', true);
        $path = $this->root . '/Box/Main/Welcome.php'; $before = file_get_contents($path);
        try { $g->generate($this->root, 'App', 'Main', 'Welcome', 'index', true); self::fail('Expected conflict'); }
        catch (\RuntimeException $e) { self::assertSame($before, file_get_contents($path)); }
    }
    public function testTraversalAndReservedIdentifiersRejected(): void {
        foreach (['../Evil', 'X/Y', 'class', 'X;die()', ''] as $module) {
            try { (new BoxGenerator())->generate($this->root, 'App', 'Main', $module, 'index'); self::fail('Accepted invalid identifier'); }
            catch (\InvalidArgumentException $e) { self::assertNotEmpty($e->getMessage()); }
        }
    }
    public function testSymlinkRejectedWithoutCreatingFilesOutsideRoot(): void {
        symlink(sys_get_temp_dir(), $this->root . '/Box');
        $this->expectException(\RuntimeException::class);
        (new BoxGenerator())->generate($this->root, 'App', 'Main', 'Welcome', 'index', true);
    }
    public function testGeneratedBoxRendersThroughExistingParentTree(): void {
        (new BoxGenerator())->generate($this->root, 'ConsoleFixtureApp', 'Main', 'Welcome', 'index', true);
        require $this->root . '/Box/Main/Welcome.php';
        $parent = new class { public function getRenderer() { return new class { public function render($template) { return 'rendered:' . $template; } }; } };
        $box = new \ConsoleFixtureApp\Box\Main\Welcome([], $parent);
        self::assertInstanceOf(\Wbengine\Box\WbengineBoxAbstract::class, $box);
        self::assertSame('rendered:Main/Welcome/Index', $box->Index());
    }
    public function testConflictInLaterTemplateDoesNotLeaveClassBehind(): void {
        mkdir($this->root . '/View/Main/Welcome', 0755, true);
        file_put_contents($this->root . '/View/Main/Welcome/Index.tpl', 'existing');
        try { (new BoxGenerator())->generate($this->root, 'App', 'Main', 'Welcome', 'index', true); self::fail('Expected conflict'); }
        catch (\RuntimeException $e) { self::assertFileDoesNotExist($this->root . '/Box/Main/Welcome.php'); }
    }
    public function testInheritedActionIsRejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        (new BoxGenerator())->generate($this->root, 'App', 'Main', 'Welcome', 'getRenderer');
    }
    public function testDoctorNeverReportsSecretValuesAndOptionalMigrationsDisabled(): void {
        $config = ['debug'=>true, 'jwt'=>['accessKey'=>'SECRET-ACCESS', 'refreshKey'=>'SECRET-REFRESH'], 'dbAdapterDefinition'=>['password'=>'SECRET-PASSWORD'], 'renderer'=>['compileCheck'=>true]];
        $report = (new Doctor())->check($config, $this->root, false);
        $output = json_encode($report);
        self::assertStringNotContainsString('SECRET-', $output);
        self::assertSame('fail', $report['debug']['status']);
        self::assertSame('skip', $report['migration.002']['status']);
        self::assertStringContainsString('false', $report['debug']['action']);
    }
    public function testDoctorUsesRuntimeDefaultCachePathAndNormalizesDriverCase(): void {
        $config = ['cache'=>['enabled'=>true,'driver'=>'FiLe'], 'dbAdapterDefinition'=>['hostname'=>'fixture-' . basename($this->root), 'database'=>'console']];
        $directory = sys_get_temp_dir() . '/wbengine-cache-wb' . substr(hash('sha256', $config['dbAdapterDefinition']['hostname'] . '/console'), 0, 8);
        // Runtime FileCache can create this missing directory in the writable temp parent.
        self::assertDirectoryDoesNotExist($directory);
        $report = (new Doctor())->check($config, $this->root, false);
        self::assertSame('ok', $report['cache.driver']['status']);
        self::assertDirectoryDoesNotExist($directory);
        mkdir($directory);
        try { self::assertSame('ok', (new Doctor())->check($config, $this->root, false)['cache.driver']['status']); }
        finally { rmdir($directory); }
    }
    public function testDoctorFindsProjectVendorBesideApplicationSourceRoot(): void {
        mkdir($this->root . '/src');
        mkdir($this->root . '/vendor/composer', 0700, true);
        file_put_contents($this->root . '/composer.json', '{}');
        file_put_contents($this->root . '/vendor/composer/installed.php', "<?php return ['root'=>['dev'=>false]];");
        file_put_contents($this->root . '/vendor/composer/autoload_classmap.php', "<?php return ['One'=>'a','Two'=>'b'];");
        $report = (new Doctor())->check([], $this->root . '/src', false);
        self::assertSame('ok', $report['composer.production']['status']);
        self::assertSame('ok', $report['composer.optimized']['status']);
        // A real vendor attached to the explicit application root takes precedence.
        mkdir($this->root . '/src/vendor/composer', 0700, true);
        file_put_contents($this->root . '/src/vendor/composer/installed.php', "<?php return ['root'=>['dev'=>true]];");
        self::assertSame('fail', (new Doctor())->check([], $this->root . '/src', false)['composer.production']['status']);
    }
    public function testBenchmarkQuantilesAndMissingInstrumentationAreHonest(): void {
        $report = RequestMatrix::summarize([['ms'=>10,'status'=>200], ['ms'=>20,'status'=>503], ['ms'=>30,'status'=>200]], 0.1);
        self::assertSame(20.0, $report['p50_ms']); self::assertSame(30.0, $report['p95_ms']);
        self::assertSame(30.0, $report['requests_per_second']); self::assertSame(1, $report['errors']);
        self::assertNull($report['server_memory_bytes']); self::assertNull($report['sql_queries']);
    }
}
