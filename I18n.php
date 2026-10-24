<?php

namespace Wbengine;

use Wbengine\I18n\Format;
use Wbengine\I18n\LocaleResolver;
use Wbengine\I18n\Translator;

/**
 * Internationalisation of the user interface: the locale of the request, the translated
 * texts, and the URLs of the other languages.
 *
 * It is off by default. Switch it on in Settings.json:
 *
 *   "i18n": {
 *       "enabled": true,
 *       "default": "en",
 *       "locales": ["en", "cs"],
 *       "detect": ["url", "cookie", "header"],
 *       "urlPrefix": true,
 *       "prefixDefault": false,
 *       "dir": "/Locale/"
 *   }
 *
 * The locale of a request is chosen in the order given by "detect": a language prefix in
 * the URL ("/cs/blog/"), the cookie that remembers the last choice, then the
 * Accept-Language header, then the default. The prefix is removed from the URL before the
 * rest of the framework sees it, so sites, boxes and routes are written without it.
 */
class I18n
{
    /** @var Translator|null */
    private static $_translator = null;
    private static $_settings = null;
    private static $_locale = null;
    private static $_booted = false;
    private static $_cookieWriter = null;

    // ---------------------------------------------------------------- setup

    /**
     * Called once by the Application, before anything reads the request URL.
     *
     * @param string $appBaseDir the application directory, where "dir" is relative to
     * @param array|null $server the request data, $_SERVER when null (for tests)
     * @param array|null $cookies $_COOKIE when null
     * @return string|null a URL to redirect to, when the request must be redirected
     */
    public static function boot($appBaseDir, ?array $server = null, ?array $cookies = null)
    {
        $server = $server ?? $_SERVER;
        $cookies = $cookies ?? $_COOKIE;

        self::$_booted = true;
        self::$_settings = Config::getI18nSettings();
        self::$_translator = null;
        self::$_locale = null;

        if (!self::$_settings['enabled']) {
            return null;
        }

        $translator = self::_createTranslator($appBaseDir);
        $result = self::resolve($server, $cookies, self::$_settings);

        self::$_locale = $result['locale'];
        $translator->setLocale($result['locale']);

        if ($result['remember']) {
            self::_remember($result['locale'], $server);
        }
        if (null !== $result['redirect']) {
            return $result['redirect'];
        }
        if ($result['prefixed']) {
            self::_rewriteRequest($result['path'], $result['query']);
        }
        self::_sendHeaders($result['locale'], self::$_settings);
        return null;
    }

