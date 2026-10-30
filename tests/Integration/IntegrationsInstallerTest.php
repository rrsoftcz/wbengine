<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Console\IntegrationsInstaller;
use Wbengine\Db;

final class IntegrationsInstallerTest extends DbTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/wb-install-' . bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/Box/*/*') ?: [] as $file) { unlink($file); }
        foreach (glob($this->root . '/Box/*') ?: [] as $dir) { rmdir($dir); }
        @rmdir($this->root . '/Box');
        @rmdir($this->root);
    }

    private function rows(string $table): int
    {
        return (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM ' . $table)['n'];
    }

    public function testPreviewWritesNothing(): void
    {
        $result = (new IntegrationsInstaller())->install($this->root, 'App', 'central', '/integrations/', false, true);
        $this->assertFalse($result['applied']);
        $this->assertSame(['missing', 'missing', 'missing', 'missing'], array_column($result['actions'], 'status'));
        $this->assertFileDoesNotExist($this->root . '/Box/Central/Integrations.php');
        $this->assertSame(0, $this->rows('cms_boxes'));
    }

    public function testApplyCreatesTheClassSectionPageAndBoxOnceAndNeverChangesThemAgain(): void
    {
        $installer = new IntegrationsInstaller();
        $first = $installer->install($this->root, 'App', 'central', '/integrations/', true, true);
        $this->assertSame(['created', 'created', 'created', 'created'], array_column($first['actions'], 'status'));
        $class = file_get_contents($this->root . '/Box/Central/Integrations.php');
        $this->assertStringContainsString('namespace App\\Box\\Central;', $class);
        $this->assertStringContainsString('extends \\Wbengine\\Box\\Integrations', $class);
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($this->root . '/Box/Central/Integrations.php') . ' 2>&1', $out, $code);
        $this->assertSame(0, $code);

        $site = Db::fetchAssoc("SELECT site_id, visible, acl FROM cms_sites WHERE link = '/integrations/'");
        $box = Db::fetchAssoc("SELECT sites, section_id, static FROM cms_boxes WHERE module = 'integrations' AND method = 'integrations'");
        $this->assertSame(1, (int) $site['visible']);
        $this->assertSame(1, (int) $site['acl']);
        $this->assertSame([(string) $site['site_id']], json_decode($box['sites'], true));
        $this->assertSame(1, (int) $box['static']);

        $second = $installer->install($this->root, 'App', 'central', '/integrations/', true, true);
        $this->assertSame(['exists', 'exists', 'exists', 'exists'], array_column($second['actions'], 'status'));
        $this->assertSame(1, $this->rows('cms_boxes'));
        $this->assertSame(1, $this->rows('cms_sites'));
    }

    public function testReportsWhatStillBlocksIssuingKeys(): void
    {
        $result = (new IntegrationsInstaller())->install($this->root, 'App', 'central', '/integrations/', false, false);
        $this->assertContains('api_disabled', $result['problems']);
    }

    public function testRejectsUnsafeNamesBeforeTouchingAnything(): void
    {
        foreach ([['App; evil', 'central', '/integrations/'], ['App', 'Central', '/integrations/'], ['App', 'central', 'integrations']] as [$namespace, $section, $link]) {
            try {
                (new IntegrationsInstaller())->install($this->root, $namespace, $section, $link, true, true);
                $this->fail('Unsafe input was accepted.');
            } catch (\InvalidArgumentException) {
                $this->assertDirectoryDoesNotExist($this->root . '/Box');
            }
        }
    }
}
