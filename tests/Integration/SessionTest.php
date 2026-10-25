<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Config;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Wbengine\Db;
use Wbengine\Session;

/**
 * The session writes to the database and to PHP's own session handling, so every test
 * runs in a separate process. A "next request" is simulated by closing the PHP session
 * and presenting the cookie again.
 */
class SessionTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        unset($_COOKIE[session_name()]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('proxyConnections')]
    public function testSessionCookiesAndStoredIpFollowTrustedConnection(array $server, bool $secure, string $ip): void
    {
        Config::$config->http = (object) [
            'trustedProxies' => ['10.0.0.0/8'],
            'forwardedHeaders' => ['x-forwarded-for', 'x-forwarded-proto'],
        ];
        $_SERVER = $server + ['HTTP_USER_AGENT' => 'PHPUnit'];
        $session = new Session();
        $options = session_get_cookie_params();
        $this->assertSame($secure, $options['secure']);
        $this->assertTrue($options['httponly']);
        $this->assertSame('Lax', $options['samesite']);
        $this->assertSame($ip, $session->getUserIp());
        $session->setValue('content', 'private');
        $session->flush();
        $this->assertSame($ip, Db::fetchAssoc('SELECT user_ip FROM cms_sessions WHERE session_id = ?', [$session->getSessionId()])['user_ip']);
        session_write_close();
    }

    public static function proxyConnections(): array
    {
        return [
            'TLS termination' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'], true, '203.0.113.9'],
            'untrusted headers' => [['REMOTE_ADDR' => '198.51.100.2', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'], false, '198.51.100.2'],
            'native HTTPS' => [['REMOTE_ADDR' => '198.51.100.2', 'HTTPS' => 'on'], true, '198.51.100.2'],
        ];
    }

    private function rows(): int
    {
        return (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_sessions')['c'];
    }

    /**
     * End the "request" and start the next one with the cookie the first one received.
     */
    private function nextRequest(Session $previous): Session
    {
        $id = $previous->getSessionId();
        $previous->flush();
        session_write_close();
        $_COOKIE[session_name()] = $id;
        return new Session();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAVisitorWhoStoresNothingLeavesNoRow(): void
    {
        $session = new Session();
        $this->assertNull($session->getValue('anything'));

        $session->flush();

        $this->assertSame(0, $this->rows());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testARequestWithoutACookieCostsNoSessionQuery(): void
    {
        $before = Db::getQueriesCount();

        $session = new Session();
        // Measure request lookup cost independently of the probabilistic GC DELETE.
        $session->setAutoClean(false);
        $session->getValue('anything');

        $this->assertSame($before, Db::getQueriesCount(), 'no cookie, so no stored session can exist');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValuesAreWrittenOnceAndSurviveTheNextRequest(): void
    {
        $session = new Session();
        $session->setValue('a', 1);
        $session->setValue('b', ['x' => 'y']);
        $session->setValue('c', 'three');
        $this->assertSame(0, $this->rows(), 'nothing is written while the request runs');

        $session->flush();
        $this->assertSame(1, $this->rows(), 'one row, however many values were set');

        $next = $this->nextRequest($session);
        $this->assertSame(1, $next->getValue('a'));
        $this->assertEquals(['x' => 'y'], $next->getValue('b'));
        $this->assertSame('three', $next->getValue('c'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testASecondWriteUpdatesTheSameRow(): void
    {
        $session = new Session();
        $session->setValue('a', 1);
        $next = $this->nextRequest($session);

        $next->setValue('a', 2);
        $next->flush();

        $this->assertSame(1, $this->rows());
        $this->assertSame(2, $this->nextRequest($next)->getValue('a'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWasModifiedReportsWrites(): void
    {
        $session = new Session();
        $this->assertFalse($session->wasModified());

        $session->setValue('a', 1);

        $this->assertTrue($session->wasModified());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testADestroyedSessionStaysDead(): void
    {
        $session = new Session();
        $session->setValue('user_id', 7);
        $next = $this->nextRequest($session);
        $this->assertSame(1, $this->rows());

        $next->destroy();
        $next->setValue('user_is_logged', false);   // what a logout does right after
        $next->flush();

        $this->assertSame(0, $this->rows(), 'the row is gone and a later write does not bring it back');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegeneratingTheIdMovesTheRow(): void
    {
        $session = new Session();
        $session->setValue('a', 1);
        $next = $this->nextRequest($session);
        $old = $next->getSessionId();

        $next->regenerateId();
        $next->flush();

        $this->assertNotSame($old, $next->getSessionId());
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_sessions WHERE session_id = ?', [$old])['c']);
        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_sessions WHERE session_id = ?', [$next->getSessionId()])['c']);
        $this->assertSame(1, $next->getValue('a'), 'the data moved with it');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testASessionOfAnotherClientIsNotAccepted(): void
    {
        $session = new Session();
        $session->setValue('secret', 'mine');
        $id = $session->getSessionId();
        $session->flush();
        session_write_close();

        // The same cookie from another address and another browser.
        $_COOKIE[session_name()] = $id;
        $_SERVER['REMOTE_ADDR'] = '198.51.100.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Somebody else';
        $thief = new Session();

        $this->assertNull($thief->getValue('secret'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAnExpiredRowIsIgnoredAndRemoved(): void
    {
        $session = new Session();
        $session->setValue('a', 1);
        $id = $session->getSessionId();
        $session->flush();
        session_write_close();
        Db::query('UPDATE cms_sessions SET session_expire = ? WHERE session_id = ?', [time() - 10, $id]);

        $_COOKIE[session_name()] = $id;
        $next = new Session();

        $this->assertNull($next->getValue('a'));
        $this->assertSame(0, $this->rows(), 'the expired row was deleted');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStoredObjectsOtherThanStdClassAreNeverCreated(): void
    {
        $session = new Session();
        $session->setValue('a', 1);
        $id = $session->getSessionId();
        $session->flush();
        session_write_close();

        $payload = serialize(new \ArrayObject(['pwned' => true]));
        Db::query('UPDATE cms_sessions SET session_data = ? WHERE session_id = ?', [$payload, $id]);

        $_COOKIE[session_name()] = $id;
        $next = new Session();

        $this->assertNull($next->getValue('pwned'));
        $this->assertNull($next->getValue('a'), 'a foreign object is discarded, not instantiated');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHostileValuesRoundTrip(): void
    {
        $values = ["O'Brien", '"; DROP TABLE cms_sessions; --', "\0", "emoji \u{1F600}", str_repeat('x', 5000)];
        $session = new Session();
        foreach ($values as $i => $v) {
            $session->setValue('k' . $i, $v);
        }

        $next = $this->nextRequest($session);

        foreach ($values as $i => $v) {
            $this->assertSame($v, $next->getValue('k' . $i));
        }
    }
}
