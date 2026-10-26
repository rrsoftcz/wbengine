<?php
namespace Wbengine;

/** Compatibility entry point. New content uses the built-in Markdown formatter. */
class Formater
{
    private array $formatters = [];

    public function getFormater($formaterName = 'markdown', $path = null)
    {
        if (!is_string($formaterName) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $formaterName)) {
            throw new Exception\RuntimeException('Invalid content formatter name.');
        }
        $name = strtolower($formaterName);
        if ($name === 'texy') {
            throw new Exception\RuntimeException('Texy runtime formatting was removed in WBengine 4.0. Convert trusted existing Texy content offline using wbengine/texy-migration, then select Markdown explicitly.');
        }
        if (!isset($this->formatters[$name])) {
            $class = 'Wbengine\\Formater\\Adapter\\' . ucfirst($name);
            if (!class_exists($class)) { throw new Exception\RuntimeException('Requested content formatter is not installed.'); }
            $this->formatters[$name] = (new $class())->get();
        }
        return $this->formatters[$name];
    }
}
