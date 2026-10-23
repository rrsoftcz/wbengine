<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Db;
use Wbengine\PageCache;

/**
 * Whether a visitor may be served the anonymous copy of a page. Getting this wrong
 * would show a logged in user's page to somebody else, so every doubt must mean "logged in".
 */
class PageCacheDecisionTest extends DbTestCase
{
    private const SID = 'abcdefghijklmnop0123456789';

    private function session(int $userId, int $expires): void
    {
        Db::insert('cms_sessions', [
            'session_id' => self::SID, 'user_id' => $userId, 'session_data' => 'O:8:"stdClass":0:{}',
            'user_agent' => 'x', 'user_ip' => '1.1.1.1', 'session_updated' => time(),
            'session_expire' => $expires, 'user_salt' => 'salt',
        ]);
    }

    public function testAnAnonymousSessionMayUseTheCache(): void
    {
        $this->session(0, time() + 600);

        $this->assertFalse(PageCache::sessionBelongsToAUser(self::SID));
    }

    public function testALoggedInSessionMayNot(): void
    {
        $this->session(7, time() + 600);

        $this->assertTrue(PageCache::sessionBelongsToAUser(self::SID));
    }

    public function testAnUnknownSessionIdIsAnonymous(): void
    {
        $this->assertFalse(PageCache::sessionBelongsToAUser(self::SID));
    }

    public function testAnExpiredLoggedInSessionIsNotLoggedIn(): void
    {
        $this->session(7, time() - 10);

        $this->assertFalse(PageCache::sessionBelongsToAUser(self::SID));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function suspiciousValues(): array
    {
        return [
            'array'        => [['a']],
            'null'         => [null],
            'int'          => [123456789012345678],
            'too short'    => ['abc'],
            'too long'     => [str_repeat('a', 200)],
            'quote'        => ["abcdefghijklmnop' OR '1'='1"],
            'space'        => ['abcdefghijklmnop 123456'],
            'null byte'    => ["abcdefghijklmnop\0123456"],
            'empty'        => [''],
        ];
    }

    #[DataProvider('suspiciousValues')]
    public function testAnythingOddCountsAsLoggedIn($value): void
    {
        $this->assertTrue(PageCache::sessionBelongsToAUser($value));
    }
}
