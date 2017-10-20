<?php

    /**
     * $Id$ - CLASS
     * --------------------------------------------
     * Url class manage defaults url included all
     * needed functions.
     *
     * @package RRsoft-CMS
     * @version $Rev$ $Date$ $Author$
     * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
     * @license GNU Public License
     *
     * Minimum Requirement: PHP 5.1.x
     */

    namespace Wbengine;

    use Wbengine\Application\Application;
    use Wbengine\Site;

    class Url
    {


        /**
         * Given site url
         *
         * @var string
         */
        private $_url = NULL;

        /**
         * URL parts between slashes
         *
         * @var array
         */
        private $_parts = NULL;

        /**
         * paired urls by each url part
         *
         * @var array
         */
        private $_urlPairs = NULL;

        /**
         * Extended params from site url
         *
         * @var <type>
         */
        private $_ext = NULL;

        /**
         * Full url without host
         *
         * @var string
         */
        private $_link = NULL;

        /**
         * Last part from url
         *
         * @var string
         */
        private $_lastUrlPart = NULL;

        /**
         * Given raw url
         *
         * @var string
         */
        private $_requestUri = NULL;

        private $_cmsd = NULL;

        private $_get = NULL;

        private $_post = NULL;

        private $_cookie = NULL;

        private $_cleanVarAsDefault = TRUE;

        private $_urlStrict = TRUE;

        private $_urlParams = NULL;

        /**
         * Full url with a hostname
         * @var string
         */
        private $_fullUrl = null;


        /**
         * Class constructor
         * -----------------
         * 1) We set http variables
         * 2) We set raw requested uri
         * @param Application $app
         */
        public function __construct(Application $app)
        {
            $this->_parent = $app;

            $this->_get = (!get_magic_quotes_gpc())
                ? $this->_addMagicQuotes(filter_input_array(INPUT_GET))
                : filter_input_array(INPUT_GET);
            $this->_post = (!get_magic_quotes_gpc())
                ? $this->_addMagicQuotes(filter_input_array(INPUT_POST))
                : filter_input_array(INPUT_POST);
            $this->_cookie = (!get_magic_quotes_gpc())
                ? $this->_addMagicQuotes(filter_input_array(INPUT_COOKIE))
                : filter_input_array(INPUT_COOKIE);

            $_requestUri = filter_input(INPUT_SERVER, "REQUEST_URI");

            // try to store parameters from given uri request
            if (strstr($_requestUri, "?")) {
                $this->_setUrlParams(trim(strstr($_requestUri,"?"),"?"));
                $_requestUri = substr($_requestUri, 0, strpos($_requestUri, '?'));
            }

            $this->_requestUri = $_requestUri;
            $this->_setUrlExtension();
            $this->_setLink();
            $this->_setUrlParts();
            $this->_setUrl();
            $this->_setLastUrlPart();
            $this->_setUrlRestriction();
            $this->_setUrlPairs();
            $this->_createFullUrl();
        }



        /**
         * Store url extension if exist.
         */
        private function _setUrlExtension()
        {
            $this->_ext = $this->request("GET", "ext");
        }

        private function _createFullUrl(){
            $this->_fullUrl = $this->getHostName().$this->getUrl();
        }


        /**
         * Store url and extension without last slash.
         */
        private function _setLink()
        {
            if(isset($_REQUEST['link'])){
                $this->_link = trim($_REQUEST['link'] . $this->_ext, '/');
                $this->_link = $_REQUEST['link'] . $this->_ext;
            }
        }



        /**
         * Store full URL without host name.
         */
        private function _setUrl()
        {
            $this->_url = $this->_requestUri;
            return;
        }



        /**
         * Store divided url parts by slashes to array.
         */
        private function _setUrlParts()
        {
            $this->_parts = explode('/', trim($this->_link, '/'));
        }



        /**
         * Store last url part after host name.
         */
        private function _setLastUrlPart()
        {
            $this->_lastUrlPart = $this->_parts[sizeof($this->_parts) - 1];
        }


        /**
         * Set boolean value to TRUE if url is grouped.
         * This means that the last part of the URL is not
         * an extension.
         */
        private function _setUrlRestriction()
        {
            if (preg_match('/\.html/', $this->getUrl()))
            {
                $this->_urlStrict = TRUE;
            }
            else
            {
                $this->_urlStrict = FALSE;
            }
        }


        /**
         * Create array with each of url parts as section urls.
         * Each index starts with slash, but ending without slashes.
         *
         * @void
         */
        private function _setUrlPairs()
        {
            $_arr = array();
            $_tmp = NULL;

            $_parts = explode('/', trim($this->getUrl(), '/'));

            foreach ($_parts as $_part)
            {
                if (empty($_tmp))
                {
                    $_tmp = "";
                }

                $_tmp   = $_tmp . "/$_part";
                $_arr[] = $_tmp;
            }

            $this->_urlPairs = $_arr;
        }



        /**
         * Insert magic quotes to array.
         *
         * @param arry $array
         * @return array
         */
        private function _addMagicQuotes($array = NULL)
        {
            if (NULL === $array || !is_array($array))
                return;

            foreach ($array as $k => $v)
            {
                if (is_array($v))
                {
                    $array[$k] = add_magic_quotes($v);
                }
                else
                {
                    $array[$k] = addslashes($v);
                }
            }

            return $array;
        }



        /**
         * Remove magis quotes from the array.
         *
         * @param array $array
         * @return arry
         */
        private function _removeMagicQuotes($array = NULL)
        {
            if (NULL === $array || !is_array($array))
                return;

            foreach ($array as $k => $v)
            {
                if (is_array($v))
                {
                    $array[$k] = remove_magic_quotes($v);
                }
                else
                {
                    $array[$k] = stripslashes($v);
                }
            }

            return $array;
        }



        /**
         * Clean given string or text from html entities.
         *
         * @param mixed $var
         * @return mixed
         */
        private function _cleanVar($var = NULL)
        {

            if (empty($var))
            {
                return;
            }

            if (!is_array($var))
            {
                $var = htmlentities($var, ENT_QUOTES, "UTF-8");
            }
            else
            {
                foreach ($var as $key => $value)
                {
                    $var[$key] = $this->_cleanVar($value);
                }
            }

            return $var;
        }



        /**
         * This function clean url from unwanted characters and return it
         * as SEO url.
         *
         * @param string $url
         * @param boolean $addSlashes
         * @return string
         */
        private function _seoUrl($url, $addSlashes = TRUE)
        {
            $tmp = preg_replace('/[^a-zA-ZÁČĎÉĚÍŇÓŘŠŤŮÚÝŽáčďéěíňóřšťůúýž-\s]\//', '', $url);

            return $tmp;
        }



        /**
         * Set URL params from given url request string
         * Set class var as array
         * @param $params
         */
        private function _setUrlParams($params)
        {
            $_pars = explode("&", $params);

            foreach($_pars as $par){
                $i = explode("=", $par);
                $ta[$i[0]] = $i[1];
            }

            $this->_urlParams = $ta;
        }


        /**
         * Return a full url with hostname
         * @return string
         */
        public function getFullUrl() {
            return $this->_fullUrl;
        }


        /**
         * Do permanent url http 301 redirection
         * @param null $url
         */
        public function doRedirection($url = NULL)
        {
            if ($url === NULL)
                return;

            foreach ($this->getSite()->getRedirections() as $from => $to)
            {
                if ((string)$from === (string)$url)
                {
                    Header("HTTP/1.1 301 Moved Permanently");
                    Header("Location: " . $this->getHostName() . $to);
                    exit();
                }
            }
        }



        /**
         * Return parent site object
         *
         * @return Class_Site
         */
        public function getSite()
        {
            return $this->_parent->getSite();
        }



        /**
         * Return Site home URL with default protocol
         *
         * @return string
         */
        public function getHostName()
        {
            return preg_replace('/[^a-z](.*)/', '://' . $_SERVER['HTTP_HOST']
                , strtolower($_SERVER['SERVER_PROTOCOL']));
        }



        /**
         * Return raw url parsed from curent page.
         *
         * @return string
         */
        public function getUri()
        {
            return (NULL === $this->_requestUri)
                ? $this->getHostName()
                : $this->getHostName() . $this->_requestUri;
        }



        public function getLink()
        {
            return $this->_link;
        }



        /**
         * Return url pairs as section urls.
         *
         * @return array
         */
        public function getUrlPairs()
        {
            return $this->_urlPairs;
        }



        /**
         * Return state if url is strict
         *
         * @return boolean
         */
        public function getUrlStrict()
        {
            return $this->_urlStrict;
        }



        /**
         * Return url parts as array
         *
         * @return array
         */
        public function getUrlParts()
        {
            return $this->_parts;
        }



        /**
         * Clean the string from unvanted magic quotes
         * and html tags.
         * Function working with string but with the array as well.
         *
         * @param mixed $var
         * @return mixed
         */
        public function cleanVar($var)
        {
            return $this->_cleanvar($var);
        }



        /**
         * Return cleaned last part url.
         *
         * @return string
         */
        public function getUrl()
        {
            return $this->_url;
        }


        /**
         * Return stored URL params from given url
         * @return array
         */
        public function getUrlParams(){
            return $this->_urlParams;
        }


        /**
         * Set default value to clean all headers variables
         * before use it.
         *
         * @param boolean $val
         */
        public function cleanVarsAsDefault($val = TRUE)
        {
            $this->_cleanVarAsDefault = (boolean)$val;
        }



        /**
         * Method return html header variables by givet method name.
         * Allowed types is GET, POST and COOKIE.
         * Return array when value argument is empty and return string if
         * value argument exist, otherwise return null.
         * Method alternatively can remove magic quotes, if $removeMagicQuote
         * argument = TRUE.
         *
         * @param string $method
         * @param string $value
         * @param boolean $removeMagicQuote
         * @return mixed
         */
        public function request($method = "GET", $value = NULL, $removeMagicQuote = FALSE)
        {

            switch ($method)
            {
                case "GET":

                    if (NULL === $value)
                    {
                        return ($removeMagicQuote === TRUE)
                            ? $this->_cleanVar($this->_removeMagicQuotes($this->_get))
                            : $this->_cleanVar($this->_get);
                    }
                    else
                    {
//		    die(fff);
                        if (isset($this->_get[$value]))
                        {
                            if ($removeMagicQuote === TRUE)
                            {
                                return $this->_cleanVar(remove_magic_quotes($this->_get[$value]));
                            }
                            else
                            {
                                return $this->_cleanVar($this->_get[$value]);
                            }
                        }
                    }
                    break;

                case "POST":
                    if (NULL === $value)
                    {
                        return ($removeMagicQuote === TRUE)
                            ? $this->_cleanVar($this->_removeMagicQuotes($this->_post))
                            : $this->_cleanVar($this->_post);
                    }
                    else
                    {

                        if (isset($this->_post[$value]))
                        {
                            return ($removeMagicQuote === TRUE)
                                ? $this->_cleanVar(remove_magic_quotes($this->_post[$value]))
                                : $this->_cleanVar($this->_post[$value]);
                        }
                    }
                    break;

                default:
                    break;
            }
        }


    }
