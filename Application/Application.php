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
use Wbengine\Components\HttpResponseInterface;
use Wbengine\Config;
use Wbengine\Db;
use Wbengine\Error;
use Wbengine\Exception\RuntimeException;
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
use Wbengine\Application\Http\ResponseInterface;
use Wbengine\Debug;

include_once dirname(__DIR__) . '/Application/Env/Const.php';

class Application implements ComponentParentInterface, ResponseInterface
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
     * Production environment
     * True = Development \ False = Production
     * @var bool
     */
    private $_env;

    private $_routes = array();

    /**
     * Users application namespace
     * @var null | string
     */
    private $_namespace = null;


    const APP_BASE_DIR              = '/App';
    const APP_CONFIG_PATH           = '/Config/';





    public function __construct($appBaseDir, $nameSpace = null)
    {
        if (!is_string($appBaseDir) || empty($appBaseDir)) {
            throw new ApplicationException(
                sprintf('%s->%s: Application base dir must be a string.'
                    , __CLASS__
                    , __FUNCTION__
                )
            );
        }


        try {

        /**
         * 1. LOAD CONFIGURATION
         * Setup config file example over class setter...
         */
        Config::load(new File($appBaseDir . self::APP_CONFIG_PATH . Config::CONFIG_FILE,true));

        if($this->isDebugOn() === true){
            ini_set('display_errors', 1);
            $this->setStartTime(microtime());
        }


            /**
             * Set default application namespace deffined in composer.json file ...
             */
            $this->setParentNameSpace($nameSpace);

            /**
             * Set application BASE path as firs ...
             */
            $this->setPath(Path::TYPE_BASE, ($appBaseDir));

            /**
             * setup errorhandler ...
             */
            set_error_handler(array($this->getErrorHandler(), 'SetErrorHandler'));

            /**
             * .. SET CURRENT TIMEZONE (OPTIONAL)...
             */
            date_default_timezone_set(Config::getTimeZone());


            /**
             * INITIALIZE OBJECT SITE...
             */
            if(empty($this->getClassVars()->getValue('central'))) {
                $this->getSite()->initialize($this);
            }


        } catch (RuntimeException $e) {
            $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($e));
        }

        if(Config::isDebugEnabled()) {
            $this->setEndtime(microtime());
            $this->setValue('debug', new Debug($this));
        }


    }


    public function getStaticBox($constructor){
        return $this->_createBox($constructor);
    }



    private function _createBox($constructor)
    {
        $values = explode('@',$constructor);

        if(is_array($values)){
            if(preg_match('/\\\\/', $values[0])){
                $namespace = $values[0];
            }else{
                $namespace = "\App\Box\\" . ucfirst($values[0]);
            }

            if($values[1]){
                $method = $values[1];
            }

        }

        if(class_exists($namespace))
        {
            if(method_exists($namespace, $method)){
                try {
                    $box = new $namespace(null,$this);
                    return $box->$method();
                }catch (RuntimeException $e){
                    $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($e));
                }
            }else{
                Throw New Router\Route\RouteException(sprintf("%s -> %s: No method found '%s::%s()'.",
                    __CLASS__,
                    __FUNCTION__,
                    $namespace,
                    $method
                ));

            }
        }else{
            Throw New Router\Route\RouteException(sprintf("%s -> %s: Class file '%s' not found.",
                __CLASS__,
                __FUNCTION__,
                $namespace
            ));

        }

        return;
    }


    public function isUserLogged(){
        return $this->getClassUser()->getUserIsLogged();
    }

    /**
     * Set Parent application namespace
     * @param $namespace string
     */
    public function setParentNameSpace($namespace){
        $this->_namespace = ($namespace) ? $namespace : self::getAppDir(true);
    }

    /**
     * Return uder defined parent namespace
     * @return null|string
     */
    public function getParentNameSpace(){
        return $this->_namespace;
    }

    /**
     * Create object Class_Renderer
     */
    private function _setRenderer(){
        $this->_renderer = new Renderer($this);
    }


    public function getParent(){
        return $this;
    }

    /**
     * Return created session instance.
     * @return Session
     */
    public function getSession(){
        if ($this->_session instanceof Session) {
            return $this->_session;
        }
        return $this->_createSession();
    }


    /**
     * Create new session instance object if needed.
     * @see Session
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
        }elseif($_device->isMobile()){
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
        $this->_site = new Site($this);
    }


    /**
     * Create instance of \Wbengine\Vars
     * @void
     */
    private function _setClassVars(){
        $this->_classVars = new Vars($this);
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
            $this->_classUser = new User($this);
        }
        return $this->_classUser;
    }


    /**
     * Return user's data loaded for an session.
     * @return array
     */
    public function getIdentityx(){
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
        return $this->_exception = new ApplicationException($message, $code);
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
        if (NULL === $this->errorHandler){
            $this->errorHandler = New Error\Handler();
        }
        return $this->errorHandler;
    }


    /**
     * Return Boxes count as sum of all sections boxes
     * @return int
     */
    public function getBoxesCount()
    {
        $boxes = 0;

        /**
         * @var $section Section
         */
        if($sections = $this->getSections()){
            foreach ($sections as $section){
                $boxes += (int)$section->getBoxesCount();
            }
        }
        return $boxes;
    }


    /**
     * Return App name as path ...
     * @param bool $noSlashes
     * @return string
     */
    public static function _getAppDir($noSlashes){
        return ($noSlashes) ? ltrim(self::APP_BASE_DIR,'/') : self::APP_BASE_DIR;
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
     * Return config filename
     * @return string
     */
    public function getConfigFile(){
        return Config::CONFIG_FILE;
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


    public function addRoute($route){
        $this->_routes[] = $route;
    }

    /**
     * Return site object instance
     * @return \Wbengine\Site
     */
    public function getSite()
    {
        if ($this->_site instanceof Site){
            return $this->_site;
        }else{
            $this->_createSite();
        }
        return $this->_site;
    }


    public function get($path, $callable)
    {
        if (!is_string($path)) {
            throw new ApplicationException('Route pattern must be a string.');
        }

        try {
            $req = Router::get($path, function ($route) {
                return $route;
            });

            if($req) {
                $callable($req, $this);
                $this->addRoute($path);
            }else{
                return;
            }

        }catch (ApplicationException $e){
            $this->addException($e->getMessage(), $e->getCode());
            $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($e));
        }
    }


    public function display($content = null){

        //@TODO Try to avoid multipple calls of init() function...
        $this->init();

        try {
            if ($content) {
                $this->setValue(HTML_CENTRAL_SECTION, $content);
            }
            $this->getRenderer()->dispatch($this);
        }catch (ApplicationException $e){die(error);
            $this->addException($e->getMessage(), $e->getCode());
            $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($e));


        }
    }



    /**
     * Run the application ...
     * @param null $errorHandler
     */
    public function run($errorHandler = null)
    {
        try {

            if ($errorHandler === HTML_ERROR_410) {
                $this->addException('Gone.', HTML_ERROR_410);
                $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($this->getException()));
            }

            if ((!$this->getSite() instanceof Site || $this->getSite()->isLoaded() === false) && sizeof($this->_routes) === 0) {
                $this->addException('Site not found.', HTML_ERROR_404);
                $this->setValue(HTML_CENTRAL_SECTION, $this->getRenderer()->getErrorBox($this->getException()));
            }

            $this->getRenderer()->dispatch($this);

        }catch (ApplicationException $e){
            $e->Show();
        }

    }

}
