<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 22.09.17
 * Time: 19:05
 */

namespace Wbengine\Application;


use Wbengine\Application\Env\Stac\Utils;

Abstract class Env
{
    CONST DETECT_ENV_TYPE_BY_HOSTNAME   = 3;
    CONST DETECT_ENV_TYPE_BY_IP         = 2;
    CONST ENV_PRODUCTION                = 1;
    CONST ENV_DEVEL                     = 0;

    /**
     * Safe IP NOT production Subnets
     * We try detect devel OR production by IP
     * @var array
     */
    private static $_safeIpRanges   = array
    (
        '127.0.0.1/0',
        '10.0.0.0/16',
        '172.16.0.0/16',
        '192.0.0.0/16',
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
     * Try to detect configuraton type by SERVER IP RANGE.
     * Return predefined config name a string.
     *
     * Possible values
     * 1 = DETECT_ENV_TYPE_BY_IP
     * 2 = DETECT_ENV_TYPE_BY_HOSTNAME
     *
     * @param int $type
     * @return boolean
     * @ToDo: Detect environments even by more aspects.
     */
    static function autodetectEnvironment($type = null)
    {
        switch ($type){
            case self::DETECT_ENV_TYPE_BY_IP:
                if (is_array(self::$_safeIpRanges)) {
                    foreach (self::$_safeIpRanges as $range) {
                        if (Utils::ipInRange($_SERVER['SERVER_ADDR'], $range)) {
                            return self::ENV_DEVEL;die(dd);
                        }
                    }
                    return self::ENV_PRODUCTION;
                }else{
                    return self::ENV_PRODUCTION;
                }

            case self::DETECT_ENV_TYPE_BY_HOSTNAME:
                if (is_array(self::$_safeHostKeywords))
                {
                    foreach (self::$_safeHostKeywords as $keyword) {
                        if (Utils::resolveEnvironmentByHostname($_SERVER['SERVER_NAME'], $keyword) == true) {
                            return self::ENV_DEVEL;
                        }
                    }
                }
            default:
                    return self::ENV_PRODUCTION;

        }
    }

}