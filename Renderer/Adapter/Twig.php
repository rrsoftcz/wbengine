<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 21/11/2018
 * Time: 16:31
 */
namespace Wbengine\Renderer\Adapter;
use Wbengine\Renderer;
use Wbengine\Renderer\Adapter;
use Wbengine\Renderer\RendererInterface;

class Twig implements RendererInterface
{
    /**
     * @var null|\Twig_Environment
     */
    private $twig = null;

    public function __construct(Renderer $adapter){
        $loader = new \Twig_Loader_Filesystem($adapter->getRendererTemplatesPath());
        $this->twig = new \Twig_Environment($loader, array(
            'cache' => $adapter->getRendererCacheDir(),
        ));
    }

    public function getAdapter(){
        return $this->twig;
    }

    public function fetch($template, $cache_id = NULL, $compile_id = NULL){

        return $this->getAdapter()->render($template);
//        return $template->render();
//        return $this->getAdapter()->render($template, $cache_id, $compile_id);
    }

    public function display($template, $cache_id = NULL, $compile_id = NULL){
        $this->getAdapter()->display($template, $cache_id, $compile_id);
    }

    public function assignxx($varname, $var = NULL, $scope = NULL){
        $this->getAdapter()->addGlobal('test', 'Hello');
//        $this->getAdapter()->assign($varname, $var, $scope);
    }

    public function setCompileDir($path){
        $this->getAdapter()->compile_dir = (string) $path;
    }

    public function setTemplateDir($path){
        $this->getAdapter()->template_dir = (string) $path;
    }

    public function setConfigDir($path){

    }

    public function setCacheDir($path){
        $this->getAdapter()->cache_dir = (string) $path;
    }

    public function registerObject($name, $value){
        $this->getAdapter()->assignByRef($name, $value);
    }

}