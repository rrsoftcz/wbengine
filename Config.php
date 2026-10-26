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
use Wbengine\Config\Adapter\Exception\ConfigException;
use Wbengine\Config\Value;

class Config
{
    CONST CONFIG_FILE                   = 'Settings.json';

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
            case self::CONFIG_TYPE_JASON:

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

    /**
     * Whether Smarty checks templates for changes on every request.
     * Defaults to true, set "renderer.compileCheck": false in production.
     * @return bool
     */
    public static function getRendererCompileCheck()
    {
        $renderer = (is_object(self::$config) && isset(self::$config->renderer)) ? self::$config->renderer : null;
        return (is_object($renderer) && isset($renderer->compileCheck)) ? (bool) $renderer->compileCheck : true;
    }

    /**
     * Whether the renderer HTML-escapes output by default. Defaults to true for both adapters.
     * Set "renderer.autoEscape": false for old templates that print raw HTML as {$value}.
     * @return bool
     */
    public static function getRendererAutoEscape()
    {
        $renderer = (is_object(self::$config) && isset(self::$config->renderer)) ? self::$config->renderer : null;
        return (is_object($renderer) && isset($renderer->autoEscape)) ? (bool) $renderer->autoEscape : true;
    }

    public static function getRendererCompiledDir()
    {
        return self::_getProperty('renderer','compiledDir');
    }

    public static function getCssCollection()
    {
        return self::$config->cssFiles;
    }

    /**
     * Internationalisation settings with their defaults:
     *   enabled (false), default ("en"), locales (["en"]), detect (["url", "cookie", "header"],
     *   in this order), urlPrefix (true), prefixDefault (false), cookie ("wb_locale"),
     *   cookieDays (365), dir ("/Locale/", relative to the application directory).
     * Configure under "i18n".
     * @return array
     */
    public static function getI18nSettings()
    {
        $custom = (is_object(self::$config) && isset(self::$config->i18n) && is_object(self::$config->i18n))
            ? (array) self::$config->i18n
            : array();

        $settings = array_merge(array(
            'enabled' => false,
            'default' => 'en',
            'locales' => array('en'),
            'detect' => array('url', 'cookie', 'header'),
            'urlPrefix' => true,
            'prefixDefault' => false,
            'cookie' => 'wb_locale',
            'cookieDays' => 365,
            'dir' => '/Locale/',
            'baseUrl' => '',
        ), $custom);

        $settings['enabled'] = (bool) $settings['enabled'];
        $settings['urlPrefix'] = (bool) $settings['urlPrefix'];
        $settings['prefixDefault'] = (bool) $settings['prefixDefault'];
        $settings['cookieDays'] = max(1, (int) $settings['cookieDays']);
        $settings['cookie'] = preg_match('/^[A-Za-z0-9_]{1,40}$/', (string) $settings['cookie']) ? (string) $settings['cookie'] : 'wb_locale';
        $settings['dir'] = (string) $settings['dir'];
        // "https://example.com": the public address, for the absolute hreflang links.
        $base = rtrim((string) $settings['baseUrl'], '/');
        $settings['baseUrl'] = preg_match('~^https?://[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?(:[0-9]{1,5})?$~', $base) ? $base : '';

        $default = I18n\Translator::normalize($settings['default']) ?? 'en';
        $locales = array();
        foreach ((array) $settings['locales'] as $locale) {
            $normalized = I18n\Translator::normalize($locale);
            if (null !== $normalized) {
                $locales[$normalized] = $normalized;
            }
        }
        $locales[$default] = $default;
        $settings['default'] = $default;
        $settings['locales'] = array_values($locales);

        $detect = array_values(array_intersect((array) $settings['detect'], array('url', 'cookie', 'header')));
        $settings['detect'] = $detect;
        return $settings;
    }

    /**
     * View counter batching: enabled (false), hits (50), seconds (60), dir.
     * Configure under "counters": { "views": { ... } }. When disabled every view is
     * written to the database immediately.
     * @return array
     */
    public static function getViewCounterSettings()
    {
        $custom = (is_object(self::$config) && isset(self::$config->counters->views) && is_object(self::$config->counters->views))
            ? (array) self::$config->counters->views
            : array();

        $db = self::getDbCredentials();
        $settings = array_merge(array(
            'enabled' => false,
            'hits' => 50,
            'seconds' => 60,
            'dir' => sys_get_temp_dir() . '/wbengine-counters-' . substr(hash('sha256', $db->hostname . '/' . $db->database), 0, 8),
        ), $custom);

        $settings['enabled'] = (bool) $settings['enabled'];
        $settings['hits'] = max(1, (int) $settings['hits']);
        $settings['seconds'] = max(1, (int) $settings['seconds']);
        return $settings;
    }

