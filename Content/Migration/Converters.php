<?php
namespace Wbengine\Content\Migration;
final class Converters
{
    public static function for(string $format): Converter
    {
        if ($format === 'html') { return new HtmlConverter(); }
        if ($format !== 'texy') { throw new \InvalidArgumentException('Choose explicit source-format=html or source-format=texy; detection is not supported'); }
        $class = 'Wbengine\\Compat\\TexyMigration\\TexyConverter';
        if (!class_exists($class)) { throw new \RuntimeException('Texy conversion requires the optional compat/texy-migration Composer package. Install it as a path repository and provide its autoloader with --texy-autoload=/absolute/path/vendor/autoload.php. Core HTML migration does not require Texy.'); }
        $converter = new $class();
        if (!$converter instanceof Converter) { throw new \RuntimeException('Optional Texy adapter does not implement the content migration converter interface'); }
        return $converter;
    }
}
