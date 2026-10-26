<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Application\Env\Http;
use Wbengine\Application\Env\Stac\Utils;

class RequestHelpersTest extends TestCase
{
    private array $server;
    private array $post;
    private array $get;
    private array $cookies;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->post = $_POST;
        $this->get = $_GET;
        $this->cookies = $_COOKIE;
        $_POST = $_GET = $_COOKIE = [];
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_POST = $this->post;
        $_GET = $this->get;
        $_COOKIE = $this->cookies;
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
        $this->assertSame('<b>', Http::getCookie('probe'));
    }

    #[DataProvider('rawPayloads')]
    public function testInputHelpersPreserveStringsExactly(string $value): void
    {
        $_POST['value'] = $_GET['value'] = $_COOKIE['value'] = $value;
        $this->assertSame($value, Http::Post('value'));
        $this->assertSame($value, Http::Get('value'));
        $this->assertSame($value, Http::getCookie('value'));
    }

    public static function rawPayloads(): array
    {
        return [
            'apostrophes and quotes' => ["O'Brien \"quoted\""],
            'backslashes' => ['C:\\path\\file \\n \\\\server\\share'],
            'html' => ['<img src=x onerror="alert(1)"> &amp; <b>text</b>'],
            'whitespace' => ["  line one\r\nline two\t  "],
            'empty' => [''],
            'zero' => ['0'],
            'invalid UTF-8' => ["\xC3\x28"],
            'null byte' => ["before\0after"],
            'Czech' => ['Příliš žluťoučký kůň'],
        ];
    }

    public function testPostAndGetReturnCollectionsWithoutFiltering(): void
    {
        $values = ['text' => "O'Brien", 'nested' => ['html' => '<b>', 'list' => ['a', 'b']],
            0 => 'zero', '' => 'empty key'];
        $_POST = $_GET = $values;
        $this->assertSame($values, Http::Post());
        $this->assertSame($values, Http::Get());
        $this->assertSame($values['nested'], Http::Post('nested'));
        $this->assertSame($values['nested'], Http::Get('nested'));
        foreach ([0, '0', ''] as $key) {
            $this->assertSame($values[$key], Http::Post($key));
            $this->assertSame($values[$key], Http::Get($key));
        }
    }

    public function testMissingFieldsAndInvalidKeyTypesReturnNull(): void
    {
        foreach (['missing', [], new \stdClass(), false, 1.5] as $key) {
            $this->assertNull(Http::Post($key));
            $this->assertNull(Http::Get($key));
            $this->assertNull(Http::PostString($key));
            $this->assertNull(Http::GetString($key));
            $this->assertNull(Http::PostRaw($key));
        }
    }

    public function testStringHelpersDoNotCoerceInvalidTypes(): void
    {
        foreach ([[], ['nested'], 0, false, true, new \stdClass(), null] as $value) {
            $_POST['value'] = $_GET['value'] = $_COOKIE['value'] = $value;
            $this->assertNull(Http::PostString('value'));
            $this->assertNull(Http::GetString('value'));
            $this->assertNull(Http::PostRaw('value'));
            $this->assertNull(Http::getCookie('value'));
        }
    }

    #[DataProvider('rawPayloads')]
    public function testStringHelpersDoNotModifyAcceptedStrings(string $value): void
    {
        $_POST['value'] = $_GET['value'] = $value;
        $this->assertSame($value, Http::PostString('value'));
        $this->assertSame($value, Http::GetString('value'));
        $this->assertSame($value, Http::PostRaw('value'));
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
