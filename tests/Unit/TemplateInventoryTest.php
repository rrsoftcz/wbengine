<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;

class TemplateInventoryTest extends TestCase
{
    public function testInventoryDistinguishesCompileSuccessFromCompatibility(): void
    {
        $directory = sys_get_temp_dir() . '/wb-inventory-' . bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            file_put_contents($directory . '/common.tpl', '<p>{$title|escape}</p>');
            file_put_contents($directory . '/smarty.tpl', '{assign var="x" value="y"}{$x}');
            file_put_contents($directory . '/broken.tpl', '{if $flag}unclosed');
            file_put_contents($directory . '/literal.tpl', '{literal}{assign var="x"}{/literal}{* {extends "x"} *}');
            $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/tools/template-inventory.php', $directory],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $errors . $output);
            $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $files = array_column($report['application'], null, 'path');
            $this->assertSame('compiled', $files['common.tpl']['compile']);
            $this->assertSame('review', $files['smarty.tpl']['status']);
            $this->assertSame(['assign'], $files['smarty.tpl']['reviewHints']);
            $this->assertSame('failed', $files['smarty.tpl']['compile']);
            $this->assertStringContainsString('Unsupported Smarty tag', $files['smarty.tpl']['diagnostic']);
            $this->assertSame('failed', $files['broken.tpl']['compile']);
            $this->assertSame([], $files['literal.tpl']['reviewHints']);
            $this->assertSame(hash_file('sha256', $directory . '/common.tpl'), $files['common.tpl']['sha256']);
            $this->assertSame('sprintf', array_column($report['framework'], null, 'path')['Exception/exception.tpl']['consumer']);
        } finally {
            foreach (glob($directory . '/*') as $file) { unlink($file); }
            rmdir($directory);
        }
    }
}