    /**
     * Login rate limit settings with their defaults:
     *   enabled (true),
     *   maxAttempts per client IP and account together (5),
     *   ipMaxAttempts per client IP over all accounts (20),
     *   accountMaxAttempts per account from all IPs (25, catches distributed guessing),
     *   window in seconds (900), dir for the counter files.
     * Configure under "rateLimit": { "login": { ... } }.
     * @return array
     */
    public static function getLoginRateLimit()
    {
        $custom = (is_object(self::$config) && isset(self::$config->rateLimit->login) && is_object(self::$config->rateLimit->login))
            ? (array) self::$config->rateLimit->login
            : array();

        $db = self::getDbCredentials();
        $defaults = array(
            'enabled' => true,
            'maxAttempts' => 5,
            'ipMaxAttempts' => 20,
            'accountMaxAttempts' => 25,
            'window' => 900,
            'dir' => sys_get_temp_dir() . '/wbengine-ratelimit-' . substr(hash('sha256', $db->hostname . '/' . $db->database), 0, 8),
        );

        $settings = array_merge($defaults, $custom);
        $settings['enabled'] = (bool) $settings['enabled'];
        foreach (array('maxAttempts', 'ipMaxAttempts', 'accountMaxAttempts', 'window') as $key) {
            $settings[$key] = max(1, (int) $settings[$key]);
        }
        return $settings;
    }

    /** Explicit opt-in to forwarded connection metadata, disabled by default. */
    public static function getHttpSettings(): array
    {
        $http = is_object(self::$config) ? (self::$config->http ?? null) : null;
        if (null !== $http && !is_object($http)) {
            throw new \InvalidArgumentException('The http configuration must be an object.');
        }
        $proxies = $http->trustedProxies ?? [];
        $headers = $http->forwardedHeaders ?? [];
        if (!is_array($proxies) || !is_array($headers)) {
            throw new \InvalidArgumentException('http.trustedProxies and http.forwardedHeaders must be arrays.');
        }
        foreach ($headers as $header) {
            if (!in_array($header, ['x-forwarded-for', 'x-forwarded-proto'], true)) {
                throw new \InvalidArgumentException('Unsupported trusted forwarded header.');
            }
        }
        return ['trustedProxies' => $proxies, 'forwardedHeaders' => $headers];
    }

    /**
     * Cache settings: array with enabled, driver, ttl, dir. Disabled when not configured.
     * @return array
     */
    public static function getCacheSettings()
    {
        $cache = (is_object(self::$config) && isset(self::$config->cache)) ? self::$config->cache : null;
        return is_object($cache) ? (array) $cache : array('enabled' => false);
    }

    /**
     * Return JWT signing keys as array('access' => string, 'refresh' => string).
     * Environment variables WBENGINE_JWT_KEY and WBENGINE_JWT_REFRESH_KEY take precedence
     * over the "jwt" section of the settings file. Missing keys are returned as null.
     * @return array
     */
    public static function getJwtKeys()
    {
        $jwt = (is_object(self::$config) && isset(self::$config->jwt) && is_object(self::$config->jwt))
            ? self::$config->jwt
            : new \stdClass();

        $access = getenv('WBENGINE_JWT_KEY');
        $refresh = getenv('WBENGINE_JWT_REFRESH_KEY');

        return array(
            'access' => ($access !== false && $access !== '') ? $access : ($jwt->accessKey ?? null),
            'refresh' => ($refresh !== false && $refresh !== '') ? $refresh : ($jwt->refreshKey ?? null),
        );
    }

    /**
     * Return the reCAPTCHA secret key or null when it is not configured.
     * Environment variable WBENGINE_RECAPTCHA_SECRET takes precedence over "recaptcha.secretKey".
     * @return string|null
     */
    public static function getRecaptchaSecret()
    {
        $env = getenv('WBENGINE_RECAPTCHA_SECRET');
        if ($env !== false && $env !== '') {
            return $env;
        }
        return (is_object(self::$config) && isset(self::$config->recaptcha->secretKey))
            ? self::$config->recaptcha->secretKey
            : null;
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

    /** @deprecated Since 4.0, Renderer::dispatch ignores this legacy setting. */
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
