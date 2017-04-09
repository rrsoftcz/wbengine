<?php

/**
 *
 * @author roza
 */

namespace Wbengine\Config\Adapter;

interface AdapterInterface {


    public static function getDbCredentials();

    public static function getHtmlHeaderCharset();

    public static function getCssCollection();

    public static function getAdminIpCollection();

    public static function getIsDebugEnabled();

    public static function getTemplateDirPath($type);

    public static function getTimeZone();

    public static function getCdnPath();

    public static function minimizeCss();

    public static function minimizeHtml();

    public static function minimizeJs();

    public static function toArray();
}
