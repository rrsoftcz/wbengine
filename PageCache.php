<?php

namespace Wbengine;

use Wbengine\Application\Application;

/**
 * Whole page cache for anonymous visitors. A hit is served before any box runs, so
 * it costs no database query for a visitor without a session cookie and one cheap
 * lookup for a returning one.
 *
 *   "cache": { "enabled": true, "driver": "apcu", "pages": { "enabled": true, "ttl": 60 } }
 *
 * A page is stored only when everything says it is the same for every visitor:
 *
 *  - a plain GET without a query string, without an Authorization header, not ajax;
 *  - the visitor is not logged in (a session cookie is checked against the database);
 *  - the response is 200 and HTML, and nothing was written to the session (so a page
 *    with a login token or any personal state is never stored);
 *  - the page is a real site of the database and the URL is exactly its link (not a
 *    deeper path that merely falls back to it), which also keeps the number of cache
 *    entries bounded by the number of sites;
 *  - the site does not require a login, and debug is off.
 *
 * Pages whose output depends on anything else (the device type, a language cookie,
 * the time of day) must not be cached this way: leave the page cache off, or extend
 * the key.
 */
class PageCache
{
    const DEFAULT_TTL = 60;
    const MAX_BODY = 1048576;

    /** @var Application|null */
    private static $_app = null;
    private static $_key = null;
    private static $_path = null;
    private static $_ttl = self::DEFAULT_TTL;

    private static $_bypassed = false;

    /** Disable page cache for the remainder of this request. Call before constructing Application to prevent a hit. */
    public static function bypass()
    {
        self::$_bypassed = true;
    }

    /** @var callable|null returns the response headers, headers_list() unless replaced */
    private static $_headerProvider = null;


    /**
     * Replace the source of the response headers. The command line SAPI keeps no headers,
     * so tests need to supply them; in production headers_list() is used.
     */
    public static function setHeaderProvider(?callable $provider)
    {
        self::$_headerProvider = $provider;
    }


    /**
     * Serve the page from the cache and exit, or arrange for the response to be stored.
     * Call once, before the site is initialized. Never throws.
     */
    public static function handle(Application $app)
    {
        try {
            self::_handle($app);
        } catch (\Throwable $e) {
            error_log(__CLASS__ . ': ' . $e->getMessage());
        }
    }


    private static function _handle(Application $app)
    {
        $pages = Config::getCacheSettings()['pages'] ?? null;
        $pages = is_object($pages) ? (array) $pages : (is_array($pages) ? $pages : array());
        if (self::$_bypassed || empty($pages['enabled']) || !Cache::isEnabled() || Config::isDebugEnabled()) {
            return;
        }

        $path = self::cacheablePath($_SERVER['REQUEST_METHOD'] ?? '', (string) ($_SERVER['REQUEST_URI'] ?? ''));
        if (null === $path || !self::isPathAllowed($path, $pages) || self::_hasPersonalState() || self::_isLoggedIn()
            || null === self::_reusableHeaders()) {
            return;
        }

        self::$_ttl = max(1, (int) ($pages['ttl'] ?? self::DEFAULT_TTL));
        self::$_key = self::cacheKey($path);
        self::$_path = $path;

        $page = Cache::fetch(self::$_key, $found);
        if ($found && is_array($page) && 2 === ($page['format'] ?? null)
            && isset($page['body'], $page['etag'], $page['headers']) && is_array($page['headers'])) {
            self::_serve($page);
        }

        self::$_app = $app;
        ob_start(array(__CLASS__, 'capture'));
    }


    /**
     * The cache key of a page. With i18n the locale is part of it: the same URL can be shown
     * in different languages (chosen by cookie or Accept-Language), and a page in one
     * language must never be served to a visitor of another.
     */
    public static function cacheKey($path)
    {
        // The absolute hreflang links of a page contain its host, so without a configured
        // baseUrl a copy belongs to one host: a forged Host header cannot poison the copies of
        // the real one.
        $host = (I18n::originFollowsHost() && '' !== I18n::origin()) ? substr(md5(I18n::origin()), 0, 8) . ':' : '';
        return 'page:' . (I18n::isEnabled() ? I18n::locale() . ':' : '') . $host . $path;
    }


    /**
     * The normalized cache path for a request, or null when the request may not be
     * cached at all: only GET, no query string, a short path of plain characters.
     * "/Blog" and "/blog/" are the same page.
     *
     * @param string $method
     * @param string $uri the request URI
     * @return string|null
     */
    public static function cacheablePath($method, $uri)
    {
        if ('GET' !== $method || false !== strpos($uri, '?') || false !== strpos($uri, '#')) {
            return null;
        }

        $path = strtolower(rtrim($uri, '/')) . '/';
        if (strlen($path) > 200 || !preg_match('#^/[a-z0-9_.~%/-]*$#', $path) || false !== strpos($path, '..')) {
            return null;
        }
        return $path;
    }

    /** Exact canonical paths: optional allowPaths limits caching, denyPaths always wins. */
    public static function isPathAllowed($path, array $pages)
    {
        foreach (array('allowPaths', 'denyPaths') as $name) {
            if (isset($pages[$name]) && !is_array($pages[$name])) {
                return false;
            }
            $paths = array();
            foreach ($pages[$name] ?? array() as $candidate) {
                if (!is_string($candidate) || null === ($normalized = self::cacheablePath('GET', $candidate))) {
                    return false;
                }
                $paths[] = $normalized;
            }
            if ('allowPaths' === $name && isset($pages[$name]) && !in_array($path, $paths, true)) {
                return false;
            }
            if ('denyPaths' === $name && in_array($path, $paths, true)) {
                return false;
            }
        }
        return true;
    }


