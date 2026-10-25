<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Box\Auth;
use Wbengine\Session;
use Wbengine\Session\Csrf;
use Wbengine\Tests\Support\MemorySession;

class AuthBoxInputTest extends TestCase
{
    private array $post;
    private array $server;
    private $recaptchaSecret;

    protected function setUp(): void
    {
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->recaptchaSecret = getenv('WBENGINE_RECAPTCHA_SECRET');
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        $_SERVER = $this->server;
        (new \ReflectionProperty(\Wbengine\PageCache::class, '_bypassed'))->setValue(null, false);
        (new \ReflectionProperty(\Wbengine\Template\FragmentCache::class, '_bypassed'))->setValue(null, false);
        false === $this->recaptchaSecret ? putenv('WBENGINE_RECAPTCHA_SECRET')
            : putenv('WBENGINE_RECAPTCHA_SECRET=' . $this->recaptchaSecret);
    }

    #[DataProvider('csrfInputs')]
    public function testCsrfInputIsValidatedWithoutStringCoercion($submitted, bool $expected): void
    {
        $session = new MemorySession();
        $token = $session->getCsrf()->token('auth.login');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $_POST = ['token' => 'stored-token' === $submitted ? $token : $submitted];
        $box = $this->createStub(Auth::class);
        $box->method('getSession')->willReturn($session);
        $this->assertSame($expected, (new \ReflectionMethod(Auth::class, '_checkToken'))->invoke($box));
    }

    public static function csrfInputs(): array
    {
        return [['stored-token', true], ['wrong', false], ['', false], [null, false],
            [[], false], [['stored-token'], false], [new \stdClass(), false], [true, false], [0, false]];
    }

    #[DataProvider('malformedTokens')]
    public function testMalformedStoredTokenIsRejected($stored): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $_POST = ['token' => str_repeat('a', 64)];
        $session = new MemorySession();
        $session->values[Csrf::SESSION_KEY] = $stored;
        $box = $this->createStub(Auth::class);
        $box->method('getSession')->willReturn($session);
        $this->assertFalse((new \ReflectionMethod(Auth::class, '_checkToken'))->invoke($box));
    }

    #[DataProvider('captchaInputs')]
    public function testMalformedCaptchaInputIsRejectedBeforeVerification($submitted, bool $ajax): void
    {
        putenv('WBENGINE_RECAPTCHA_SECRET=local-test-secret');
        if ($ajax) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        } else {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }
        $_POST = [($ajax ? Auth::CAPTCHA_RESPONSE_AJAX : Auth::CAPTCHA_RESPONSE) => $submitted];
        $session = $this->createStub(Session::class);
        $session->method('getValue')->willReturn(4);
        $box = $this->getMockBuilder(Auth::class)->disableOriginalConstructor()
            ->onlyMethods(['getSession', 'verifyCaptcha'])->getMock();
        $box->method('getSession')->willReturn($session);
        $box->expects($this->never())->method('verifyCaptcha');
        $this->assertFalse((new \ReflectionMethod(Auth::class, '_checkCaptcha'))->invoke($box));
    }

    #[DataProvider('requestModes')]
    public function testValidCaptchaStringIsPassedUnchangedToTheVerifier(bool $ajax): void
    {
        putenv('WBENGINE_RECAPTCHA_SECRET=local-test-secret');
        if ($ajax) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        } else {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }
        $value = 'opaque\\value+with/characters';
        $_POST = [($ajax ? Auth::CAPTCHA_RESPONSE_AJAX : Auth::CAPTCHA_RESPONSE) => $value];
        $session = $this->createStub(Session::class);
        $session->method('getValue')->willReturn(4);
        $box = $this->getMockBuilder(Auth::class)->disableOriginalConstructor()
            ->onlyMethods(['getSession', 'verifyCaptcha'])->getMock();
        $box->method('getSession')->willReturn($session);
        $box->expects($this->once())->method('verifyCaptcha')->with('local-test-secret', $value)->willReturn(true);
        $this->assertTrue((new \ReflectionMethod(Auth::class, '_checkCaptcha'))->invoke($box));
    }

    public static function requestModes(): array
    {
        return [[false], [true]];
    }

    public static function captchaInputs(): array
    {
        $inputs = [];
        foreach (self::malformedTokens() as [$value]) {
            $inputs[] = [$value, false];
            $inputs[] = [$value, true];
        }
        return $inputs;
    }

    public static function malformedTokens(): array
    {
        return [[null], [[]], [['stored-token']], [new \stdClass()], [true], [0], ['']];
    }
}
