<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Abstarct Class for the config adapters
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Config\Adapter;


abstract class AdapterAbstractxxx implements AdapterInterface
{

    /**
     * Config adapter instance
     * @var Config\Adapter\AdapterInterface
     */
    static $adapter         = NULL;

    /**
     * return stored config adapter
     * @return Config\Adapter\AdapterInterface
     */
    public static function getConfigAdapter()
    {
        return self::$adapter;
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

    public static function toArray(){
    self::getConfigAdapter()->toArray;
}
}
