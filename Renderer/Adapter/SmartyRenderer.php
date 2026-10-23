<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 21/11/2018
 * Time: 16:31
 */
namespace Wbengine\Renderer\Adapter;
use Smarty\Smarty;
use Wbengine\Renderer\RendererInterface;

class SmartyRenderer implements RendererInterface
{
    private $smarty = null;

    public function __construct(){
        $this->smarty = New Smarty();

        // The i18n modifiers of the native engine, so templates can use them with Smarty too.
        $this->smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 't', fn ($key, $count = null) => \Wbengine\Template\Runtime::tr($key, $count));
        $this->smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'lurl', fn ($url, $locale = null) => \Wbengine\Template\Runtime::lurl($url, $locale));
        $this->smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'number', fn ($value, $decimals = 0) => \Wbengine\Template\Runtime::number($value, $decimals));
        $this->smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'ldate', fn ($value, $style = 'medium') => \Wbengine\Template\Runtime::ldate($value, $style));
    }

    public function getAdapter(){
        return $this->smarty;
    }

    public function fetch($template, $cache_id = NULL, $compile_id = NULL){
        return $this->getAdapter()->fetch($template, $cache_id, $compile_id);
    }

    public function display($template, $cache_id = NULL, $compile_id = NULL){
        $this->getAdapter()->display($template, $cache_id, $compile_id);
    }

    public function assign($varname, $var = NULL, $nocache = false){
        $this->getAdapter()->assign($varname, $var, $nocache);
    }

    public function setCompileDir($path){
        $this->getAdapter()->setCompileDir((string) $path);
    }

    public function setTemplateDir($path){
        $this->getAdapter()->setTemplateDir((string) $path);
    }

    public function setConfigDir($path){
        $this->getAdapter()->setConfigDir((string) $path);
    }

    public function setCacheDir($path){
        $this->getAdapter()->setCacheDir((string) $path);
    }

    public function registerObject($name, $value){
        // Objects are handles in PHP 8, there is no need to assign by reference.
        $this->getAdapter()->assign($name, $value);
    }

    public function setLeftDelimiter($value){
        $this->getAdapter()->setLeftDelimiter((string) $value);
    }

    public function setRightDelimiter($value){
        $this->getAdapter()->setRightDelimiter((string) $value);
    }

    /**
     * When off, Smarty does not check whether a template changed on every request.
     * Turn it off in production (the compiled cache must then be cleared on deploy).
     */
    public function setCompileCheck($value){
        $this->getAdapter()->setCompileCheck($value ? Smarty::COMPILECHECK_ON : Smarty::COMPILECHECK_OFF);
    }

    public function enableCache(){
        $this->getAdapter()->caching = 1;
    }

    public function disableCache(){
        $this->getAdapter()->caching = 0;
    }

}