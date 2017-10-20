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



        /**
         * @param WbengineBoxAbstract $parent
         */
        public function __construct(ComponentParentInterface $parent = null)
        {
            if($parent instanceof ComponentParentInterface) {
                $this->parent = $parent;
            }
        }


        public static function get($path, $function, $callable){
            $route = self::match($path);
            if($route->isRouteMatch() === true){
                if(is_callable($callable)){
                    $callable(self::getRoute()->getStaticBox($function));
//                    return $callable('found');
//                    var_dump(self::getRoute()->createBox($function));
//                    return self::_createBox($function);
                }
            }
//            self::getRoute()->toString();
            return false;
        }


        /**
         * Try to match route by given box url
         *
         * @param string $boxRemainUrl
         * @return $this|null
         */
        public static function match($user_route)
        {
//            $route = new Route($user_route);
//            Utils::dump(self::createRoute($user_route)->compare(htmlspecialchars($_SERVER['REQUEST_URI'])));

            return self::createRoute($user_route)->compare(htmlspecialchars($_SERVER['REQUEST_URI']));

//            $route->setUserRoute($user_route);
//            var_dump($this->getRoute()->getUserRoute());
            // Replace user patern to regular expression...
//            $pattern = preg_replace('/\{[a-z0-9]+\}/','([A-Za-z0-9]+)', $user_route);
//            $pattern = sprintf("/^%s$/", preg_replace('/\//','\/',self::$pattern));
//            $this->pattern = sprintf("/^%s$/", preg_replace('/\//','\/',$pattern));
//            Utils::dump(preg_split('/\{[a-z0-9]+\}/', $route, $params));
//            preg_match_all('/\{[a-z0-9]+\}/', $user_route, $params);
//            $match = preg_match($route->getPattern(), $_SERVER['REQUEST_URI'], $matches);
//            Utils::dump(self::$pattern);
//            Utils::dump($_SERVER['REQUEST_URI']);

//            var_dump($route->match($_SERVER['REQUEST_URI'])->isMatched());

//            array_shift($matches);
//            for($i=0;$i<sizeof($match);$i++){
////                var_dump($i);
//                $p[$params[0][$i]] = $match[$i];
////                Utils::dump($m);
//            }
//            $p = array_combine(
//                array_map(function($value){
//                    return preg_replace('/\{|\}/','', $value);
//                }, $params[0]), $matches
//            );

//            $this->route = new Route();
//            $this->route->pattern = $this->pattern;
//            $this->route->match = $match;
//            $this->route->params = $p;
//            Utils::dump($route->toString());
//            return $this->route;
//            Utils::dump($params);
//            Utils::dump($match);
//            Utils::dump($p);
//            var_dump($pattern);
//            var_dump($x);           return $this->route;
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


        public function getParent(){
            return $this->parent;
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


//        private static function _createBox($constructor){
//            $values = explode('@',$constructor);
//            if(is_array($values)){
//                if(preg_match('/\\\\/', $values[0])){
//                    $namespace = $values[0];
//                }else{
//                    $namespace = "\App\Box\\" . ucfirst($values[0]);
//                }
//
//                if($values[1]){
//                    $method = $values[1];
//                }
//
//            }
////            var_dump(__NAMESPACE__);
//            Utils::dump($method);
////            var_dump(class_exists($namespace));
//
//            if(class_exists($namespace)){
//                if(method_exists($namespace, $method)){
//                    $box = new $namespace(self);
//                    return $box;
//                }
//            }
//            return;
//        }


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
