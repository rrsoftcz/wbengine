<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api;
use Wbengine\Db;
use Wbengine\User\Role;

/**
 * Base class for tests of the API controller. It is called directly: the API
 * normally ends the request after each response, here it throws instead.
 */
abstract class ApiTestCase extends DbTestCase
{
    protected Api $api;
    private $previousErrorLog;

    protected function setUp(): void
    {
        parent::setUp();
        // Rejected tokens and similar are logged on purpose, keep the test output clean.
        $this->previousErrorLog = ini_set('error_log', '/dev/null');
        $this->api = new Api();
        $this->api->setTerminator(static function () {
            throw new ResponseSent();
        });
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);
        unset($_SERVER['HTTP_AUTHORIZATION']);
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
}
