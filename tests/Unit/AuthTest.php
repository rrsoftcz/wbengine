<?php

namespace Wbengine\Tests\Unit;

use Firebase\JWT\SignatureInvalidException;
use PHPUnit\Framework\TestCase;
use Wbengine\Auth;
use Wbengine\Auth\Exception\AuthException;

class AuthTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('WBENGINE_JWT_KEY');
        putenv('WBENGINE_JWT_REFRESH_KEY');
    }

    public function testTokenRoundTrip(): void
    {
        $auth = (new Auth())->setPayloadData(array('uid' => 7));

        $decoded = $auth->getDecodedData($auth->getJwtToken());

        $this->assertSame(7, $decoded['data']->uid);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $auth = new Auth();
        $token = $auth->getJwtToken();

        $this->expectException(SignatureInvalidException::class);
        $auth->getDecodedData(substr($token, 0, -2) . 'xx');
    }

    public function testRefreshTokenDoesNotValidateAsAccessToken(): void
    {
        $auth = new Auth();

        $this->expectException(SignatureInvalidException::class);
        $auth->getDecodedData($auth->getRefreshToken());
    }

    public function testShortKeyFromEnvironmentFailsClosed(): void
    {
        putenv('WBENGINE_JWT_KEY=too-short');

        $this->expectException(AuthException::class);
        new Auth();
    }

    public function testEnvironmentKeyOverridesConfiguration(): void
    {
        $token = (new Auth())->getJwtToken();

        putenv('WBENGINE_JWT_KEY=' . str_repeat('k', 40));

        $this->expectException(SignatureInvalidException::class);
        (new Auth())->getDecodedData($token);
    }
}
