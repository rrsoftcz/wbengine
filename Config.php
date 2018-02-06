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
use Wbengine\Application\ApplicationException;
use Wbengine\Application\Path\File;
use Wbengine\Application\Path\Path;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Config\Adapter\AdapterArray;
use Wbengine\Config\Adapter\AdapterInterface;
use Wbengine\Config\Adapter\Exception\ConfigException;
use Wbengine\Config\Adapter\AdapterAbstract;
use Wbengine\Config\Value;
use Wbengine\Config\Valuex;

class Config
{
    CONST CONFIG_FILE                   = 'Settings.json';
    CONST DETECT_ENV_TYPE_BY_IP         = 2;
    CONST DETECT_ENV_TYPE_BY_HOSTNAME   = 3;

    CONST CONFIG_TYPE_ARRAY             = 'php';
    CONST CONFIG_TYPE_INI               = 'ini';
    CONST CONFIG_TYPE_JASON             = 'json';
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
     * Loaded and stored configuration as array ...
     * @var array
     */
    static $config           = array();


    /**
     * This method ceate config adapter by
     * given adapter type.
     *
     * @see local const
     * @param Application\Path\File|File $configFile
     * @return Config\Adapter\AdapterInterface
     * @throws ConfigException
     */
    public static function load(File $configFile)
    {
        if($configFile->exist() === false){
//            var_dump($configFile->exist());

            throw new ConfigException(
                sprintf('%s->%s: Can not locate configuration file "%s/%s".'
                    , __CLASS__
                    , __FUNCTION__
                    , $configFile->getDirectory()
                    , $configFile->getFileName()
                )
            );
        }
        //@TODO - Define more adapters...
        self::setConfigAdapter($configFile);
    }

    static function addEnvironmentSafeKeyword($keyword){
        self::$_safeHostKeywords[] = $keyword;
    }

    static function addEnvironmentSafeIp($iprange){
        self::$_safeIpRanges[] = $iprange;
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
        if ($filename->exist() === false) {
            throw new ConfigException(
                sprintf('%s->%s: The Configuration file "%s" not found.'
                    , __CLASS__
                    , __FUNCTION__
                    , $filename->getFile()
                )
            );
        }

        if ($filename->isReadable() === false) {
            throw new ConfigException(
                sprintf('%s->%s: The Config file "%s" exist, but probably is not readable.'
                    , __CLASS__
                    , __FUNCTION__
                    , $filename->getFile()
                )
            );
        }

        // @todo create more adapters...
        switch (strtolower($filename->getFileExtension()))
        {
            case self::CONFIG_TYPE_JASON;

                self::$config = json_decode($filename->getContent());

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
    }

    private static function _detectConfigTypeByName($filename)
    {
        $extension = substr(strrchr($filename, '.'), 1);
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


    /**
     * Check whatewer given property object tree
     * exist. If yes, return value.
     *
     * @param $objName string
     * @param $propName string
     * @return mixed
     */
    private static function _getProperty($objName, $propName){
        if(is_object(self::$config)) {
            if(self::$config->$objName) {
                return self::$config->$objName->$propName;
            }else{
                return null;
            }
        }else{
            return null;
        }
    }


    public static function getCdnPath(){
        return self::$config->cdnPath;
    }

    public static function getDbCredentials()
    {
        return new Value(self::$config->dbAdapterDefinition);
    }

    public static function getHtmlHeaderCharset()
    {
        return self::$config->codePage;
    }

    public static function getRendererTemplatesDir()
    {
        return self::_getProperty('renderer','templatesDir');
    }

    public static function getRendererConfigDir()
    {
        return self::_getProperty('renderer','configDir');
    }

    public static function getRendererAdapterName()
    {
        return self::_getProperty('renderer','adapterName');
    }

    public static function getRendererCompiledDir()
    {
        return self::_getProperty('renderer','compiledDir');
    }

    public static function getCssCollection()
    {
        return self::$config->cssFiles;
    }

    public static function getAdminIpCollection()
    {
        return self::$config->ipAdmins;
    }

    public static function isDebugEnabled()
    {
        return self::$config->debug;
    }

    public static function getTimeZone()
    {
        return self::_getValueTimeZone()->asString();
//        return self::$config->timeZone;
    }

    public static function minimizeCss(){
        return self::$config->minimizeCss;
    }

    public static function minimizeHtml(){
        return self::$config->minimizeHtml;
    }

    public static function minimizejs(){
        return self::$config->minimizeJs;
    }

    private static function _getValueTimeZone(){
        return new Value(self::$config->timeZone);
    }

}
