<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api\Auth\Exception\InvalidRefreshTokenException;
use Wbengine\Api\Auth\RefreshTokens;
use Wbengine\Db;

class RefreshTokensTest extends DbTestCase
{
    private int $now = 2_000_000;
    private RefreshTokens $tokens;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens = new RefreshTokens(fn () => $this->now);
        $this->userId = Db::insert('cms_users', array('username' => 'u', 'email' => 'u@example.com', 'password' => 'x'));
    }

    private function assertRejected(string $token, string $reasonStart): void
    {
        try {
            $this->tokens->rotate($token, 3600);
            $this->fail('Expected InvalidRefreshTokenException');
        } catch (InvalidRefreshTokenException $e) {
            $this->assertStringStartsWith($reasonStart, $e->getReason());
            $this->assertSame('Invalid refresh token.', $e->getMessage(), 'the client never learns the reason');
        }
    }

    public function testOnlyTheHashIsStored(): void
    {
        $token = $this->tokens->issue($this->userId, 3600);

        $row = Db::fetchAssoc('SELECT * FROM cms_refresh_tokens');
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->assertStringNotContainsString($token, json_encode($row));
        $this->assertGreaterThanOrEqual(40, strlen($token));
    }

    public function testRotationIssuesANewTokenInTheSameFamily(): void
    {
        $first = $this->tokens->issue($this->userId, 3600);

        $result = $this->tokens->rotate($first, 3600);

        $this->assertSame($this->userId, $result['user_id']);
        $this->assertNotSame($first, $result['token']);
        $families = array_unique(array_column(Db::fetchAllAssoc('SELECT family_id FROM cms_refresh_tokens'), 'family_id'));
        $this->assertCount(1, $families);
    }

    public function testTheNewTokenKeepsWorking(): void
    {
        $token = $this->tokens->issue($this->userId, 3600);

        for ($i = 0; $i < 5; $i++) {
            $this->now += 60;
            $token = $this->tokens->rotate($token, 3600)['token'];
        }

        $this->assertSame($this->userId, $this->tokens->rotate($token, 3600)['user_id']);
    }

    public function testUnknownAndMalformedTokensAreRejected(): void
    {
        $this->assertRejected('A' . str_repeat('b', 40), 'unknown');
        $this->assertRejected("x' OR '1'='1", 'unknown');
        $this->assertRejected('', 'unknown');
        $this->assertRejected(str_repeat('a', 500), 'unknown');
    }

    public function testExpiredTokenIsRejected(): void
    {
        $token = $this->tokens->issue($this->userId, 100);
        $this->now += 101;

        $this->assertRejected($token, 'expired');
    }

    public function testReuseAfterTheGracePeriodRevokesTheWholeFamily(): void
    {
        $stolen = $this->tokens->issue($this->userId, 3600);
        $legit = $this->tokens->rotate($stolen, 3600)['token'];

        $this->now += RefreshTokens::REUSE_GRACE + 1;
        $this->assertRejected($stolen, 'reused: family revoked');

        // The thief used the old token, so the owner's newest token is dead too.
        $this->assertRejected($legit, 'revoked');
    }

    public function testReuseWithinTheGraceIsRefusedButDoesNotRevoke(): void
    {
        $first = $this->tokens->issue($this->userId, 3600);
        $second = $this->tokens->rotate($first, 3600)['token'];

        $this->now += 2;
        $this->assertRejected($first, 'reused within the grace period');

        $this->assertSame($this->userId, $this->tokens->rotate($second, 3600)['user_id'], 'the winner of the race is unaffected');
    }

    public function testAFamilyHasAnAbsoluteLifetimeNoMatterHowOftenItIsUsed(): void
    {
        $tokens = new RefreshTokens(fn () => $this->now, 1000);
        $token = $tokens->issue($this->userId, 400);

        // Used every 300 seconds, well inside the 400 second sliding window.
        for ($i = 0; $i < 3; $i++) {
            $this->now += 300;
            $token = $tokens->rotate($token, 400)['token'];
        }

        $this->now += 300;  // 1200 seconds after the login
        try {
            $tokens->rotate($token, 400);
            $this->fail('Expected the family to have reached its absolute lifetime');
        } catch (InvalidRefreshTokenException $e) {
            $this->assertStringContainsString('absolute lifetime', $e->getReason());
        }

        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_refresh_tokens WHERE revoked_at IS NULL')['c'], 'the whole family is revoked');
    }

    public function testTheStartOfTheFamilyIsKeptAcrossRotations(): void
    {
        $token = $this->tokens->issue($this->userId, 3600);
        $start = (int) Db::fetchAssoc('SELECT family_started_at s FROM cms_refresh_tokens')['s'];

        $this->now += 500;
        $this->tokens->rotate($token, 3600);

        $starts = array_unique(array_column(Db::fetchAllAssoc('SELECT family_started_at FROM cms_refresh_tokens'), 'family_started_at'));
        $this->assertSame([$start], array_map('intval', $starts));
    }

    public function testRevokeLogsOutOnlyThatFamily(): void
    {
        $a = $this->tokens->issue($this->userId, 3600);
        $b = $this->tokens->issue($this->userId, 3600);

        $this->tokens->revoke($a);

        $this->assertRejected($a, 'revoked');
        $this->assertSame($this->userId, $this->tokens->rotate($b, 3600)['user_id']);
    }

    public function testRevokeOfAnUnknownTokenIsSilent(): void
    {
        $this->tokens->revoke('A' . str_repeat('b', 40));
        $this->addToAssertionCount(1);
    }

    public function testRevokeAllForUserKillsEveryDevice(): void
    {
        $other = Db::insert('cms_users', array('username' => 'o', 'email' => 'o@example.com', 'password' => 'x'));
        $a = $this->tokens->issue($this->userId, 3600);
        $b = $this->tokens->issue($this->userId, 3600);
        $c = $this->tokens->issue($other, 3600);

        $this->assertSame(2, $this->tokens->revokeAllForUser($this->userId));

        $this->assertRejected($a, 'revoked');
        $this->assertRejected($b, 'revoked');
        $this->assertSame($other, $this->tokens->rotate($c, 3600)['user_id']);
    }

    public function testDeletedUserCannotRefresh(): void
    {
        $token = $this->tokens->issue($this->userId, 3600);
        Db::query('DELETE FROM cms_users WHERE user_id = ?', array($this->userId));

        $this->assertRejected($token, 'user no longer exists');
    }

    public function testSimultaneousClaimsLetOnlyOneWin(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is not available.');
        }
        // Real clock here: the worker processes use it too.
        $token = (new RefreshTokens())->issue($this->userId, 3600);
        $out = sys_get_temp_dir() . '/wb-rt-race-' . bin2hex(random_bytes(4));
        mkdir($out);

        $pids = [];
        for ($w = 0; $w < 6; $w++) {
            $pid = pcntl_fork();
            if (0 === $pid) {
                Db::forgetConnection();
                try {
                    (new RefreshTokens())->rotate($token, 3600);
                    file_put_contents("$out/$w", 'won');
                } catch (\Throwable $e) {
                    file_put_contents("$out/$w", 'lost');
                }
                function_exists('posix_kill') ? posix_kill(getmypid(), SIGKILL) : exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = array_map('file_get_contents', glob("$out/*"));
        array_map('unlink', glob("$out/*"));
        rmdir($out);

        $this->assertCount(6, $results);
        $this->assertSame(1, count(array_filter($results, fn ($r) => 'won' === $r)), 'exactly one request may redeem a token');
    }
}
