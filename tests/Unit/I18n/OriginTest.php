<?php

namespace Wbengine\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\I18n;
use Wbengine\I18n\Translator;
use Wbengine\PageCache;

class OriginTest extends TestCase
{
    private function configure(array $extra = [], string $locale = 'en'): void
    {
        I18n::configure(
            (new Translator([], 'en'))->setLocale($locale),
            $extra + ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs', 'pt_BR'], 'urlPrefix' => true, 'prefixDefault' => false]
        );
    }

    protected function tearDown(): void
    {
        I18n::configure(null, null);
    }

    public function testTheOriginComesFromTheRequestWhenNothingIsConfigured(): void
    {
        $this->configure();

        $this->assertSame('http://localhost:8080', I18n::origin(['HTTP_HOST' => 'localhost:8080']));
        $this->assertSame('https://example.com', I18n::origin(['HTTP_HOST' => 'Example.COM', 'HTTPS' => 'on']));
        $this->assertSame('http://example.com', I18n::origin(['HTTP_HOST' => 'example.com', 'HTTPS' => 'off']));
    }

    #[DataProvider('hostileHosts')]
    public function testAHostThatIsNotAHostNameIsNotUsed(string $host): void
    {
        $this->configure();

        $this->assertSame('', I18n::origin(['HTTP_HOST' => $host]));
    }

    public static function hostileHosts(): array
    {
        return [
            'markup' => ['"><script>alert(1)</script>'],
            'path' => ['evil.com/path'],
            'userinfo' => ['user@evil.com'],
            'space' => ['evil.com x'],
            'empty' => [''],
            'dash start' => ['-evil.com'],
            'bad port' => ['evil.com:99999999'],
        ];
    }

    public function testAConfiguredBaseUrlWinsOverTheHostHeader(): void
    {
        $this->configure(['baseUrl' => 'https://wbengine.example']);

        $this->assertSame('https://wbengine.example', I18n::origin(['HTTP_HOST' => 'evil.com']));
    }

    public function testHreflangsAreAbsoluteAndHaveAnXDefault(): void
    {
        $this->configure([], 'cs');

        $links = I18n::hreflangs('/docs/', ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);

        $this->assertSame([
            'en' => 'https://example.com/docs/',
            'cs' => 'https://example.com/cs/docs/',
            'pt-BR' => 'https://example.com/pt-BR/docs/',
            'x-default' => 'https://example.com/docs/',
        ], $links);
    }

    public function testNoOriginNoHreflangs(): void
    {
        $this->configure();

        $this->assertSame([], I18n::hreflangs('/docs/', []));
    }

    public function testTheCacheKeyFollowsTheHostOnlyWhenTheHostIsUsed(): void
    {
        $saved = $_SERVER;
        try {
            $_SERVER['HTTP_HOST'] = 'a.example';
            $this->configure();
            $a = PageCache::cacheKey('/docs/');
            $_SERVER['HTTP_HOST'] = 'b.example';
            $b = PageCache::cacheKey('/docs/');

            $this->configure(['baseUrl' => 'https://fixed.example']);
            $fixedB = PageCache::cacheKey('/docs/');
            $_SERVER['HTTP_HOST'] = 'a.example';
            $fixedA = PageCache::cacheKey('/docs/');
        } finally {
            $_SERVER = $saved;
        }

        $this->assertNotSame($a, $b, 'two hosts, two copies');
        $this->assertSame($fixedA, $fixedB, 'a configured address does not depend on the host');
        $this->assertSame('page:en:/docs/', $fixedA);
    }
}
