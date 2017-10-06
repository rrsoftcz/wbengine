<?php

/**
 * Description of BoxTemplate
 *
 * @author roza
 */

namespace Wbengine\Box;

use Wbengine\Box\Exception;
use Wbengine\Box;
use Wbengine\Box\Exception\BoxException;

Abstract class BoxTemplate
{

    /**
     * Object Site
     * @var \Wbengine\Site
     */
    private $site = null;

    /**
     * @var array
     */
    private $modelCache = null;

    /**
     * @var BoxTemplate
     */
    private $_box = null;

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
     * @var Exception
     */
    private $_exception = null;

    /**
     * @var array
     */
    private $_routes = null;


    /**
     * Return instance of Box object
     * @param \Wbengine\Box $box
     * @internal param $
     */
    public function __construct(Box $box)
    {
        $this->site = $box->getSite();
        $this->_box = $box;
    }


    /**
     * Return instance of selected renderer
     * @return \Wbengine\Renderer
     */
    public function getRenderer()
    {
        return $this->site->getRenderer();
    }


    /**
     * Return instance of class site
     * @return \Wbengine\Site
     */
    public function getSite()
    {
        return $this->site;
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
     * @return Box|BoxTemplate
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
