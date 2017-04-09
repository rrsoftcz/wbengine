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

    /**
     * Class Route
     *
     * @package Wbengine\Router
     */
    class Route
    {

        /**
         * @var array|null
         */
        private $route = NULL;

        /**
         * @var null
         */
        private $options = NULL;

        /**
         * @var null
         */
        private $pattern = NULL;

        /**
         * @var
         */
        private $params;



        /**
         * @param array $route
         */
        public function __construct(array $route)
        {
            $this->route   = $route;
            $this->pattern = $route[0];
            $this->options = $route[1];
        }


        /**
         * @internal param array $params
         * @param $params
         */
        public function setParams($params)
        {
            $this->params = $params;
        }



        /**
         * @param mixed $param
         * @throws RouterException
         * @return array|mixed
         */
        public function getParams($param = NULL)
        {
            if (!empty($param))
            {
                if (array_key_exists($param, $this->params))
                {
                    return $this->params[$param];
                } else
                {
                    throw New RouterException(__METHOD__
                        . ': Requested Route parameter [' . $param . '] does not exist.');
                }
            }

            return $this->params;
        }



        /**
         * @return null
         */
        public function getOptions()
        {
            return $this->options;
        }



        /**
         * @return null
         */
        public function getPattern()
        {
            return $this->pattern;
        }



        /**
         * @return $this
         */
        public function getRoute()
        {
            return $this;
        }



        /**
         * @return int
         */
        public function getRequestedParamsCount()
        {
            return sizeof($this->getParamsDefinition());
        }



        /**
         * @return mixed
         */
        public function getParamsDefinition()
        {
            return $this->options[params];
        }



        /**
         * @return mixed
         */
        public function getAction()
        {
            return $this->options[action];
        }



        /**
         * @return mixed
         */
        public function getController()
        {
            return $this->options[controller];
        }

    }
