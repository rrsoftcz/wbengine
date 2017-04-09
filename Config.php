<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Config class
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

use Wbengine\Application\Application;
use Wbengine\Application\Path\File;
use Wbengine\Application\Path\Path;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Config\Adapter\AdapterArray;
use Wbengine\Config\Adapter\AdapterInterface;
use Wbengine\Config\Adapter\Exception\ConfigException;
use Wbengine\Config\Adapter\AdapterAbstract;

abstract class Config implements AdapterInterface
{
    CONST CONFIG_FILE_DEVEL             = 'Devel.cfg.php';
    CONST CONFIG_FILE_PRODUCCTION       = 'Default.cfg.php';
    CONST DETECT_ENV_TYPE_BY_IP         = 2;
    CONST DETECT_ENV_TYPE_BY_HOSTNAME   = 3;

    CONST CONFIG_TYPE_ARRAY             = 'php';
    CONST CONFIG_TYPE_INI               = 'ini';
    CONST CONFIG_TYPE_JASON             = 'jason';
    CONST CONFIG_TYPE_XML               = 'xml';
    CONST CONFIG_TYPE_YAML              = 'yaml';

    CONST DEFAULT_CONFIG_DIR_NAME       = '/Config/';


    /**
     * Path to config file
     * @var string
     */
    private static $configFilePath  = NULL;


    /**
     * Config adapter type
     * @var string
     */
    private static $configAdapter   = NULL;


    /**
     * File extension
     * @var string
     */
    private static $fileExtension   = null;

    /**
     * Safe IP NOT production Subnets
     * We try detect devel OR production by IP
     * @var array
     */
    private static $_safeIpRanges   = array
    (
        '127.0.0.1',
        '10.0.0.0/16',
        '172.0.0.0/16',
    );


    /**
     * SafeE NOT production hostname keywords
     * We try detect devel OR production by hostname
     * @var array
     */
    private static $_safeHostKeywords   = array
    (
        'devel',
        'test',
        'local',
        'home',
    );


    /**
     * Config file extensions.
     * The autodetect config type function create
     * instance of apropirate adapter class.
     *
     * @var array
     */
    private static $_supporteConfigTyes = array(
        'php'  => 'Php',
        'ini'  => 'Ini',
        'json' => 'Json',
        'xml'  => 'Xml',
        'yaml' => 'Yaml',
    );


    /**
     * Config adapter instance
     * @var Config\Adapter\AdapterInterface
     */
    static $adapter         = NULL;


    /**
     * return stored config adapter
     * @return AdapterInterface
     * @throws ConfigException
     */
    public static function getConfigAdapter()
    {
        if(is_null(self::$adapter)){

            if(!empty(self::$configFilePath)){
                // ...is deffined? Try to load ...
                if(self::load(New File(self::$configFilePath))){
                    return self::$adapter;
                }
            }else{
                // ...probably wrong filename, so maybe try autoload...?
                if(self::load(New File(self::DEFAULT_CONFIG_DIR_NAME . self::autodetectEnvironment(FALSE), true))){
                    return self::$adapter;
                }

                throw new ConfigException(
                    sprintf('%s->%s: The configuration file is empty or not set.'
                        , __CLASS__
                        , __FUNCTION__
                    )
                );
            }
            // so adapter is empty and config file is not set or unknown...
            throw new ConfigException(
                sprintf('%s->%s: The config adapter is not set or not loaded...'
                    , __CLASS__
                    , __FUNCTION__
                )
            );
        }
        return self::$adapter;
    }

    /**
     * This method ceate config adapter by
     * given adapter type.
     *
     * @see local const
     * @param Application\Path\File|File $configFile
     * @return Config\Adapter\AdapterInterface
     * @throws ConfigException
     */
    static function load( File $configFile = null)
    {

//        var_dump($App->getPath(Path::TYPE_BASE).$App->getPath(Path::TYPE_CONFIG));
//        var_dump($App->getPath(Path::TYPE_CONFIG, Path::TYPE_BASE));
//        var_dump($App->getPath(Path::TYPE_BASE));
//        var_dump($App->getPath(Path::TYPE_CONFIG));
        //@todo Create config path object class...
//        self::$configFilePath = $App . DEFAULT_APP_DIR.DEFAULT_APP_CONFIG_DIR.DEFAULT_APP_CONFIG_FILE_NAME_DEVEL;


//        self::$configFilePath = $App->getPath(Path::TYPE_CONFIG, Path::TYPE_BASE);



//        $App =self::$configFilePath;
//var_dump(self::$configFilePath);
//        if (is_null($App)) {
//
//            throw new Exception\RuntimeException(__METHOD__ .
//                ': Config file path cannot be null!');
//        }
//        if (is_null($adapterType)) {
//
//            throw new Exception\RuntimeException(__METHOD__ .
//                ': Config adapter type cannot be null!');
//        }


//        self::$configAdapter = $adapterType;

//        if(!$configFile instanceof File){
//            throw new ConfigException(
//                sprintf('%s->%s: The argument must be instance of Wbengine\Application\Path\File but %s given.'
//                    , __CLASS__
//                    , __FUNCTION__
//                    , gettype($configFile)
//                )
//            );
//
//        }

        //@TODO - Define more adapters...
        self::setConfigAdapter($configFile);
;
        return self::getConfigAdapter();
    }




//    /**
//     * Return curent file extension
//     * @return string
//     */
//    private static function createFileExtension($adapterType)
//    {
//        if ($adapterType === self::CONFIG_TYPE_ARRAY) {
//
//            return '.' . substr(strrchr(__FILE__, '.'), 1);
//        }
//        return '.' . $adapterType;
//    }

    static function addEnvironmentSafeKeyword($keyword){
        self::$_safeHostKeywords[] = $keyword;
    }

