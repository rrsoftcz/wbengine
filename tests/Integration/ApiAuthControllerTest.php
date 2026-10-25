<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api\Auth;
use Wbengine\Api\Auth\RefreshTokens;
use Wbengine\Db;
use Wbengine\Config;
use Wbengine\User\Role;

/**
 * The parts of the auth controller that do not start a PHP session: refresh, identify,
 * and logging out everywhere. (Login and logout use the PHP session.)
 */
class ApiAuthControllerTest extends ApiTestCase
{
    private Auth $auth;
    private RefreshTokens $tokens;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = new Auth($this->api);
        $this->tokens = new RefreshTokens();
        $this->userId = $this->createUser('user@example.com', Role::EDITOR);
    }

    private function refreshWith(?string $cookie): array
    {
        if (null === $cookie) {
            unset($_COOKIE['refresh_token']);
        } else {
            $_COOKIE['refresh_token'] = $cookie;
        }
        return $this->respond(fn () => $this->auth->validateRefreshToken());
    }

    // -------------------------------------------------------------- refresh

    public function testRefreshIssuesAWorkingAccessTokenAndRotatesTheCookie(): void
    {
        $old = $this->tokens->issue($this->userId, 3600);

        $r = $this->refreshWith($old);

        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['json']['success']);
        $this->assertSame($this->userId, $r['json']['user_id']);

        $payload = (new \Wbengine\Auth())->getDecodedData($r['json']['token']);
        $this->assertSame($this->userId, (int) $payload['data']->user_id);
        $this->assertSame(Role::EDITOR, $payload['data']->role, 'the token carries the role for display');

        $cookie = $this->lastCookie();
        $this->assertNotNull($cookie);
        $this->assertNotSame($old, $cookie['value']);
        $this->assertGreaterThan(time(), $cookie['options']['expires']);
    }

    public function testTheRefreshCookieIsHardened(): void
    {
        $this->refreshWith($this->tokens->issue($this->userId, 3600));

        $options = $this->lastCookie()['options'];

        $this->assertTrue($options['httponly']);
        $this->assertSame('Strict', $options['samesite']);
        $this->assertSame('/api/auth/', $options['path']);
        $this->assertSame('', $options['domain'], 'host-only, not a hardcoded domain');
        $this->assertFalse($options['secure'], 'Secure follows the request scheme, plain HTTP here');
    }

    public function testSecureFlagFollowsHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        try {
            $this->refreshWith($this->tokens->issue($this->userId, 3600));
            $this->assertTrue($this->lastCookie()['options']['secure']);
        } finally {
            unset($_SERVER['HTTPS']);
        }
    }

    public function testRefreshIssueAndDeletionUseTrustedOriginalScheme(): void
    {
        $config = Config::$config;
        $server = $_SERVER;
        Config::$config = clone $config;
        Config::$config->http = (object) [
            'trustedProxies' => ['10.0.0.0/8'],
            'forwardedHeaders' => ['x-forwarded-for', 'x-forwarded-proto'],
        ];
        try {
            foreach (['10.0.0.1' => true, '198.51.100.2' => false] as $peer => $secure) {
                $_SERVER = ['REMOTE_ADDR' => $peer, 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'];
                $this->assertSame(200, $this->refreshWith($this->tokens->issue($this->userId, 3600))['status']);
                $this->assertSame($secure, $this->lastCookie()['options']['secure']);
                $this->assertTrue($this->lastCookie()['options']['httponly']);
                $this->assertSame('Strict', $this->lastCookie()['options']['samesite']);
                $this->assertSame(401, $this->refreshWith('invalid')['status']);
                $this->assertSame($secure, $this->lastCookie()['options']['secure']);
                $this->assertLessThan(time(), $this->lastCookie()['options']['expires']);
            }
        } finally {
            Config::$config = $config;
            $_SERVER = $server;
        }
    }

    public function testRefreshNeedsNoPhpSession(): void
    {
        $this->assertSame(PHP_SESSION_NONE, session_status());

        $this->assertSame(200, $this->refreshWith($this->tokens->issue($this->userId, 3600))['status']);

        $this->assertSame(PHP_SESSION_NONE, session_status(), 'the stateless endpoint did not start one');
    }

    public function testMissingAndGarbageCookiesAre401AndClearTheCookie(): void
    {
        foreach ([null, '', 'garbage', "x' OR '1'='1", str_repeat('a', 500)] as $cookie) {
            $this->cookies = [];
            $r = $this->refreshWith($cookie);

            $this->assertSame(401, $r['status']);
            $this->assertFalse($r['json']['success']);
            $this->assertLessThan(time(), $this->lastCookie()['options']['expires'], 'the bad cookie is cleared');
            $this->assertSame('', $this->lastCookie()['value']);
        }
    }

    public function testTheFailureReasonIsNotShownToTheClient(): void
    {
        $r = $this->refreshWith($this->tokens->issue($this->userId, 3600) . 'x');

        $this->assertStringNotContainsString('unknown', $r['body']);
        $this->assertSame('Invalid refresh token, has expired or unavailable.', $r['json']['message']);
    }

    public function testStolenTokenRevokesTheFamilyThroughTheController(): void
    {
        $stolen = $this->tokens->issue($this->userId, 3600);
        $legit = $this->refreshWith($stolen);
        $this->assertSame(200, $legit['status']);
        $newCookie = $this->lastCookie()['value'];

        // Age the used token past the grace period.
        Db::query('UPDATE cms_refresh_tokens SET used_at = used_at - 60 WHERE used_at IS NOT NULL');

        $this->assertSame(401, $this->refreshWith($stolen)['status'], 'the thief is refused');
        $this->assertSame(401, $this->refreshWith($newCookie)['status'], 'and so is the owner: the family is gone');
    }

    public function testRefreshOfADeletedUserIs401(): void
    {
        $token = $this->tokens->issue($this->userId, 3600);
        Db::query('DELETE FROM cms_users WHERE user_id = ?', [$this->userId]);

        $this->assertSame(401, $this->refreshWith($token)['status']);
    }

    public function testRefreshWithoutTheTokenTableIs401NotAFatalError(): void
    {
        Db::query('RENAME TABLE cms_refresh_tokens TO cms_refresh_tokens_off');
        try {
            $this->assertSame(401, $this->refreshWith('A' . str_repeat('b', 40))['status']);
        } finally {
            Db::query('RENAME TABLE cms_refresh_tokens_off TO cms_refresh_tokens');
        }
    }

    // ------------------------------------------------------------- identify

    public function testIdentifyChecksTheTokenAgainstTheBodyWithoutASession(): void
    {
        $token = $this->tokenFor($this->userId);

        $ok = $this->respond(fn () => $this->auth->validateJwtToken($token, ['user_id' => $this->userId]));
        $this->assertSame(200, $ok['status']);
        $this->assertTrue($ok['json']['success']);

        $wrong = $this->respond(fn () => $this->auth->validateJwtToken($token, ['user_id' => $this->userId + 1]));
        $this->assertSame(401, $wrong['status']);
        $this->assertFalse($wrong['json']['success']);
    }

    public function testIdentifyRejectsBadInput(): void
    {
        $this->assertSame(401, $this->respond(fn () => $this->auth->validateJwtToken('garbage', ['user_id' => 1]))['status']);
        $this->assertSame(401, $this->respond(fn () => $this->auth->validateJwtToken(null, ['user_id' => 1]))['status']);
        $this->assertSame(401, $this->respond(fn () => $this->auth->validateJwtToken($this->tokenFor($this->userId), null))['status']);
        $this->assertSame(401, $this->respond(fn () => $this->auth->validateJwtToken($this->tokenFor($this->userId), ['x' => 1]))['status']);
    }

    // ------------------------------------------------------------ logout all

    public function testLogoutAllRevokesEveryDeviceOfTheCaller(): void
    {
        $a = $this->tokens->issue($this->userId, 3600);
        $b = $this->tokens->issue($this->userId, 3600);
        $other = $this->tokens->issue($this->createUser('o@example.com'), 3600);
        $this->asUser($this->userId);

        $r = $this->respond(fn () => $this->auth->logoutAll());

        $this->assertSame(200, $r['status']);
        $this->assertSame(2, $r['json']['revoked']);
        $this->assertSame(401, $this->refreshWith($a)['status']);
        $this->assertSame(401, $this->refreshWith($b)['status']);
        $this->assertSame(200, $this->refreshWith($other)['status'], 'other users are not affected');
    }

    public function testLogoutAllNeedsAToken(): void
    {
        $this->assertSame(401, $this->respond(fn () => $this->auth->logoutAll())['status']);
    }
}
