<?php
namespace Wbengine\Compat\TexyMigration;

use Wbengine\Content\Migration\Conversion;
use Wbengine\Content\Migration\Converter;
use Wbengine\Content\Migration\HtmlConverter;

/** Offline bridge only. Websites and the core HTML converter never load Texy. */
final class TexyConverter implements Converter
{
    public function format(): string { return 'texy'; }
    public function version(): string { return 'texy-3-top2-images-root-v1'; }
    public function convert(string $source): Conversion
    {
        if (strlen($source) > 1048576) { throw new \LengthException('Texy migration input size limit exceeded'); }
        if (!class_exists(\Texy\Texy::class)) { throw new \RuntimeException('Install texy/texy in the optional migration package'); }
        $texy = new \Texy\Texy();
        $texy->imageModule->root = '/Images/';
        // No image dimension lookup from the application filesystem or remote assets.
        $texy->imageModule->fileRoot = __DIR__ . '/disabled-assets';
        $texy->headingModule->top = 2;
        $texy->obfuscateEmail = false;
        $html = $texy->process($source);
        $converted = (new HtmlConverter())->convert($html);
        return new Conversion($converted->markdown, hash('sha256', $source), $converted->issues);
    }
}
