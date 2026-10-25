<?php

namespace Wbengine\Renderer\Adapter;

use Wbengine\Renderer\RendererInterface;
use Wbengine\Template\Engine;

/**
 * The WBengine template engine as a renderer adapter. Select it with
 * "renderer": { "adapterName": "NativeRenderer" } in Settings.json.
 *
 * Output is HTML-escaped by default. Mark trusted HTML with {$value|raw} (Smarty 5
 * understands |raw and |escape too, so a template written that way works with both
 * adapters). "autoEscape": false keeps the Smarty behaviour of printing as is.
 */
class NativeRenderer implements RendererInterface
{
    private $_engine;

    public function __construct()
    {
        $this->_engine = new Engine();
        // The templates that ship with the framework (login box, article box, error boxes).
        $framework = dirname(__DIR__, 2);
        $this->_engine->addRoot($framework . '/Box')->addRoot($framework . '/Application');
    }

    public function getAdapter()
    {
        return $this->_engine;
    }

    public function fetch($template, $cache_id = null, $compile_id = null)
    {
        return $this->_engine->fetch($template);
    }

    public function display($template, $cache_id = null, $compile_id = null)
    {
        $this->_engine->display($template);
    }

    public function assign($varname, $var = null, $nocache = false)
    {
        $this->_engine->assign($varname, $var);
    }

    public function setCompileDir($path)
    {
        $this->_engine->setCompileDir((string) $path);
    }

    public function setTemplateDir($path)
    {
        $this->_engine->setTemplateDir((string) $path);
    }

    public function setConfigDir($path)
    {
        // Config files are a Smarty feature, the native engine has none.
    }

    public function setCacheDir($path)
    {
        // The fragment cache uses Wbengine\Cache, see {cache}.
    }

    public function registerObject($name, $value)
    {
        $this->_engine->assign($name, $value);
    }

    public function setCompileCheck($value)
    {
        $this->_engine->setCompileCheck($value);
    }

    public function setAutoEscape($value)
    {
        $this->_engine->setAutoEscape($value);
    }

    public function setCacheUserResolver(?callable $resolver)
    {
        $this->_engine->setCacheUserResolver($resolver);
    }

    public function setLeftDelimiter($value)
    {
        throw new \Wbengine\Renderer\Exception\RendererException('The native renderer does not support custom delimiters.');
    }

    public function setRightDelimiter($value)
    {
        throw new \Wbengine\Renderer\Exception\RendererException('The native renderer does not support custom delimiters.');
    }

    public function enableCache()
    {
    }

    public function disableCache()
    {
    }
}
