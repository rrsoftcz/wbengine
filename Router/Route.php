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

    namespace Wbengine\Router;
    use Wbengine\Application\Env\Stac\Utils;

    /**
     * Class Route
     *
     * @package Wbengine\Router
     */
    class Route
    {


        /**
         * Created route as stdClass object
         * @var \stdClass
         */
        private $route;


    
        
        
        /**
         * Route constructor.
         * @param string|null $user_route
         */
        public function __construct($user_route = null)
        {
            if($user_route){
                $this->user_route = $user_route;
            }
        }


        /**
         * Class setter ...
         * @param $name
         * @param $value
         */
        public function __set($name, $value)
        {
            $this->route->$name = $value;
        }


        /**
         * Class getter ...
         * @param $name
         * @return mixed
         */
        public function __get($name)
        {
            return $this->route->$name;
        }


        
        /**
         * Set user's route template.
         * @param string $user_route
         */
        public function setUserRoute($user_route){
            $this->user_route = $user_route;
        }



        /**
         * @param mixed $param
         * @throws RouterException
         * @return array|mixed
         */
        public function getParams($param = NULL)
        {
//            preg_match_all('/\{[a-z0-9]+\}/', $this->route->user_route, $this->params);
            return $this->args;
        }



        /**
         * Parse given user route and create regex pattern
         * @return string
         */
        public function getPattern(){
            $this->preg_pattern = preg_replace('/\{[a-z0-9]+\}/','([A-Za-z0-9]+)', $this->getUserRoute());
            $this->pattern = sprintf("~^%s$~", $this->route->preg_pattern);
            return $this->pattern;
        }



        /**
         * Try to match route ...
         * @param $uri
         * @return $this
         */
        public function compare($uri)
        {
            $this->uri = $uri;
            if($this->is_matched = preg_match($this->getPattern(), $uri, $matches)) {
                $this->_parse($matches);
            }
            return $this;
        }



        /**
         * Return is matched state as boolean.
         * @return bool
         */
        public function isMatched(){
            return (boolean) $this->is_matched;
        }



        /**
         * Parse all route params if route matched ...
         * @param $matches
         */
        private function _parse($matches)
        {
            preg_match_all('/\{[a-z0-9]+\}/', $this->getUserRoute(), $params);

            $this->uri = array_shift($matches);
            $this->matches = $matches;
            $this->params = $params[0];
            $this->args = array_combine(
                array_map(function($value){
                    return preg_replace(
                        '/\{|\}/',
                        '',
                        $value
                    );
                }, $this->params), $matches
            );

        }



        /**
         * Return given user route
         * @return string|null
         */
        public function getUserRoute(){
            return $this->route->user_route;
        }



        /**
         * Return Controller's method name
         * @return string|null
         */
        public function getMethod(){
            return $this->methodname;
        }



        /**
         * Return Box controller name
         * @return string|null
         */
        public function getController(){
            return $this->controller;
        }



        /**
         * Dump self object as string
         */
        public function toString(){
            Utils::dump($this);
        }

    }
