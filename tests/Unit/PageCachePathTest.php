<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\PageCache;

class PageCachePathTest extends TestCase
{
    #[DataProvider('cacheable')]
    public function testNormalizedPath(string $uri, string $expected): void
    {
        $this->assertSame($expected, PageCache::cacheablePath('GET', $uri));
    }

    public static function cacheable(): array
    {
        return [
            'root'               => ['/', '/'],
            'trailing slash'     => ['/blog/', '/blog/'],
            'no trailing slash'  => ['/blog', '/blog/'],
            'case is ignored'    => ['/Blog/About', '/blog/about/'],
            'dots and dashes'    => ['/a-b_c.d/', '/a-b_c.d/'],
            'percent encoded'    => ['/caf%C3%A9/', '/caf%c3%a9/'],
        ];
    }

    #[DataProvider('notCacheable')]
    public function testNotCacheable(string $method, string $uri): void
    {
        $this->assertNull(PageCache::cacheablePath($method, $uri));
    }

    public static function notCacheable(): array
    {
        return [
            'post'              => ['POST', '/blog/'],
            'head'              => ['HEAD', '/blog/'],
            'put'               => ['PUT', '/blog/'],
            'query string'      => ['GET', '/blog/?x=1'],
            'empty query mark'  => ['GET', '/blog/?'],
            'fragment'          => ['GET', '/blog/#top'],
            'traversal'         => ['GET', '/blog/../etc/'],
            'space'             => ['GET', '/a b/'],
            'quote'             => ['GET', "/a'b/"],
            'angle bracket'     => ['GET', '/<script>/'],
            'backslash'         => ['GET', '/a\\b/'],
            'null byte'         => ['GET', "/a\0b/"],
            'too long'          => ['GET', '/' . str_repeat('a', 250) . '/'],
            'no leading slash'  => ['GET', 'blog/'],
            'absolute url'      => ['GET', 'http://evil.example/blog/'],
        ];
    }
}
