<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Application\Application;
use Wbengine\Application\Env\Http;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Api;
use Wbengine\Config;
use Wbengine\I18n;

class TrustedProxyTest extends TestCase
{
    private array $server;
    private $config;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->config = Config::$config;
        Config::$config = (object) [];
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        Config::$config = $this->config;
    }

    private function policy(array $proxies, array $headers = ['x-forwarded-for', 'x-forwarded-proto']): void
    {
        Config::$config->http = (object) ['trustedProxies' => $proxies, 'forwardedHeaders' => $headers];
    }

    public function testHeadersCannotSupplyAMissingOrInvalidPeer(): void
    {
        $_SERVER = ['HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_CLIENT_IP' => '203.0.113.10'];
        $this->assertSame('', Utils::getUserIp());
        $this->policy(['10.0.0.0/8']);
        foreach ([null, '', 'invalid', [], '10.0.0.1:80'] as $peer) {
            $_SERVER['REMOTE_ADDR'] = $peer;
            $this->assertSame('', Utils::getUserIp());
        }
    }

    #[DataProvider('addressChains')]
    public function testAddressFollowsOnlyTheTrustedRightSide(array $proxies, $peer, $header, string $expected): void
    {
        $this->policy($proxies);
        $_SERVER = ['REMOTE_ADDR' => $peer, 'HTTP_X_FORWARDED_FOR' => $header];
        $this->assertSame($expected, Utils::getUserIp());
    }

    public static function addressChains(): array
    {
        return [
            'disabled' => [[], '10.0.0.1', '203.0.113.9', '10.0.0.1'],
            'untrusted peer' => [['10.0.0.0/8'], '198.51.100.1', '203.0.113.9', '198.51.100.1'],
            'one proxy' => [['10.0.0.0/8'], '10.0.0.1', '203.0.113.9', '203.0.113.9'],
            'exact proxy' => [['10.0.0.1'], '10.0.0.1', '203.0.113.9', '203.0.113.9'],
            'multiple proxies' => [['10.0.0.0/8'], '10.0.0.1', '203.0.113.9, 10.2.3.4', '203.0.113.9'],
            'spoofed left' => [['10.0.0.0/8'], '10.0.0.1', '6.6.6.6, 198.51.100.2, 10.2.3.4', '198.51.100.2'],
            'malformed left ignored after boundary' => [['10.0.0.0/8'], '10.0.0.1', 'garbage, 198.51.100.2', '198.51.100.2'],
            'malformed trusted suffix' => [['10.0.0.0/8'], '10.0.0.1', '203.0.113.9, unknown', '10.0.0.1'],
            'missing' => [['10.0.0.0/8'], '10.0.0.1', null, '10.0.0.1'],
            'array' => [['10.0.0.0/8'], '10.0.0.1', [], '10.0.0.1'],
            'empty hop' => [['10.0.0.0/8'], '10.0.0.1', '203.0.113.9,', '10.0.0.1'],
            'port' => [['10.0.0.0/8'], '10.0.0.1', '203.0.113.9:80', '10.0.0.1'],
            'ipv6 peer' => [['2001:db8:1::/48'], '2001:db8:1::1', '2001:db8:2:0:0:0:0:9', '2001:db8:2::9'],
            'ipv6 outside range' => [['2001:db8:1::/48'], '2001:db8:2::1', '203.0.113.9', '2001:db8:2::1'],
            'ipv6 partial byte in range' => [['2001:db8::/33'], '2001:db8:7fff::1', '203.0.113.9', '203.0.113.9'],
            'ipv6 partial byte outside' => [['2001:db8::/33'], '2001:db8:8000::1', '203.0.113.9', '2001:db8:8000::1'],
            'ipv4 /32 outside' => [['10.0.0.0/32'], '10.0.0.1', '203.0.113.9', '10.0.0.1'],
            'ipv6 /128 exact' => [['2001:db8::1/128'], '2001:db8::1', '203.0.113.9', '203.0.113.9'],
            'address family mismatch' => [['::/0'], '10.0.0.1', '203.0.113.9', '10.0.0.1'],
            'all trusted' => [['10.0.0.0/8'], '10.0.0.1', '10.1.2.3, 10.2.3.4', '10.1.2.3'],
            'overlong' => [['10.0.0.0/8'], '10.0.0.1', str_repeat('1', 8193), '10.0.0.1'],
            'too many hops' => [['10.0.0.0/8'], '10.0.0.1', str_repeat('10.0.0.2,', 65) . '10.0.0.3', '10.0.0.1'],
        ];
    }

    public function testEachForwardedHeaderMustBeExplicitlyEnabled(): void
    {
        $_SERVER = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        $this->policy(['10.0.0.0/8'], []);
        $this->assertSame('10.0.0.1', Utils::getUserIp());
        $this->assertFalse((new Api())->getCookieIsSecured());
        $this->policy(['10.0.0.0/8'], ['x-forwarded-for']);
        $this->assertSame('203.0.113.9', Utils::getUserIp());
        $this->assertFalse((new Api())->getCookieIsSecured());
    }

    #[DataProvider('schemes')]
    public function testApiCookieUsesTrustedOriginalScheme(array $server, array $proxies, bool $expected): void
    {
        $this->policy($proxies);
        $_SERVER = $server;
        $this->assertSame($expected, (new Api())->getCookieIsSecured());
    }

    public static function schemes(): array
    {
        return [
            'native TLS' => [['HTTPS' => 'on'], [], true],
            'native one' => [['HTTPS' => '1'], [], true],
            'native off' => [['HTTPS' => 'off'], [], false],
            'native zero' => [['HTTPS' => '0'], [], false],
            'native unexpected' => [['HTTPS' => 'garbage'], [], false],
            'native array' => [['HTTPS' => ['on']], [], false],
            'spoofed' => [['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['10.0.0.0/8'], false],
            'invalid peer' => [['REMOTE_ADDR' => 'garbage', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['10.0.0.0/8'], false],
            'no peer' => [['HTTP_X_FORWARDED_PROTO' => 'https'], ['10.0.0.0/8'], false],
            'tls terminated' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['10.0.0.0/8'], true],
            'tls backend plain original' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTPS' => 'on', 'HTTP_X_FORWARDED_PROTO' => 'http'], ['10.0.0.0/8'], false],
            'list rejected' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https, http'], ['10.0.0.0/8'], false],
            'array rejected' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => ['https']], ['10.0.0.0/8'], false],
            'unknown rejected' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'wss'], ['10.0.0.0/8'], false],
            'alternative ignored' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_FORWARDED' => 'proto=https', 'HTTP_X_FORWARDED_SSL' => 'on'], ['10.0.0.0/8'], false],
        ];
    }

    #[DataProvider('localeSchemes')]
    public function testLocaleCookieAndAbsoluteOriginUseTheProvidedConnection(array $server, bool $secure): void
    {
        $this->policy(['10.0.0.0/8']);
        Config::$config->i18n = (object) ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs']];
        $server += ['HTTP_HOST' => 'example.test', 'REQUEST_URI' => '/cs/', 'REQUEST_METHOD' => 'GET'];
        // A supplied request must not accidentally read the ambient connection instead.
        $_SERVER = ['HTTPS' => $secure ? 'off' : 'on'];
        $cookies = [];
        I18n::setCookieWriter(function ($name, $value, $options) use (&$cookies) {
            $cookies[] = $options;
        });
        try {
            I18n::boot(sys_get_temp_dir(), $server, []);
            $this->assertCount(1, $cookies);
            $this->assertSame($secure, $cookies[0]['secure']);
            $this->assertTrue($cookies[0]['httponly']);
            $this->assertSame('Lax', $cookies[0]['samesite']);
            $this->assertSame($secure ? 'https://example.test' : 'http://example.test', I18n::origin($server));
        } finally {
            I18n::configure(null, null);
        }
    }

    public static function localeSchemes(): array
    {
        return [
            [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'], true],
            [['REMOTE_ADDR' => '198.51.100.2', 'HTTP_X_FORWARDED_PROTO' => 'https'], false],
            [['REMOTE_ADDR' => '10.0.0.1', 'HTTPS' => 'on', 'HTTP_X_FORWARDED_PROTO' => 'http'], false],
            [['HTTPS' => 'on'], true],
        ];
    }

    public function testExplicitApiSecureSettingStillOverridesDetection(): void
    {
        $this->policy(['10.0.0.1']);
        $_SERVER = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        $api = new Api();
        $api->setCookieIsSecured(false);
        $this->assertFalse($api->getCookieIsSecured());
        $api->setCookieIsSecured(true);
        $this->assertTrue($api->getCookieIsSecured());
    }

    #[DataProvider('invalidPolicies')]
    public function testInvalidPoliciesFailClosed($http): void
    {
        Config::$config->http = $http;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $this->expectException(\InvalidArgumentException::class);
        Utils::getUserIp();
    }

    public static function invalidPolicies(): array
    {
        return array_map(fn ($v) => [$v], [
            'any', (object) ['trustedProxies' => '*'], (object) ['trustedProxies' => ['*']],
            (object) ['trustedProxies' => ['10.0.0.0/33']], (object) ['trustedProxies' => ['::/129']],
            (object) ['trustedProxies' => ['10.0.0.0/-1']], (object) ['trustedProxies' => ['10.0.0.0/abc']],
            (object) ['trustedProxies' => [null]], (object) ['trustedProxies' => ['127.1']],
            (object) ['forwardedHeaders' => ['client-ip']], (object) ['forwardedHeaders' => 'x-forwarded-for'],
        ]);
    }

    public function testApplicationOwnsLazyContextAndUsesTheSamePolicyAsLegacyFacade(): void
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $this->policy(['10.0.0.1']);
        $_SERVER = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'];
        $context = $app->getRequestContext();
        $this->assertSame($app, $context->getParent());
        $this->assertSame($context, $app->getRequestContext());
        $this->assertSame('203.0.113.9', $context->getClientIp());
        $this->assertSame('203.0.113.9', Http::getClientIp());
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.2';
        $this->assertSame('198.51.100.2', $context->getClientIp());
        $this->assertSame('198.51.100.2', Http::getClientIp());
        $this->policy([]);
        $this->assertSame('10.0.0.1', $context->getClientIp());
        $this->assertSame('10.0.0.1', Http::getClientIp());
    }

    public function testUrlHostNameFollowsTrustedForwardedProto(): void
    {
        $url = (new \ReflectionClass(\Wbengine\Url::class))->newInstanceWithoutConstructor();
        $base = ['SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_HOST' => 'example.test', 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        $this->policy(['10.0.0.0/8']);
        $_SERVER = $base;
        $this->assertSame('https://example.test', $url->getHostName());
        $_SERVER = ['REMOTE_ADDR' => '203.0.113.5'] + $base;
        $this->assertSame('http://example.test', $url->getHostName(), 'untrusted peer cannot choose the scheme');
        $this->policy([]);
        $_SERVER = $base;
        $this->assertSame('http://example.test', $url->getHostName());
        $_SERVER = ['HTTPS' => 'on'] + $base;
        $this->assertSame('https://example.test', $url->getHostName());
    }
}
