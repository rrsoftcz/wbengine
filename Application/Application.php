<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * CMS class provide most of general
 * functionalities as store data to session,
 * manage user's accounts, site locales and etc.
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Application;

use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Application\Mobile\Detector;
use Wbengine\Application\Path\File;
use Wbengine\Application\Path\Path;
use Wbengine\Components\ComponentParentInterface;
use Wbengine\Config;
use Wbengine\Db;
use Wbengine\Error;
use Wbengine\Locale;
use Wbengine\Locale\LocaleAbstract;
use Wbengine\Router;
use Wbengine\Section;
use Wbengine\Site;
use Wbengine\Renderer;
use Wbengine\Session;
use Wbengine\Url;
use Wbengine\User;
use Wbengine\Vars;

include_once dirname(__DIR__) . '/Application/Env/Const.php';

abstract Class Application implements ComponentParentInterface
{
    /**
     * Locale class
     * @var int
     */
    private $_locale = NULL;

    /**
     * Loaded user's data
     * @var array
     */
    private $_userData = NULL;

    /**
     * Instance of user class
     * @var User
     */
    private $_classUser = NULL;

    /**
     * Created session object
     * @var Session
     */
    private $_session = NULL;

    /**
     * Instance of Class_Config
     * @var \Wbengine\Config
     */
    private $config = NULL;

    /**
     * Instance of Class_Renderer
     * @var Renderer
     */
    private $_renderer = NULL;

    /**
     * Error handler
     * @var Error\Handler
     */
    private $errorHandler = NULL;

    /**
     * CMS member object exception
     * @var ApplicationException
     */
    private $_exception = NULL;

    /**
     * Instance of object Class_Site
     * @var Site
     */
    private $_site = NULL;

    /**
     * Site Vars
     * @var Vars
     */
    private $_classVars = NULL;

    /**
     * URL redirection array
     * @var array
     */
    private $_redirections  = array();

    /**
     * Instance of object Detector
     * @var Detector
     */
    private $_detector;

    /**
     * Device type as integer
     * @var int
     */
    private $_deviceType;

    /**
     * Object of class Path
     * @var Path
     */
    private $_path;

    /**
     * Start time as microtime
     * @var double
     */
    private $_starttime;

    /**
     * End time as microtime
     * @var double
     */
    private $_endtime;

    /**
     * Config filename
     * @var string
     */
    private $_config_file;

    /**
     * Production environment
     * True = Development \ False = Production
     * @var bool
     */
    private $_env;

    protected static $APP_BASE_DIR;
    protected static $APP_TYPE_CACHE;
    protected static $APP_CONFIG_PATH;
    protected static $APP_TEMPLATE_PATH;
    protected static $ENV_TYPE_PRODUCTION;
    protected static $APP_TYPE_RENDERER_TEMP;

    /**
     * Create object Class_Renderer
     */
    private function _setRenderer(){
        $this->_renderer = New Renderer($this);
    }


    /**
     * Return created session instance.
     * @return \Wbengine\Session
     */
    public function getSession(){
        if ($this->_session instanceof Session) {
            return $this->_session;
        }
        return $this->_createSession();
    }


    /**
     * Create new session instance object if needed.
     * @see Class_Session
     */
    private function _createSession(){
        return $this->_session = new Session();
    }


    /**
     * Set Device type as integer
     * @See Wbengine\Application\Mobile\_detector
     */
    private function _setApplicationTypeByDevice()
    {
        $_device = $this->_getObjectMobile_detector();

        if($_device->isTablet()){
            $this->_deviceType = DEVICE_TYPE_TABLET;
        }elseif($_device->isMobile()) {
            $this->_deviceType = DEVICE_TYPE_MOBILE;
        }else{
            $this->_deviceType = DEVICE_TYPE_DESKTOP;
        }
    }


    /**
     * Create instance of object \Wbengine\Site
     * @$this->_site
     */
    private function _createSite(){
        $this->_site = New Site(New Url($this));
    }


    /**
     * Create instance of \Wbengine\Vars
     * @void
     */
    private function _setClassVars(){
        $this->_classVars = New Vars($this);
    }


    /**
     * Set user data to local variable for latest use.
     * @param array $userData
     */
    private function _setUserData($userData){
        $this->_userData = $userData;
    }


    /**
     * Set user identities if needed.
     * @see getIdentity
     */
    private function _setIdentity(){
        $this->_setUserData($this->getClassUser()->getIdentity());
    }


    /**
     * Store locale class.
     * @param LocaleAbstract $locale
     */
    private function _setLocale(LocaleAbstract $locale){
        $this->_locale = $locale;
    }


    /**
     * @return null|Path
     * @internal param string $name
     * @internal param string $include
     * @internal param bool $appBaseDir
     */
    public function getPath(){
        return $this->_getObjectPath();
    }


    /**
     * Minimize css file...
     * @param $files
     * @param null $_path
     * @return Void
     */
    public function minimizeCssFiles($files, $_path = null)
    {
        foreach ($files as $file){
            $cssFile = New File($_path.$file);
            $ef = New File($cssFile->newFileName(File::FILE_TYPE_ETAG, $this->getPath()->getCacheDir())->getFile(), true);

            if (!$ef->exist() || Utils::compareStrings(md5_file($cssFile->getFile()), $ef->getContent()) === false)
            {
                $minFile = $cssFile->saveAsMinimized();
                $minFile->replaceInFile('%_cdn_%', Config::getCdnPath());

                if($minFile->getStatus() === true){
                    $ef->saveEtag($cssFile);
                }
            }

        }
    }


    /**
     * Create instance of object class Path
     * @param null $name
     * @param $_path
     * @param bool $appBaseDir
     */
    public function setPath($name = null, $_path, $appBaseDir = false){
        return $this->_getObjectPath()->addPath($name, $_path, $appBaseDir);
    }


    /**
     * Return instance of object Path
     * @return null|Path
     */
    public function _getObjectPath(){
        if(null === $this->_path || !$this->_path instanceof Path){
            $this->_path = New Path();
        }
        return $this->_path;
    }


    /**
     * Return instance of Mobile\_detector class
     * @return null|Detector
     */
    public function _getObjectMobile_detector(){
        if(null === $this->_detector || !$this->_detector instanceof Detector){
            $this->_detector = New Detector();
        }

        return $this->_detector;
    }


    /**
     * Return detected device type as integer
     * DEVICE_TYPE_MOBILE | DEVICE_TYPE_TABLET | DEVICE_TYPE_DESKTOP
     *
     * @return null|Integer
     */
    public function getDeviceType(){
        if($this->_deviceType === null){
            $this->_setApplicationTypeByDevice();
        }

        return $this->_deviceType;
    }


    /**
     * Return all defined redirections.
     * @return array
     */
    public function getRedirections(){
        return $this->_redirections;
    }


    /**
     * Get debuging trigger...
     * @internal param bool $debug
     * @return \Wbengine\Application\boolean
     */
    public function isDebugOn(){
        return Config::isDebugEnabled();
    }


    /**
     * Add given value to local vars.
     * In order you can specify parent key.
     *
     * @param string $key
     * @param mixed $value
     * @param string $parentKey
     */
    public function setValue($key, $value = NULL, $parentKey = NULL){
        if (!empty($parentKey)) {
            $this->getClassVars()->addValue($key, $value, $parentKey);
        } else {
            $this->getClassVars()->addValue($key, $value);
        }
    }


    /**
     * Return all assigned site variables.
     * @return array
     */
    public function getVars(){
        return $this->getClassVars()->getValues();
    }


    /**
     * Return instance of Object Vars
     * @return \Wbengine\Vars
     */
    public function getClassVars(){
        if ($this->_classVars instanceof Vars) {
            return $this->_classVars;
        }

        $this->_setClassVars();
        return $this->_classVars;
    }


    /**
     * Return CMS member object Exception
     * @return ApplicationException
     */
    public function getException(){
        return $this->_exception;
    }


    /**
     * Return instance of class User.
     * @return \Wbengine\User
     */
    public function getClassUser(){
        if (!$this->_classUser instanceof User) {
            $this->_classUser = new User();
        }
        return $this->_classUser;
    }


    /**
     * Return user's data loaded for an session.
     * @return array
     */
    public function getIdentity(){
        if (is_array($this->_userData) && sizeof($this->_userData)) {
            return $this->_userData;
        } else {
            $this->_setIdentity();
        }
        return $this->_userData;
    }


    /**
     * Return a created locale instance.
     * @return int
     */
    public function getLocale(){
        if (!$this->_locale instanceof Locale) {
            $this->_createLocale();
        }
        return $this->_locale;
    }


    private function _createLocale(){
        $this->_locale = new Locale();
    }


    /**
     * Return a config class object
     * @return Config
     */
    public function getConfig(){
        return $this->config;
    }


    /**
     * Set Config adapter
     * @param \Wbengine\Config\Adapter\AdapterInterface $config
     */
    public function setConfig($config){
        $this->config = $config;
    }


    /**
     * Return created object renderer
     * @return \Wbengine\Renderer
     */
    public function getRenderer(){
        If (NULL === $this->_renderer) {
            $this->_setRenderer();
        }

        return $this->_renderer;
    }


    /**
     * Create New CMS object member Exception
     * @param string $message
     * @param integer $code
     */
    public function addException($message, $code = null){
        $this->_exception = new ApplicationException($message, $code);
    }


    /**
     * Return instance of Class_Url
     * @return Url
     */
    public function getClassUrl(){
        return $this->getSite()->getClassUrl();
    }


    /**
     * Return instance of \Wbengine\Error\Handler
     * @return \Wbengine\Error\Handler
     */
    public function getErrorHandler(){
        if (NULL === $this->errorHandler) {
            $this->errorHandler = New Error\Handler();
        }

        return $this->errorHandler;
    }


    /**
     * Return Boxes count as sum of all sections boxes
     * @return int
     */
    public function getBoxesCount(){
        /**
         * @var $section Section
         */
        $boxes = 0;
        foreach ($this->getSections() as $section){
            $boxes += (int)$section->getBoxesCount();
        }
        return $boxes;
    }


    /**
     * Return App name as path ...
     * @param bool $noSlashes
     * @return string
     */
    public static function _getAppDir($noSlashes){
        return ($noSlashes)?ltrim(self::$APP_BASE_DIR,'/'):self::$APP_BASE_DIR;
    }


    /**
     * Return Web app directory
     * @param $noSlash
     * @return string
     */
    public function getAppDir($noSlash = false){
        return self::_getAppDir($noSlash);
    }


    /**
     * Return Application environment
     * @return boolean
     */
    public function getEnv(){
        return $this->_env;
    }


    /**
     * Set config filename and create (set) environment type
     * derived from config name
     * @param $filename
     */
    public function setConfigFile($filename){
        $this->_env = (boolean)preg_match('/(devel)/',strtolower($filename));
        $this->_config_file = $filename;
    }


    /**
     * Return config filename
     * @return string
     */
    public function getConfigFile(){
        return $this->_config_file;
    }


    /**
     * Return sections count
     * @return int
     */
    public function getSectionsCount(){
        return sizeof($this->getSections());
    }


    /**
     * Return Sections as array collection
     * @return array
     */
    public function getSections(){
        return $this->getSite()->getSections();
    }


    /**
     * Set start time as microtime
     * @param $starttime
     */
    public function setStartTime($starttime){
        $this->_starttime = $starttime;
    }


    /**
     * Set end time as microtime
     * @param $endtime
     */
    public function setEndtime($endtime){
        $this->_endtime = $endtime;
    }


    /**
     * Return start time
     * @return mixed
     */
    public function getStartTime(){
        return $this->_starttime;
    }


    /**
     * Return end time
     * @return mixed
     */
    public function getEndTime(){
        return $this->_endtime;
    }


    /**
     * Return all executed queries as array
     * @see Db
     * @return int
     */
    public function getAllQueriesCount(){
        return Db::getQueriesCount();
    }


    /**
     * Return sum of executed queries time
     * @see Db
     * @return int
     */
    public function getAllQueriesTime(){
        return Db::getAllQueriesEstimatedTime();
    }


    /**
     * Return site object instance
     * @return \Wbengine\Site
     */
    public function getSite(){
        if ($this->_site instanceof Site){
            return $this->_site;
        }else{
            $this->_createSite();
        }
        return $this->_site;
    }

    Public function get($user_rouute, $function, $callable){
        $router = new Router($this);
        var_dump($router->match($user_rouute));
    }


    /**
     * Run the application ...
     * @param null $errorHandler
     */
    public function run($errorHandler = null)
    {
        try {

            if ($errorHandler===HTML_ERROR_410) {
                $this->addException('Gone.', HTML_ERROR_410);
                $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($this->getException()));
            }

            if (!$this->getSite() instanceof Site || $this->getSite()->isLoaded() === false) {
                $this->addException('Site not found.', HTML_ERROR_404);
                $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($this->getException()));
            }

            $this->getRenderer()->dispatch($this);

        }catch (ApplicationException $e){
            $this->addException($e->getMessage(), $e->getCode());
            $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($e));
        }

    }

}
