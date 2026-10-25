<?php

namespace Wbengine\Template;

use Wbengine\Cache;
use Wbengine\I18n;
use Wbengine\PageCache;
use Wbengine\Template\Exception\TemplateException;

/** Fragment storage owned by the template engine, sharing its lazy user context. */
class FragmentCache
{
    private Engine $_parent;

    public function __construct(Engine $parent)
    {
        $this->_parent = $parent;
    }

    public function getParent(): Engine
    {
        return $this->_parent;
    }

    public function render($key, $ttl, callable $render, string $scope = 'public'): string
    {
        if (!in_array($scope, ['public', 'user'], true)) {
            throw new TemplateException('Fragment cache scope must be public or user.');
        }

        $userId = null;
        if ('user' === $scope) {
            PageCache::bypass();
            $userId = $this->getParent()->resolveCacheUserId();
            if (null === $userId) {
                return $this->_render($render);
            }
        }

        if (!Cache::isEnabled() || ('public' === $scope && PageCache::isBypassed())) {
            return $this->_render($render);
        }

        $locale = I18n::isEnabled() ? I18n::locale() : '';
        $cacheKey = 'tpl:v3:' . hash('sha256', serialize([
            Compiler::VERSION, $this->getParent()->getAutoEscape(), $scope, $userId, $locale, Runtime::s($key),
        ]));
        $html = Cache::fetch($cacheKey, $found);
        if ($found) {
            return (string) $html;
        }

        $html = $this->_render($render);
        // A public outer block must not capture a personal inner block or include.
        if ('' !== $html && ('user' === $scope || !PageCache::isBypassed())) {
            Cache::store($cacheKey, $html, $ttl);
        }
        return $html;
    }

    private function _render(callable $render): string
    {
        $level = ob_get_level();
        try {
            return (string) $render();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