    /**
     * Decide the locale of a request without touching any global state.
     *
     * An explicit language prefix in the URL always wins. Otherwise the sources in "detect"
     * are tried in order, then the default.
     *
     * @param array $server REQUEST_URI, REQUEST_METHOD, HTTP_ACCEPT_LANGUAGE
     * @param array $cookies
     * @param array $settings as returned by Config::getI18nSettings()
     * @return array locale, path (without the prefix), query, prefixed (the URL had a prefix),
     *         remember (the choice should be stored in the cookie), redirect (a URL or null)
     */
    public static function resolve(array $server, array $cookies, array $settings)
    {
        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $query = (string) parse_url($uri, PHP_URL_QUERY);
        $supported = $settings['locales'];
        $detect = $settings['detect'];

        $fromUrl = null;
        if ($settings['urlPrefix'] && in_array('url', $detect, true)) {
            list($fromUrl, $stripped) = LocaleResolver::fromPath($path, $supported);
            if (null !== $fromUrl) {
                $path = $stripped;
            }
        }

        $locale = $fromUrl;
        foreach ($detect as $source) {
            if (null !== $locale) {
                break;
            }
            if ('cookie' === $source) {
                $locale = LocaleResolver::fromValue($cookies[$settings['cookie']] ?? null, $supported);
            } elseif ('header' === $source) {
                $locale = LocaleResolver::fromAcceptLanguage($server['HTTP_ACCEPT_LANGUAGE'] ?? null, $supported);
            }
        }
        $locale = $locale ?? $settings['default'];

        $prefixed = null !== $fromUrl;
        $remember = $prefixed
            && in_array('cookie', $detect, true)
            && (LocaleResolver::fromValue($cookies[$settings['cookie']] ?? null, $supported) !== $locale);

        // The default language has one canonical URL: the one without the prefix.
        $redirect = null;
        if ($prefixed && $locale === $settings['default'] && !$settings['prefixDefault']
            && in_array($server['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true)) {
            $redirect = $path . ('' !== $query ? '?' . $query : '');
        }

        return array('locale' => $locale, 'path' => $path, 'query' => $query, 'prefixed' => $prefixed, 'remember' => $remember, 'redirect' => $redirect);
    }

    /**
     * Make the request look as if the prefix had never been there.
     */
    private static function _rewriteRequest($path, $query)
    {
        $_SERVER['REQUEST_URI'] = $path . ('' !== $query ? '?' . $query : '');
        if (isset($_REQUEST['link'])) {
            // The web server rewrite passes the path as ?link=cs/blog/
            list(, $stripped) = LocaleResolver::fromPath('/' . ltrim((string) $_REQUEST['link'], '/'), self::$_settings['locales']);
            $link = ltrim($stripped, '/');
            $_REQUEST['link'] = $link;
            $_GET['link'] = $link;
        }
    }

    private static function _sendHeaders($locale, array $settings)
    {
        if (headers_sent()) {
            return;
        }
        header('Content-Language: ' . str_replace('_', '-', $locale));
        $vary = array();
        if (in_array('header', $settings['detect'], true)) {
            $vary[] = 'Accept-Language';
        }
        if (in_array('cookie', $settings['detect'], true)) {
            $vary[] = 'Cookie';
        }
        if ($vary) {
            header('Vary: ' . implode(', ', $vary), false);
        }
    }

    private static function _remember($locale, array $server)
    {
        $s = self::$_settings;
        $options = array(
            'expires' => time() + $s['cookieDays'] * 86400,
            'path' => '/',
            'secure' => !empty($server['HTTPS']) && 'off' !== $server['HTTPS'],
            'httponly' => true,
            'samesite' => 'Lax',
        );
        if (null !== self::$_cookieWriter) {
            call_user_func(self::$_cookieWriter, $s['cookie'], $locale, $options);
        } elseif (!headers_sent()) {
            setcookie($s['cookie'], $locale, $options);
        }
    }

    private static function _createTranslator($appBaseDir)
    {
        $dirs = array(__DIR__ . '/I18n/messages');
        $dir = self::$_settings['dir'];
        $dirs[] = rtrim((string) $appBaseDir, '/') . '/' . trim($dir, '/');
        return self::$_translator = new Translator($dirs, self::$_settings['default']);
    }

    // ----------------------------------------------------------------- state

    public static function isEnabled()
    {
        return null !== self::$_settings && !empty(self::$_settings['enabled']);
    }

    /**
     * The locale of the request, for example "cs" or "pt_BR".
     */
    public static function locale()
    {
        return self::translator()->getLocale();
    }

    /**
     * The language of the request: "cs" for "cs_CZ".
     */
    public static function language()
    {
        return self::translator()->getLanguage();
    }

    public static function locales()
    {
        return self::isEnabled() ? self::$_settings['locales'] : array(self::translator()->getDefaultLocale());
    }

    /**
     * Change the locale for the rest of the request, for example from a user's setting.
     */
    public static function setLocale($locale)
    {
        $match = LocaleResolver::match($locale, self::locales());
        if (null !== $match) {
            self::$_locale = $match;
            self::translator()->setLocale($match);
        }
    }

    /**
     * The translator. Without the i18n setting it still serves the framework's own
     * messages in English, so bundled boxes and templates work either way.
     */
    public static function translator()
    {
        if (null === self::$_translator) {
            self::$_translator = new Translator(array(__DIR__ . '/I18n/messages'), 'en');
        }
        return self::$_translator;
    }

    /**
     * For tests and custom set-ups.
     */
    public static function configure(?Translator $translator, ?array $settings = null)
    {
        self::$_translator = $translator;
        self::$_settings = $settings;
        self::$_locale = $translator ? $translator->getLocale() : null;
        self::$_cookieWriter = null;
    }

    public static function setCookieWriter(?callable $writer)
    {
        self::$_cookieWriter = $writer;
    }

    // ------------------------------------------------------------- translation

    /**
     * @param string $key
     * @param array $params replacements for %name% placeholders
     * @param int|float|null $count selects the plural form and fills %count%
     * @return string
     */
    public static function t($key, array $params = array(), $count = null)
    {
        return self::translator()->translate($key, $params, $count);
    }

    // ------------------------------------------------------------------- URLs

    /**
     * A site-relative URL in a locale: "/blog/" becomes "/cs/blog/". Anything that is not a
     * path of this site ("https://...", "//cdn", "#top", "mailto:", "?x=1") is returned as is.
     * The default locale has no prefix unless "prefixDefault" is set.
     */
    public static function url($path, $locale = null)
    {
        $path = (string) $path;
        if (!self::isEnabled() || !self::$_settings['urlPrefix'] || '' === $path || '/' !== $path[0] || (isset($path[1]) && '/' === $path[1])) {
            return $path;
        }

        $locale = null === $locale ? self::locale() : (LocaleResolver::match($locale, self::$_settings['locales']) ?? self::locale());
        if ($locale === self::$_settings['default'] && !self::$_settings['prefixDefault']) {
            return $path;
        }

        $prefix = '/' . str_replace('_', '-', $locale);
        return $prefix . ('/' === $path ? '/' : $path);
    }

    /**
     * The URL of the current page in every locale, for a language switcher and hreflang
     * links. The path is the one without a language prefix.
     *
     * @param string $path
     * @return array locale => url
     */
    public static function alternates($path)
    {
        $urls = array();
        foreach (self::locales() as $locale) {
            $urls[$locale] = self::url($path, $locale);
        }
        return $urls;
    }

    /**
     * The public address of the site, "https://example.com", for links that must be absolute.
     * The "baseUrl" setting wins. Without it the address comes from the request (the Host
     * header, which a client controls, so it is accepted only when it looks like a host name)
     * and the scheme of the connection. An empty string when neither is known.
     *
     * @param array|null $server defaults to $_SERVER
     * @return string
     */
    public static function origin(?array $server = null)
    {
        $configured = (string) (self::$_settings['baseUrl'] ?? '');
        if ('' !== $configured) {
            return $configured;
        }
        $server = $server ?? $_SERVER;
        $host = strtolower((string) ($server['HTTP_HOST'] ?? ''));
        if (!preg_match('/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?(:[0-9]{1,5})?$/', $host)) {
            return '';
        }
        $https = !empty($server['HTTPS']) && 'off' !== strtolower((string) $server['HTTPS']);
        return ($https ? 'https://' : 'http://') . $host;
    }

    /**
     * True when the origin is taken from the request, so a cached page depends on the host.
     */
    public static function originFollowsHost()
    {
        return self::isEnabled() && '' === (string) (self::$_settings['baseUrl'] ?? '');
    }

    /**
     * The absolute URL of the current page in every locale, plus "x-default" (the default
     * language), for <link rel="alternate" hreflang>. Search engines and Lighthouse refuse
     * relative hreflang URLs. Empty when the origin is not known.
     *
     * @param string $path the path without a language prefix
     * @param array|null $server defaults to $_SERVER
     * @return array code => url
     */
    public static function hreflangs($path, ?array $server = null)
    {
        $origin = self::origin($server);
        if ('' === $origin) {
            return array();
        }
        $urls = array();
        foreach (self::alternates($path) as $locale => $url) {
            $urls[str_replace('_', '-', $locale)] = $origin . $url;
        }
        $urls['x-default'] = $origin . self::url($path, self::translator()->getDefaultLocale());
        return $urls;
    }

    // --------------------------------------------------------------- formatting

    public static function number($value, $decimals = 0)
    {
        return Format::number($value, $decimals, self::locale());
    }

    public static function date($value, $style = 'medium')
    {
        return Format::date($value, $style, self::locale(), self::translator());
    }
}
