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
        if (empty($pages['enabled']) || !Cache::isEnabled() || Config::isDebugEnabled()) {
            return;
        }

        $path = self::cacheablePath($_SERVER['REQUEST_METHOD'] ?? '', (string) ($_SERVER['REQUEST_URI'] ?? ''));
        if (null === $path || self::_hasPersonalState() || self::_isLoggedIn()) {
            return;
        }

        self::$_ttl = max(1, (int) ($pages['ttl'] ?? self::DEFAULT_TTL));
        self::$_key = 'page:' . $path;
        self::$_path = $path;

        $page = Cache::fetch(self::$_key, $found);
        if ($found && is_array($page) && isset($page['body'], $page['etag'])) {
            self::_serve($page);
        }

        self::$_app = $app;
        ob_start(array(__CLASS__, 'capture'));
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


    private static function _hasPersonalState()
    {
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
            sprintf('SELECT user_id FROM %s WHERE session_id = ? AND session_expire > ? LIMIT 1', S_TABLE_SESSIONS),
            array($sid, time())
        );
        return is_array($row) && (int) $row['user_id'] > 0;
    }


    private static function _serve(array $page)
    {
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
                Cache::store(self::$_key, array('body' => $buffer, 'etag' => '"' . md5($buffer) . '"'), self::$_ttl);
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
        if (200 !== http_response_code() || '' === $buffer || strlen($buffer) > self::MAX_BODY) {
            return false;
        }

        foreach ((array) call_user_func(self::$_headerProvider ?? 'headers_list') as $header) {
            if (0 === stripos($header, 'Location:')) {
                return false;
            }
            if (0 === stripos($header, 'Content-Type:') && false === stripos($header, 'text/html')) {
                return false;
            }
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
}