    private static function _hasPersonalState()
    {
        $knownCookies = array(session_name());
        if (I18n::isEnabled()) {
            $knownCookies[] = Config::getI18nSettings()['cookie'];
        }
        if (array_diff(array_keys($_COOKIE), $knownCookies)) {
            return true;
        }
        return isset($_SERVER['HTTP_AUTHORIZATION'])
            || isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            || isset($_SERVER['PHP_AUTH_USER']);
    }


    /**
     * A visitor with a session cookie is checked against the stored session. Without
     * a cookie nobody can be logged in. Any doubt means "logged in", which only costs
     * a cache miss.
     */
    private static function _isLoggedIn()
    {
        $name = session_name();
        return isset($_COOKIE[$name]) && self::sessionBelongsToAUser($_COOKIE[$name]);
    }


    /**
     * Whether a session cookie value belongs to a logged in user. Anything that is not
     * clearly an anonymous visitor counts as logged in: that only costs a cache miss.
     *
     * @param mixed $sid the value of the session cookie
     * @return bool
     */
    public static function sessionBelongsToAUser($sid)
    {
        if (!is_string($sid) || !preg_match('/^[A-Za-z0-9,-]{16,128}$/', $sid)) {
            return true;
        }

        $row = Db::fetchAssoc(
            sprintf('SELECT user_id, session_data FROM %s WHERE session_id = ? AND session_expire > ? LIMIT 1', S_TABLE_SESSIONS),
            array($sid, time())
        );
        return is_array($row) && ((int) $row['user_id'] > 0
            || $row['session_data'] !== serialize(new \stdClass()));
    }


    private static function _serve(array $page)
    {
        $current = array();
        foreach (headers_list() as $header) {
            $current[strtolower(strtok($header, ':'))] = true;
        }
        foreach ($page['headers'] as $header) {
            // Headers set by this deployment take precedence over cached policy.
            if (!isset($current[strtolower(strtok($header, ':'))])) {
                header($header, false);
            }
        }
        header('Content-Type: text/html; charset=UTF-8');
        header('X-WBengine-Cache: HIT');
        header('ETag: ' . $page['etag']);

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $page['etag']) {
            http_response_code(304);
            exit;
        }

        echo $page['body'];
        exit;
    }


    /**
     * Output buffer callback: store the finished response when it is safe to.
     */
    public static function capture($buffer)
    {
        try {
            if (self::_mayStore($buffer)) {
                Cache::store(self::$_key, array('body' => $buffer, 'etag' => '"' . md5($buffer) . '"',
                    'headers' => self::_reusableHeaders(), 'format' => 2), self::$_ttl);
                self::_mark('MISS-STORED');
            } else {
                self::_mark('BYPASS');
            }
        } catch (\Throwable $e) {
            error_log(__CLASS__ . ': ' . $e->getMessage());
        }
        return $buffer;
    }


    private static function _mark($state)
    {
        if (!headers_sent()) {
            header('X-WBengine-Cache: ' . $state);
        }
    }


    private static function _mayStore($buffer)
    {
        if (self::$_bypassed || 200 !== http_response_code() || '' === $buffer || strlen($buffer) > self::MAX_BODY) {
            return false;
        }

        if (null === self::_reusableHeaders()) {
            return false;
        }

        $app = self::$_app;
        if (!$app instanceof Application || $app->hasModifiedSession()) {
            return false;
        }

        $site = $app->getSite();
        if (!$site->isLoaded() || (int) $site->acl >= 1) {
            return false;
        }

        // Exactly the site's own URL, not a deeper path that falls back to it.
        return strtolower(rtrim((string) $site->getLink(), '/')) . '/' === self::$_path;
    }

    /** Return repeatable end-to-end headers, or null when shared storage is unsafe. */
    private static function _reusableHeaders()
    {
        $headers = array();
        $allowed = array('content-type', 'content-language', 'cache-control', 'vary',
            'content-security-policy', 'content-security-policy-report-only', 'x-frame-options',
            'x-content-type-options', 'referrer-policy', 'permissions-policy', 'strict-transport-security',
            'cross-origin-opener-policy', 'cross-origin-embedder-policy', 'cross-origin-resource-policy',
            'origin-agent-cluster');
        foreach ((array) call_user_func(self::$_headerProvider ?? 'headers_list') as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (in_array($name, array('location', 'set-cookie'), true)
                || ('content-type' === $name && !preg_match('#^text/html(?:\s*;|$)#i', $value))
                || ('cache-control' === $name && preg_match('/(?:^|,)\s*(?:private|no-store|no-cache)(?:\s*(?:=|,|$))/i', $value))
                || (str_starts_with($name, 'content-security-policy') && stripos($value, "'nonce-") !== false)) {
                return null;
            }
            if ('vary' === $name) {
                foreach (explode(',', strtolower($value)) as $variant) {
                    if (!I18n::isEnabled() || !in_array(trim($variant), array('accept-language', 'cookie'), true)) {
                        return null;
                    }
                }
            }
            if (in_array($name, $allowed, true)) {
                $headers[] = $header;
            }
        }
        return $headers;
    }
}
