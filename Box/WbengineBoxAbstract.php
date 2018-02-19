<?php

/**
 * Description of WbengineBoxAbstract
 *
 * @author roza
 */

namespace Wbengine\Box;

use App\App;
use Wbengine\Application\Application;
use Wbengine\Box\Exception;
use Wbengine\Box;
use Wbengine\Box\Exception\BoxException;
use Wbengine\Components\ComponentParentInterface;
use Wbengine\Config;
use Wbengine\Renderer;
use Wbengine\Router\Route;
use Wbengine\Section;
use Wbengine\Session;
use Wbengine\Site;

Abstract class WbengineBoxAbstract implements ComponentParentInterface
{

    /**
     * @var ComponentParentInterface
     */
    public $_parent = null;

    /**
     * @var array
     */
    private $modelCache = null;

    /**
     * @var array
     */
    private $_box;

    /**
     * @var Renderer
     */
    private $_renderer;

    /**
     * @var Site
     */
    private $_site;

    /**
     * @var Exception
     */
    private $_exception = null;

    /**
     * @var array
     */
    private $_routes = null;

    /**
     * @var Route
     */
    public $route;





    /**
     * Return instance of Box object
     * @param \Wbengine\Box $box
     * @internal param $
     */
    public function __construct($box, $parent)
    {
        $this->_box     = $box;
        $this->_parent  = $parent;
    }


    public function __get($name){
           if(isset($this->_box[$name])){
               return $this->_box[$name];
           }else{
               throw new BoxException(sprintf('Value "%s" is not defined.', $name));
           }
    }


    /**
     * Create box's own model object
     * @Return \Wbengine\Model\ModelAbstract
     */
    private function _setModel($namespace)
    {
        $classname = "\\" . trim($namespace) . '\Model';

        $createdModel = new $classname($this);
        $this->modelCache[$this->_clearNamespace($namespace)] = $createdModel;

        return $createdModel;
    }


    /**
     * Clear namespace from slashes
     * @param $namespace
     * @return string
     */
    private function _clearNamespace($namespace){
        return str_replace('\\', '_', trim($namespace));
    }


    public function getModuleName($obj){
        return get_class($obj);
    }

    /**
     * Return instance of selected renderer
     * @return \Wbengine\Renderer
     */
    public function getRenderer()
    {
        if(method_exists($this->_parent,'getRenderer')) {
            return $this->_parent->getRenderer();
        }
        if($this->_renderer instanceof Renderer){
            return $this->_renderer;
        }else{
            $this->_renderer = new Renderer($this);
        }
        return $this->_renderer;
    }


    /**
     * Return instance of class site
     * @return \Wbengine\Site
     */
    public function getSite(){
        return $this->_parent->getSite();
    }



    public function getParent(){
        return $this->_parent;
    }

    public function getBoxUrl(){
        return $this->getSite()->getLink();
    }

    /**
     * Return existing routes stored in file
     * @return array
     */
    public function getRoutes(){
        return $this->_routes;
    }



    /**
     * register routes for given box
     * @param array $routes
     */
    public function setRoutes(array $routes){
        $this->_routes = $routes;
    }



    /**
     * Return section model
     * @param string $namespace
     * @throws BoxException
     * @return \Wbengine\Model\ModelAbstract
     */
    public function getModel($namespace = NULL)
    {
        if (null === $namespace) {
            throw New BoxException(__METHOD__
                . ': Excepts namespace as string, but null given.');
        }
        if (NULL === $this->modelCache[$this->_clearNamespace($namespace)]) {

            return $this->_setModel($namespace);
        } else {
            return $this->modelCache[$this->_clearNamespace($namespace)];
        }
    }

    public function getUserIsLogged(){
        return $this->getSite()->isUserLogged();
    }


    /**
     * Return instance of object Box
     * @return Box|WbengineBoxAbstract
     */
    public function getBox()
    {
        if($this->_box instanceof WbengineBoxAbstract) {
            return $this->_box;
        }
    }


    
    /**
     * Return instance of class Section
     * @return ComponentParentInterface
     */
    public function getSection(){
        return $this->_parent;
    }



    /**
     * Return stored exception
     * @return \Wbengine\Exception\RuntimeException
     */
    public function getException()
    {
        return $this->_exception;
    }



    /**
     * Return BOX's init url
     * @return string
     */
    public function getBoxRemainUrl(){
        return str_replace(rtrim($this->getBoxUrl(),"/"),"",$this->getSite()->getUrl());
    }



    /**
     * Return posted params from url
     * @return array
     */
    public function getSiteParamsFromUrl() {
        return $this->getSite()->getUrlParams();
    }


    /**
     * Return box html template path.
     * @return string
     */
    public function getBoxTemplatePath(){
        return ucfirst($this->getSection()->getKey()) . '/' . ucfirst($this->method);
    }


    public function getSectionPath($name){
        return ucfirst($this->getSection()->getKey().'/'.$name);
    }

    /**
     * Return instance of class Session
     * @return Session
     */
    public function getSession(){
        return $this->getParent()->getSession();
    }

}
