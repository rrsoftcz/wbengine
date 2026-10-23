<?php

namespace Wbengine\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Config;
use Wbengine\I18n;
use Wbengine\I18n\Translator;

class I18nFacadeTest extends TestCase
{
    private array $settings;
    private array $serverBackup;
    private array $requestBackup;

    protected function setUp(): void
    {
        $this->settings = [
            'enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs', 'pt_BR'],
            'detect' => ['url', 'cookie', 'header'], 'urlPrefix' => true, 'prefixDefault' => false,
            'cookie' => 'wb_locale', 'cookieDays' => 30, 'dir' => '/Locale/',
        ];
        $this->serverBackup = $_SERVER;
        $this->requestBackup = [$_REQUEST, $_GET];
    }

    protected function tearDown(): void
    {
        if (false !== $this->previousConfig) {
            (new \ReflectionProperty(Config::class, 'config'))->setValue(null, $this->previousConfig);
            $this->previousConfig = false;
        }
        $_SERVER = $this->serverBackup;
        [$_REQUEST, $_GET] = $this->requestBackup;
        I18n::configure(null, null);
    }

    private function resolve(string $uri, array $cookies = [], string $accept = '', array $overrides = [], string $method = 'GET'): array
    {
        $server = ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => $method];
        if ('' !== $accept) {
            $server['HTTP_ACCEPT_LANGUAGE'] = $accept;
        }
        return I18n::resolve($server, $cookies, $overrides + $this->settings);
    }

    // ------------------------------------------------------------ resolution

    public function testTheUrlPrefixWinsOverEverythingElse(): void
    {
        $r = $this->resolve('/cs/blog/', ['wb_locale' => 'pt_BR'], 'en');

        $this->assertSame('cs', $r['locale']);
        $this->assertSame('/blog/', $r['path']);
        $this->assertTrue($r['prefixed']);
    }

    public function testThenTheCookieThenTheHeaderThenTheDefault(): void
    {
        $this->assertSame('cs', $this->resolve('/blog/', ['wb_locale' => 'cs'], 'pt-BR')['locale']);
        $this->assertSame('pt_BR', $this->resolve('/blog/', [], 'pt-BR,en;q=0.5')['locale']);
        $this->assertSame('en', $this->resolve('/blog/')['locale']);
        $this->assertSame('en', $this->resolve('/blog/', ['wb_locale' => 'xx'], 'fr,de')['locale'], 'unsupported values are ignored');
    }

    public function testTheDetectOrderIsRespectedForTheNonUrlSources(): void
    {
        $r = $this->resolve('/blog/', ['wb_locale' => 'cs'], 'pt-BR', ['detect' => ['header', 'cookie']]);

        $this->assertSame('pt_BR', $r['locale']);
    }

    public function testASourceThatIsNotListedIsNotUsed(): void
    {
        $this->assertSame('en', $this->resolve('/blog/', ['wb_locale' => 'cs'], 'cs', ['detect' => ['url']])['locale']);
        $this->assertSame('cs', $this->resolve('/blog/', [], 'cs', ['detect' => ['header']])['locale']);
    }

    public function testWithoutUrlPrefixesAPrefixIsJustAPath(): void
    {
        $r = $this->resolve('/cs/blog/', [], '', ['urlPrefix' => false]);

        $this->assertSame('en', $r['locale']);
        $this->assertSame('/cs/blog/', $r['path']);
        $this->assertFalse($r['prefixed']);
    }

    public function testTheQueryStringIsKept(): void
    {
        $r = $this->resolve('/cs/blog/?page=2&x=%20y');

        $this->assertSame('/blog/', $r['path']);
        $this->assertSame('page=2&x=%20y', $r['query']);
    }

    public function testAnExplicitChoiceIsRemembered(): void
    {
        $this->assertTrue($this->resolve('/cs/')['remember']);
        $this->assertTrue($this->resolve('/cs/', ['wb_locale' => 'en'])['remember']);
        $this->assertFalse($this->resolve('/cs/', ['wb_locale' => 'cs'])['remember'], 'already stored');
        $this->assertFalse($this->resolve('/blog/', [], 'cs')['remember'], 'a header guess is not remembered');
        $this->assertFalse($this->resolve('/cs/', [], '', ['detect' => ['url']])['remember'], 'no cookie source, no cookie');
    }

    public function testTheDefaultLanguageHasOneCanonicalUrl(): void
    {
        $this->assertSame('/blog/', $this->resolve('/en/blog/')['redirect']);
        $this->assertSame('/', $this->resolve('/en/')['redirect']);
        $this->assertSame('/blog/?a=1', $this->resolve('/en/blog/?a=1')['redirect']);
        $this->assertNull($this->resolve('/cs/blog/')['redirect']);
        $this->assertNull($this->resolve('/en/blog/', [], '', ['prefixDefault' => true])['redirect'], 'unless the prefix is wanted');
    }

    public function testOnlySafeRequestsAreRedirected(): void
    {
        $this->assertNull($this->resolve('/en/login/', [], '', [], 'POST')['redirect'], 'a redirect would drop the form data');
        $this->assertSame('/login/', $this->resolve('/en/login/', [], '', [], 'HEAD')['redirect']);
    }

    // ------------------------------------------------------------------- boot

    public function testBootRewritesTheRequestForTheRestOfTheFramework(): void
    {
        $this->bootWith(['enabled' => true, 'locales' => ['en', 'cs']]);
        $_SERVER['REQUEST_URI'] = '/cs/blog/?p=1';
        $_REQUEST['link'] = $_GET['link'] = 'cs/blog/';
        $cookies = [];
        I18n::setCookieWriter(function ($n, $v, $o) use (&$cookies) { $cookies[] = [$n, $v, $o]; });

        $redirect = I18n::boot(sys_get_temp_dir(), $_SERVER, []);

        $this->assertNull($redirect);
        $this->assertSame('/blog/?p=1', $_SERVER['REQUEST_URI']);
        $this->assertSame('blog/', $_REQUEST['link']);
        $this->assertSame('blog/', $_GET['link']);
        $this->assertSame('cs', I18n::locale());
        $this->assertSame('wb_locale', $cookies[0][0]);
        $this->assertSame('cs', $cookies[0][1]);
        $this->assertSame('Lax', $cookies[0][2]['samesite']);
        $this->assertTrue($cookies[0][2]['httponly']);
        $this->assertGreaterThan(time() + 29 * 86400, $cookies[0][2]['expires']);
    }

    public function testBootDoesNothingWhenDisabled(): void
    {
        $this->bootWith(['enabled' => false]);
        $_SERVER['REQUEST_URI'] = '/cs/blog/';

        $this->assertNull(I18n::boot(sys_get_temp_dir(), $_SERVER, []));

        $this->assertSame('/cs/blog/', $_SERVER['REQUEST_URI']);
        $this->assertFalse(I18n::isEnabled());
        $this->assertSame('en', I18n::locale());
    }

    public function testBootReturnsTheRedirectForTheDefaultPrefix(): void
    {
        $this->bootWith(['enabled' => true, 'locales' => ['en', 'cs']]);
        $_SERVER['REQUEST_URI'] = '/en/blog/';
        I18n::setCookieWriter(fn () => null);

        $this->assertSame('/blog/', I18n::boot(sys_get_temp_dir(), $_SERVER, []));
    }

    private $previousConfig = false;

    /** Config::$config is the static that the settings are read from. */
    private function bootWith(array $i18n): void
    {
        $property = new \ReflectionProperty(Config::class, 'config');
        if (false === $this->previousConfig) {
            $this->previousConfig = $property->getValue();
        }
        $config = clone $this->previousConfig;
        $config->i18n = (object) $i18n;
        $property->setValue(null, $config);
    }

    // ---------------------------------------------------------------- URLs

    private function configured(string $locale = 'en'): void
    {
        I18n::configure((new Translator([], 'en'))->setLocale($locale), $this->settings);
    }

    #[DataProvider('urls')]
    public function testUrl(string $current, string $input, ?string $locale, string $expected): void
    {
        $this->configured($current);

        $this->assertSame($expected, I18n::url($input, $locale));
    }

    public static function urls(): array
    {
        return [
            'default has no prefix'   => ['en', '/blog/', null, '/blog/'],
            'czech is prefixed'       => ['cs', '/blog/', null, '/cs/blog/'],
            'explicit locale'         => ['en', '/blog/', 'cs', '/cs/blog/'],
            'root'                    => ['cs', '/', null, '/cs/'],
            'region uses a dash'      => ['en', '/x/', 'pt_BR', '/pt-BR/x/'],
            'query kept'              => ['cs', '/blog/?a=1', null, '/cs/blog/?a=1'],
            'absolute url untouched'  => ['cs', 'https://example.com/x', null, 'https://example.com/x'],
            'protocol relative'       => ['cs', '//cdn.example.com/x', null, '//cdn.example.com/x'],
            'anchor untouched'        => ['cs', '#top', null, '#top'],
            'mailto untouched'        => ['cs', 'mailto:a@b.cz', null, 'mailto:a@b.cz'],
            'relative untouched'      => ['cs', 'blog/', null, 'blog/'],
            'empty'                   => ['cs', '', null, ''],
            'unsupported locale falls back to the current' => ['cs', '/x/', 'de', '/cs/x/'],
        ];
    }

    public function testPrefixDefaultAddsThePrefixToTheDefaultLocaleToo(): void
    {
        $this->settings['prefixDefault'] = true;
        $this->configured('en');

        $this->assertSame('/en/blog/', I18n::url('/blog/'));
    }

    public function testUrlsAreUntouchedWhenDisabledOrWithoutPrefixes(): void
    {
        I18n::configure((new Translator([], 'en'))->setLocale('cs'), ['enabled' => false] + $this->settings);
        $this->assertSame('/blog/', I18n::url('/blog/'));

        $this->settings['urlPrefix'] = false;
        $this->configured('cs');
        $this->assertSame('/blog/', I18n::url('/blog/'));
    }

    public function testAlternatesListEveryLocale(): void
    {
        $this->configured('cs');

        $this->assertSame(['en' => '/blog/', 'cs' => '/cs/blog/', 'pt_BR' => '/pt-BR/blog/'], I18n::alternates('/blog/'));
    }

    public function testSetLocaleAcceptsOnlySupportedLocales(): void
    {
        $this->configured('en');

        I18n::setLocale('de');
        $this->assertSame('en', I18n::locale());

        I18n::setLocale('CS');
        $this->assertSame('cs', I18n::locale());
        $this->assertSame('cs', I18n::language());
    }

    // ------------------------------------------------------- the framework's own messages

    public function testWithoutConfigurationTheFrameworkMessagesAreEnglish(): void
    {
        I18n::configure(null, null);

        $this->assertSame('Username', I18n::t('wb.login.username'));
        $this->assertSame('unknown.key', I18n::t('unknown.key'));
    }

    public function testEveryBundledCatalogHasTheSameKeys(): void
    {
        $dir = dirname(__DIR__, 3) . '/I18n/messages';
        $keys = [];
        foreach (glob($dir . '/*.php') as $file) {
            $keys[basename($file, '.php')] = array_keys(include $file);
        }

        $this->assertArrayHasKey('en', $keys);
        $this->assertGreaterThan(1, count($keys));
        foreach ($keys as $locale => $list) {
            $this->assertEqualsCanonicalizing($keys['en'], $list, "the $locale catalog must have exactly the keys of the English one");
        }
    }

    public function testNoBundledMessageIsEmptyAndPlaceholdersMatch(): void
    {
        $dir = dirname(__DIR__, 3) . '/I18n/messages';
        $en = include $dir . '/en.php';
        foreach (glob($dir . '/*.php') as $file) {
            foreach (include $file as $key => $text) {
                $this->assertNotSame('', trim((string) $text), "$file: $key is empty");
                preg_match_all('/%\w+%/', (string) $en[$key], $a);
                preg_match_all('/%\w+%/', (string) $text, $b);
                $this->assertEqualsCanonicalizing($a[0], $b[0], "$file: $key must use the same placeholders as English");
            }
        }
    }
}
