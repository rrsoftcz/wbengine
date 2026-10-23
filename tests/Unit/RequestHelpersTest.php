<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Application\Env\Http;
use Wbengine\Application\Env\Stac\Utils;

class RequestHelpersTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        unset($_COOKIE['probe']);
    }

    public function testHeaderLookupIsCaseInsensitiveAndFallsBackToServer(): void
    {
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'v';

        $this->assertSame('v', Http::getHeader('X-Custom-Header'));
        $this->assertSame('v', Http::getHeader('x-custom-header'));
        $this->assertNull(Http::getHeader('X-Missing'));
    }

    public function testAuthorizationCanArriveThroughTheRedirectVariable(): void
    {
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer abc';

        $this->assertSame('abc', Http::getBearerToken());
    }

    #[DataProvider('bearerHeaders')]
    public function testBearerToken(string $header, ?string $expected): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = $header;

        $this->assertSame($expected, Http::getBearerToken());
    }

    public static function bearerHeaders(): array
    {
        return [
            'standard'            => ['Bearer abc.def.ghi', 'abc.def.ghi'],
            'lower case scheme'   => ['bearer abc', 'abc'],
            'upper case scheme'   => ['BEARER abc', 'abc'],
            'extra spaces'        => ['  Bearer    abc', 'abc'],
            'token only'          => ['abc', 'abc'],
        ];
    }

    public function testNoAuthorizationHeaderGivesNoToken(): void
    {
        $this->assertNull(Http::getBearerToken());
    }

    public function testMissingCookieIsNullNotAWarning(): void
    {
        $this->assertNull(Http::getCookie('probe'));

        $_COOKIE['probe'] = '<b>';
        $this->assertSame('&lt;b&gt;', Http::getCookie('probe'));
    }

    public function testRequestMethodIsReadFromServerNotFromFilterInput(): void
    {
        // filter_input(INPUT_SERVER, ...) returns null under FastCGI and in the CLI.
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertTrue(Http::getRequestMethod(Http::TYPE_POST));
        $this->assertFalse(Http::getRequestMethod(Http::TYPE_GET));

        unset($_SERVER['REQUEST_METHOD']);
        $this->assertFalse(Http::getRequestMethod(Http::TYPE_POST));
    }

    public function testARequestWithoutAUserAgentOrAddressDoesNotWarn(): void
    {
        $this->assertSame('', Utils::getUserAgent());
        $this->assertSame('', Utils::getUserIp());
    }

    public function testAddressPrefersTheConnectionOverForwardedHeaders(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6';

        $this->assertSame('10.0.0.1', Utils::getUserIp());
    }
}
