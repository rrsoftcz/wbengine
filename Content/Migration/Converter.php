<?php
namespace Wbengine\Content\Migration;
interface Converter
{
    public function format(): string;
    public function version(): string;
    public function convert(string $source): Conversion;
}
