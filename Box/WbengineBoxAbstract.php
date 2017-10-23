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
use Wbengine\Site;

Abstract class WbengineBoxAbstract implements ComponentParentInterface
{

    /**
     * Object Site
     * @var \Wbengine\Site
     */
    private $_parent = null;

    /**
     * @var array
     */
    private $modelCache = null;

    /**
     * @var WbengineBoxAbstract
     */
    private $_box = null;
    private $_renderer;
    private $_site;

    /**
     * @var Exception
     */
    private $_exception = null;

    /**
     * @var array
     */
    private $_routes = null;

    public $route;

    /**
     * Return instance of Box object
     * @param \Wbengine\Box $box
     * @internal param $
     */
    public function __construct($parent)
    {
        if($parent instanceof Application) {
            $this->_parent = $parent;
        }elseif ($parent instanceof Route){
            $this->route = $parent;
        }
    }



    /**
     * Create box's own model object
     * @Return \Wbengine\Model\ModelAbstract
     */
    private function _setModel($namespace)
    {
        $classname = "\\" . trim($namespace) . '\Model';

        $createdModel = New $classname($this);
        $this->modelCache[$this->_clearNamespace($namespace)] = $createdModel;

        return $createdModel;
    }


    /**
     * Clear namespace from slashes
     * @param $namespace
     * @return string
     */
    private function _clearNamespace($namespace)
    {
        return str_replace('\\', '_', trim($namespace));
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
    public function getSite()
    {
        if($this->_site instanceof Site){
            return $this->_site;
        }else{
            $this->_site =  new Site($this);
        }

        return $this->_site;
    }


    public function getParent(){
        return $this->getParent();
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
                . ': excepts argument namespace as string but null given.');
        }
        if (NULL === $this->modelCache[$this->_clearNamespace($namespace)]) {

            return $this->_setModel($namespace);
        } else {
            return $this->modelCache[$this->_clearNamespace($namespace)];
        }
    }


    /**
     * Return instance of object Box
     * @return Box|WbengineBoxAbstract
     */
    public function getBox()
    {
        return $this->_box;
    }


    /**
     * Evoke new exception with given message and error code.
     * @param string $message
     * @param int $code
     * @throws RuntimeException
     */
    public function createException($message = NULL, $code = NULL){
        $this->_exception = New BoxException($message, $code);
    }


    public function getBoxUrl(){
        return $this->getSite()->getLink();
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

    public function getBoxTemplatePath(){
        return ucfirst($this->getBox()->getSection()->getKey()) . '/' . ucfirst($this->getBox()->getMethodName());
    }

}
