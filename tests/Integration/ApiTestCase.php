<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api;
use Wbengine\Auth;
use Wbengine\Db;
use Wbengine\User\Role;

/**
 * Base class for tests of the REST API controllers. They are called directly: the API
 * normally ends the request after each response, here it throws instead, and cookies
 * are captured instead of being sent.
 */
abstract class ApiTestCase extends DbTestCase
{
    protected Api $api;
    /** @var array[] name, value and options of every cookie the API tried to set */
    protected array $cookies = [];
    private $previousErrorLog;

    protected function setUp(): void
    {
        parent::setUp();
        // Rejected tokens and similar are logged on purpose, keep the test output clean.
        $this->previousErrorLog = ini_set('error_log', '/dev/null');
        $this->cookies = [];
        $this->api = new Api();
        $this->api->setTerminator(static function () {
            throw new ResponseSent();
        });
        $this->api->setCookieWriter(function ($name, $value, array $options) {
            $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        });
        unset($_SERVER['HTTP_AUTHORIZATION'], $_COOKIE['refresh_token']);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);
        unset($_SERVER['HTTP_AUTHORIZATION'], $_COOKIE['refresh_token']);
    }

    /**
     * Run a controller action and return what it answered.
     *
     * @return array status, json (decoded body or null) and body
     */
    protected function respond(callable $action): array
    {
        ob_start();
        try {
            $action();
        } catch (ResponseSent $e) {
            // the API ended the request, as it does in production
        } finally {
            $body = (string) ob_get_clean();
        }
        return ['status' => $this->api->getLastStatus(), 'json' => json_decode($body, true), 'body' => $body];
    }

    protected function createUser(string $email, string $role = Role::USER, string $password = 'pw'): int
    {
        return Db::insert('cms_users', [
            'username' => $email, 'email' => $email, 'role' => $role,
            // A legacy hash is accepted and avoids the cost of bcrypt in every test.
            'password' => md5($password),
        ]);
    }

    /**
     * A valid access token for a user, as the login would issue it.
     */
    protected function tokenFor(int $userId, int $ttl = 600): string
    {
        return (new Auth())
            ->setExpiredTime(time() + $ttl)
            ->setPayloadData(['user_id' => $userId])
            ->getJwtToken();
    }

    protected function asUser(int $userId): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->tokenFor($userId);
    }

    protected function lastCookie(string $name = 'refresh_token'): ?array
    {
        $matches = array_values(array_filter($this->cookies, fn ($c) => $c['name'] === $name));
        return $matches ? end($matches) : null;
    }
}