    static function addEnvironmentSafeIp($iprange){
        self::$_safeIpRanges[] = $iprange;
    }

    static function setConfigFilePath($filename){
        self::$configFilePath = $filename;
    }

    /**
     * Create and store config adapter object
     * instance by gien params...
     *
     * @param string|File $filename
     * @throws ConfigException
     * @internal param string $configFileType
     */
    private static function setConfigAdapter( File $filename)
    {
//        $filename = dirname(__DIR__)
//            . '/wbengine/Config/Adapter/AdapterConfig'
//            . ucfirst($adapterType)
//            . self::createFileExtension($adapterType);


        if ($filename->exist() == false) {
            throw new ConfigException(
                sprintf('%s->%s: The Configuration file "%s" not found.'
                    , __CLASS__
                    , __FUNCTION__
                    , $filename->getFile()
                )
            );
        }

        if ($filename->isReadable() == false) {
            throw new ConfigException(
                sprintf('%s->%s: The Config file "%s" exist, but probably is not readable.'
                    , __CLASS__
                    , __FUNCTION__
                    , $filename->getFile()
                )
            );
        }

//        $className = self::createClassName($adapterType);
//        if(is_null($configFileType)) {
//            $configFileType = self::_detectConfigTypeByName($filename->getFile());
//        }

        // @todo create more adapters...
        switch (strtolower($filename->getFileExtension()))
        {
            case self::CONFIG_TYPE_ARRAY:

                self::$adapter = (New AdapterArray(include $filename->getFile(), FALSE));

                break;

            default:
                throw new ConfigException(
                    sprintf('%s->%s: Unsupported Config file type "%s". Cannot craete a Config Adapter.'
                        , __CLASS__
                        , __FUNCTION__
                        , $filename->getFileExtension()
                    )
                );
                break;
        }
//        var_dump($configFileType);
//        die(self::CONFIG_TYPE_ARRAY);
    }

    private static function _detectConfigTypeByName($filename)
    {
        $extension = substr(strrchr($filename, '.'), 1);
//        $directory = dirname($filename);
        if(array_key_exists($extension, self::$_supporteConfigTyes)){
            return self::$_supporteConfigTyes[$extension];
        }else{
            throw new ConfigException(sprintf(
                'Unsupported Config file type "%s".',
                $extension
            ));
        }

    }


    /**
     * Create adapter class name
     * @param string $name
     * @return string
     * @throws Exception\RuntimeException
     */
    private static function createClassName($name)
    {
        $className = 'Wbengine\Config\Adapter\AdapterConfig' . $name;

        if (!class_exists($className)) {
            throw new Exception\RuntimeException(__METHOD__ .
                ': Adapter class ' . $className . ' does not exist!');
        }
        return (string)$className;
    }


    /**
     * Try to detect configuraton type by SERVER IP RANGE.
     * Return predefined config name a string.
     *
     * Possible values
     * 0 = CONFIG_TYPE_PRODUCCTION
     * 1 = CONFIG_TYPE_DEVEL
     * 2 = DETECT_ENV_TYPE_BY_IP
     * 3 = DETECT_ENV_TYPE_BY_HOSTNAME
     *
     * @param bool|int $type
     * @return string
     * @ToDo: Detect environments even by more aspects.
     */
    static function autodetectEnvironment($type = FALSE)
    {
        if($type === TRUE)
        {
            return self::CONFIG_FILE_DEVEL;
        }
        elseif($type === FALSE)
        {
            return self::CONFIG_FILE_PRODUCCTION;
        }
        else
        {
            if ($type === self::DETECT_ENV_TYPE_BY_IP)
            {
                if (is_array(self::$_safeIpRanges)) {
                    foreach (self::$_safeIpRanges as $range) {
                        if (Utils::ipInRange($_SERVER[SERVER_ADDR], $range)) {
                            return self::CONFIG_FILE_DEVEL;
                        }
                    }
                    return self::CONFIG_FILE_PRODUCCTION;
                }
            }
            else
            {
                if (is_array(self::$_safeHostKeywords))
                {
                    foreach (self::$_safeHostKeywords as $keyword) {
                        if (Utils::resolveEnvironmentByHostname($_SERVER['SERVER_NAME'], $keyword) == TRUE) {
                            return self::CONFIG_FILE_DEVEL;
                        }
                    }
                    return self::CONFIG_FILE_PRODUCCTION;
                }
            }
        }
    }




    public static function getFoo(){
        return self::getConfigAdapter()->dbAdapterDefinition;
    }


    public static function getCdnPath(){
        return self::getConfigAdapter()->cdnPath;
    }


    public static function getDbCredentials()
    {
        return self::getConfigAdapter()->dbAdapterDefinition;
    }


    public static function getHtmlHeaderCharset()
    {
        return self::getConfigAdapter()->codePage;
    }


    public static function getCssCollection()
    {
        return self::getConfigAdapter()->cssFiles;
    }


    public static function getAdminIpCollection()
    {
        return self::getConfigAdapter()->ipAdmins;
    }


    public static function getIsDebugEnabled()
    {
        return boolval(self::getConfigAdapter()->debug);
    }

    public static function getTemplateDirPath($type)
    {
        return self::getConfigAdapter()->templateDirPath . ucfirst($type) . '/';
    }


    public static function getTimeZone()
    {
        return self::getConfigAdapter()->timeZone;
    }

    public static function minimizeCss(){
        return self::getConfigAdapter()->minimizeCss;
    }

    public static function minimizeHtml(){
        return self::getConfigAdapter()->minimizeHtml;
    }

    public static function minimizejs(){
        return self::getConfigAdapter()->minimizeJs;
    }

    public static function toArray(){
        return self::getConfigAdapter()->toArray;
    }


}
