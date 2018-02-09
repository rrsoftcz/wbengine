<?php

    /**
     * $Id: Doors.php 85 2010-07-07 17:42:43Z bajt $
     * ----------------------------------------------
     * Class Box Central WoW sub module class.
     *
     * @package RRsoft-CMS
     * @version $Rev: 30 $
     * @copyright (c) 2012 RRsoft www.rrsoft.cz
     * @license GNU Public License
     *
     * Minimum Requirement: PHP 5.3.x
     */

    namespace Wbengine;

    use Wbengine\Application\Env\Http;
    use Wbengine\Application\Env\Stac\Utils;
    use Wbengine\Box\WbengineBoxAbstract;
    use Wbengine\Components\ComponentParentInterface;
    use Wbengine\Router\Route;
    use Wbengine\Router\RouterException;

    /**
     * Class Router
     *
     * @package Wbengine
     */
    class Router
    {

        /**
         * @var \Wbengine\Router\Route
         */
        private static $route = NULL;

        /**
         * @var array|null
         */
        private $_routes = NULL;

        /**
         * @var bool
         */
        private $match = FALSE;

        /**
         * @var null|Box\WbengineBoxAbstract
         */
        private $box = NULL;

        /**
         * @var null
         */
        private $boxRemainurl = NULL;

        private $parent;
        private $pattern;
        private $params;




        public static function get($path, $callable){
            if(Http::getRequestType() !== Http::TYPE_GET) return;

            $route = self::match($path);
            if($route->isRouteMatch() === true){
                if(is_callable($callable)){
                    return $callable(self::getRoute());
                }
            }
        }

        public static function post($path, $function, $callable){
            if(Http::getRequestType() !== Http::TYPE_POST) return;

            $route = self::match($path);
            if($route->isRouteMatch() === true){
                if(is_callable($callable)){
                    return $callable(self::getRoute()->getParams());
                }
            }
            return false;
        }


        /**
         * Try to match route by given box url
         *
         * @param string $boxRemainUrl
         * @return $this|null
         */
        public static function match($user_route){
            return self::createRoute($user_route)->compare(htmlspecialchars($_SERVER['REQUEST_URI']));
        }



        /**
         * Return parsed params defined in route
         *
         * @param mixed $param
         * @return array
         * @throws Router\RouterException
         */
        public function getParams($param = NULL)
        {
            if ($this->isRouteMatch())
            {

                return $this->route->getParams($param);
            }
            else
            {
                return NULL;
            }
        }



        /**
         * @return Route
         * @throws Router\RouterException
         */
        public static function createRoute($user_route){
            return self::$route = new Route($user_route);
        }

        public static function getRoute(){
            return self::$route;
        }


        /**
         * Return route match state.
         *
         * @return bool
         */
        public function isRouteMatch()
        {
            return $this->match;
        }


        /**
         * Return remain part url from given box.
         *
         * @throws Router\RouterException
         * @return string
         */
        private function _getBoxRemainUrl()
        {

            if ($this->box instanceof WbengineBoxAbstract)
            {
                return $this->box->getBoxRemainUrl();
            }
            else
            {
                throw New RouterException(__METHOD__
                    . ': Stored box object seems to be not instance of WbengineBoxAbstract or is null.');
            }
        }


    }
