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

    }

    public function setCacheDir($path){
        $this->getAdapter()->setCacheDir((string) $path);
    }

    public function registerObject($name, $value){
        $this->getAdapter()->assign_by_ref($name, $value);
    }

    public function enableCache(){
        $this->getAdapter()->caching = 1;
    }

    public function disableCache(){
        $this->getAdapter()->caching = 0;
    }

}