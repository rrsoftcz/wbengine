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

    use Wbengine\Box\BoxTemplate;
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
        private $route = NULL;

        /**
         * @var array|null
         */
        private $_routes = NULL;

        /**
         * @var bool
         */
        private $match = FALSE;

        /**
         * @var null|Box\BoxTemplate
         */
        private $box = NULL;

        /**
         * @var null
         */
        private $boxRemainurl = NULL;



        /**
         * @param BoxTemplate $box
         */
        public function __construct(BoxTemplate $box)
        {
            $this->box = $box;
        }



        /**
         * Try to match route by given box url
         *
         * @param string $boxRemainUrl
         * @return $this|null
         */
        public function match($boxRemainUrl = NULL)
        {

            if (!empty($boxRemainUrl))
            {
                $this->boxRemainurl = $boxRemainUrl;
            }

            if ($this->_matchRoute() === FALSE)
            {
                return NULL;
            }

            $this->_setRouteParams();

            return $this->route;
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
         * Return box routes
         *
         * @return array|null
         */
        public function getRoutes()
        {
            if ($this->_routes === NULL)
            {
                $this->_routes = $this->box->getRoutes();
            }

            return $this->_routes;
        }



        /**
         * Return remain parts from url
         *
         * @return null|string
         */
        public function getBoxRemainUrl()
        {
            if ($this->boxRemainurl === NULL)
            {
                $this->boxRemainurl = $this->_getBoxRemainUrl();
            }

            return $this->boxRemainurl;
        }



        /**
         * This function fill parsed params from remain url
         * to stored array for latest use.
         * @use $this->params
         */
        private function _setRouteParams()
        {
            if ($this->route->getRequestedParamsCount() >= 1)
            {

                $paramsRequest = $this->route->getParamsDefinition();

                if (!is_array($paramsRequest))
                {
                    throw New RouterException(__METHOD__
                        . ': Route params definition must be an array.');
                }

                $boxUrlParts = explode("/", $this->getBoxRemainUrl());
                $patterns    = explode("/", $this->route->getPattern());


                foreach (array_keys($this->route->getParamsDefinition()) as $key)
                {
                    $i                   = (int)$paramsRequest[$key];
                    $match               = preg_replace("/^{$patterns[$i]}/", '$1', $boxUrlParts[$i]);
                    $paramsRequest[$key] = $match;
                }

                $this->getRoute()->setParams($paramsRequest);
            }
            else
            {
                $this->getRoute()->setParams(NULL);
            }
        }



        /**
         * This private method loop all routes in array and
         * try match route by given remain url part.
         *
         * @see \Wbengine\Router\Route
         * @return $this|bool
         */
        private function _matchRoute()
        {
            foreach ($this->getRoutes() as $route)
            {
                $this->route = New Route($route);
                if (preg_match(json_encode($this->route->getPattern()), $this->getBoxRemainUrl()))
                {
                    $this->match = TRUE;

                    return $this;
                }
            }

            $this->match = FALSE;

            return FALSE;
        }



        /**
         * @return Route
         * @throws Router\RouterException
         */
        public function getRoute()
        {
            if ($this->route instanceof Route)
            {
                return $this->route;
            }
            else
            {
                throw New RouterException(__METHOD__
                    . ': Instance of object \Wbengine\Router\Route required, but not given.');

            }
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

            if ($this->box instanceof BoxTemplate)
            {
                return $this->box->getBoxRemainUrl();
            }
            else
            {
                throw New RouterException(__METHOD__
                    . ': Stored box object seems to be not instance of BoxTemplate or is null.');
            }
        }


    }
