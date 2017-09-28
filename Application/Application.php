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
use Wbengine\Application\Path\File;
use Wbengine\Application\Mobile\Detector;
use Wbengine\Application\Path\Path;
use Wbengine\Config;
use Wbengine\Error;
use Wbengine\Exception\RuntimeException;
use Wbengine\Locale\LocaleAbstract;
use Wbengine\Section;
use Wbengine\Site;
use Wbengine\Renderer;
use Wbengine\Session;
use Wbengine\Url;
use Wbengine\User;
use Wbengine\Vars;

include_once dirname(__DIR__) . '/Application/Env/Const.php';

abstract Class Application
{
    /**
     * Locale class
     * @var Class_Locale
     */
    private $_locale = NULL;

    /**
     * Loaded user's data
     * @var array
     */
    private $_userData = NULL;

    /**
     * Instance of user class
     * @var Class_User
     */
    private $_classUser = NULL;

    /**
     * Created session object
     * @var Session
     */
    private $_session = NULL;

    /**
     * Just debugging trigger
     * @var boolean
     */
    private $debug = FALSE;

    /**
     * Instance of Class_Config
     * @var \Wbengine\Config
     */
    private $config = NULL;

    /**
     * Instance of Class_Renderer
     * @var Class_Renderer
     */
    private $_renderer = NULL;

    /**
     * Error handler
     * @var Class_Error_Handler
     */
    private $errorHandler = NULL;

    /**
     * Instance of Class_Site_Url
     * @var Class_Site_Url
     */
    private $_classUrl = NULL;

    /**
     * CMS member object exception
     * @var Exception
     */
    private $_exception = NULL;

    /**
     * Instance of object Class_Site
     * @var type Class_Site
     */
    private $_site = NULL;

    /**
     * Site Vars
     * @var Class_Vars
     */
    private $_classVars = NULL;

    /**
     * URL redirection array
     * @var type array
     */
    private $_redirections = array();


    protected $isBackend = false;

    private $dbConnection   = NULL;

    private $_templateDir   = NULL;

    private $_configPaths   = array();

    private $Path           = null;

    private $Detector   = null;

    private $_deviceType   = null;


    /**
     * Create object Class_Renderer
     */
    private function _setRenderer()
    {
        $this->_renderer = New Renderer($this);
    }


    /**
     * Return created session instance.
     * @return \Wbengine\Session
     */
    public function getSession()
    {
        if ($this->_session instanceof Session) {
            return $this->_session;
        }
        return $this->_createSession();
    }


    /**
     * Create new session instance object if needed.
     * @see Class_Session
     */
    private function _createSession()
    {
        return $this->_session = new Session();
    }


    /**
     * Set Device type as integer
     * @See Wbengine\Application\Mobile\Detector
     */
    private function _setApplicationTypeByDevice()
    {
        $_device = $this->_getObjectMobileDetector();

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
    private function _createSite()
    {
        $this->_site = New Site(New Url($this));
    }


    /**
     * Create instance of \Wbengine\Vars
     * @void
     */
    private function _setClassVars()
    {
        $this->_classVars = New Vars($this);
    }


    /**
     * Set user data to local variable for latest use.
     * @param array $userData
     */
    private function _setUserData($userData)
    {
        $this->_userData = $userData;
    }


    /**
     * Set user identities if needed.
     * @see getIdentity
     */
    private function _setIdentity()
    {
        $this->_setUserData($this->getClassUser()->getIdentity());
    }

    /**
     * Store locale class.
     * @param \Wbengine\Locale\LocaleAbstract $locale
     */
    private function _setLocale(LocaleAbstract $locale)
    {
        $this->_locale = $locale;
    }


    /**
     * @param string $name
     * @param string $include
     * @param bool $appBaseDir
     * @return null|Path
     */
    public function getPath($name = null, $include = null, $appBaseDir = false)
    {
        return $this->_getObjectPath();
    }


    /**
     * Minimize css file...
     * @param $files
     * @param null $path
     * @return Void
     */
    public function minimizeCssFiles($files, $path = null)
    {
        foreach ($files as $file){
            $cssFile = New File($path.$file);
            $ef = New File($cssFile->newFileName(File::FILE_TYPE_ETAG, $this->getPath()->getRendererTempDir())->getFile(), true);

            if (Utils::compareStrings(md5_file($cssFile->getFile()), $ef->getContent()) === false)
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
     * @param $path
     * @param bool $appBaseDir
     */
    public function setPath($name = null, $path, $appBaseDir = false)
    {
        return $this->_getObjectPath()->addPath($name, $path, $appBaseDir);
    }


    /**
     * Return instance of object Path
     * @return null|Path
     */
    public function _getObjectPath()
    {
        if(null === $this->Path || !$this->Path instanceof Path){
            $this->Path = New Path();
        }
        return $this->Path;
    }


    /**
     * Return instance of Mobile\Detector class
     * @return null|Detector
     */
    public function _getObjectMobileDetector()
    {
        if(null === $this->Detector || !$this->Detector instanceof Detector){
            $this->Detector = New Detector();
        }

        return $this->Detector;
    }


    /**
     * Return site type prefix as string.
     * We using this prefix in renderer for right app template path,
     * but feel free use it for whatever you want.
     * @see getAppType()
     * @return string
     */
    public function getAppTypeId()
    {
        return $this->getSite()->getSiteParentKey();
    }


    /**
     * Return detected device type as integer
     * DEVICE_TYPE_MOBILE | DEVICE_TYPE_TABLET | DEVICE_TYPE_DESKTOP
     *
     * @return null|Integer
     */
    public function getDeviceType()
    {
        if($this->_deviceType === null){
            $this->_setApplicationTypeByDevice();
        }

        return $this->_deviceType;
    }


    /**
     * Return all defined redirections.
     * @return array
     */
    public function getRedirections()
    {
        return $this->_redirections;
    }


    /**
     * Get debuging trigger...
     * @internal param bool $debug
     * @return \Wbengine\Application\boolean
     */
    public function isDebugOn()
    {
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
    public function setValue($key, $value = NULL, $parentKey = NULL)
    {
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
    public function getVars()
    {
        return $this->getClassVars()->getValues();
    }


    /**
     * Return instance of Object Vars
     * @return \Wbengine\Vars
     */
    public function getClassVars()
    {
        if ($this->_classVars instanceof Vars) {
            return $this->_classVars;
        }

        $this->_setClassVars();
        return $this->_classVars;
    }


    /**
     * Return CMS member object Exception
     * @return Exception
     */
    public function getException()
    {
        return $this->_exception;
    }


    /**
     * Return instance of class User.
     * @return \Wbengine\User
     */
    public function getClassUser()
    {
        if (!$this->_classUser instanceof User) {

            $this->_classUser = new User($this);
        }

        return $this->_classUser;
    }


    /**
     * Return user's data loaded for an session.
     * @return array
     */
    public function getIdentity()
    {
        if (is_array($this->_userData) && sizeof($this->_userData)) {
            return $this->_userData;
        } else {
            $this->_setIdentity();
        }

        return $this->_userData;
    }


    /**
     * Return a created locale instance.
     * @return \Wbengine\Locale\LocaleAbstract
     */
    public function getLocale()
    {
        if (NULL === $this->_locale) {
            $this->_setLocale($this->getSession()->getLocale());
        }

        return $this->_locale;
    }


    /**
     * Return a config class object
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }


    /**
     * Set Config adapter
     * @param \Wbengine\Config\Adapter\AdapterInterface $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }


    /**
     * Return created object renderer
     * @return \Wbengine\Renderer
     */
    public function getRenderer()
    {
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
    public function addException($message, $code = null)
    {
        $this->_exception = new ApplicationException($message, $code);
    }


    /**
     * Return instance of Class_Url
     * @return Url
     */
    public function getClassUrl()
    {
        return $this->getSite()->getClassUrl();
    }


    /**
     * Return instance of \Wbengine\Error\Handler
     * @return \Wbengine\Error\Handler
     */
    public function getErrorHandler()
    {
        if (NULL === $this->errorHandler) {
            $this->errorHandler = New Error\Handler();
        }

        return $this->errorHandler;
    }


    /**
     * Return Section object by given ID
     * @param $id
     * @return Section
     */
    public function getSectionById($id) {
        $section = New Section($this->getSite());
        return $section->getSection($id);
    }


    /**
     * Return site object instance
     * @return \Wbengine\Site
     */
    public function getSite()
    {
        if ($this->_site instanceof Site) {
            return $this->_site;
        } else {
            $this->_createSite();
        }
        return $this->_site;
    }


    /**
     * Return database connection adapter
     * @return null
     */
    public function getDbAdapter()
    {
        return $this->dbConnection;
    }


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
