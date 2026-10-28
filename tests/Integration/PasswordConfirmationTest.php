<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Db;
use Wbengine\RateLimit\Limiter;
use Wbengine\User\PasswordConfirmation;
use Wbengine\User\TooManyAttemptsException;

class PasswordConfirmationTest extends DbTestCase
{
    private string $dir;
    private int $now = 2_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        Db::insert('cms_users', ['user_id' => 31, 'username' => 'modern', 'password' => password_hash('modern-secret', PASSWORD_DEFAULT)]);
        Db::insert('cms_users', ['user_id' => 32, 'username' => 'legacy', 'password' => md5('legacy-secret')]);
        Db::insert('cms_users', ['user_id' => 33, 'username' => 'empty', 'password' => '0']);
        $this->dir = sys_get_temp_dir() . '/wb-reauth-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) { @unlink($file); }
        @rmdir($this->dir);
    }

    private function confirmation(): PasswordConfirmation
    {
        return new PasswordConfirmation(new Limiter($this->dir, fn () => $this->now), 900);
    }

    public function testCorrectPasswordConfirmsModernAndLegacyHashes(): void
    {
        $this->assertTrue($this->confirmation()->confirm(31, 'modern-secret', '10.0.0.1'));
        $this->assertTrue($this->confirmation()->confirm(32, 'legacy-secret', '10.0.0.1'));
    }

    public function testWrongPasswordAndEveryUnusableInputFail(): void
    {
        foreach ([[31, 'wrong'], [31, ''], [31, null], [31, ['modern-secret']], [31, 0], [31, str_repeat('a', 5000)], [32, 'modern-secret'], [33, '0'], [999, 'x'], [0, 'x'], [-1, 'x']] as [$id, $input]) {
            $this->assertFalse($this->confirmation()->confirm($id, $input, '10.0.0.2' . abs($id)), json_encode([$id, $input]));
            $this->now += 1000;
        }
    }

    public function testTheSubmittedValueNotTheUsernameDecidesAndAnotherUsersPasswordFails(): void
    {
        $this->assertFalse($this->confirmation()->confirm(31, 'legacy-secret', '10.0.0.3'));
    }

    public function testFifthFailureBlocksEvenTheCorrectPasswordUntilTheWindowEnds(): void
    {
        for ($i = 0; $i < PasswordConfirmation::USER_ATTEMPTS; $i++) {
            $this->assertFalse($this->confirmation()->confirm(31, 'wrong' . $i, '10.0.0.4'));
        }
        try {
            $this->confirmation()->confirm(31, 'modern-secret', '10.0.0.5');
            $this->fail('The limit did not apply');
        } catch (TooManyAttemptsException $e) {
            $this->assertGreaterThan(0, $e->getRetryAfter());
            $this->assertLessThanOrEqual(900, $e->getRetryAfter());
        }
        $this->now += 901;
        $this->assertTrue($this->confirmation()->confirm(31, 'modern-secret', '10.0.0.5'));
    }

    public function testSuccessClearsTheUserCounterButOtherUsersAreIndependent(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->assertFalse($this->confirmation()->confirm(31, 'wrong', '10.0.0.6'));
        }
        $this->assertTrue($this->confirmation()->confirm(31, 'modern-secret', '10.0.0.6'));
        for ($i = 0; $i < 4; $i++) {
            $this->assertFalse($this->confirmation()->confirm(31, 'wrong', '10.0.0.6'));
        }
        $this->assertTrue($this->confirmation()->confirm(32, 'legacy-secret', '10.0.0.6'));
    }

    public function testOneClientIpIsLimitedAcrossUsers(): void
    {
        for ($i = 0; $i < PasswordConfirmation::IP_ATTEMPTS; $i++) {
            $this->assertFalse($this->confirmation()->confirm(1000 + $i, 'wrong', '10.0.0.7'));
        }
        $this->expectException(TooManyAttemptsException::class);
        $this->confirmation()->confirm(31, 'modern-secret', '10.0.0.7');
    }

    public function testThePasswordIsNeverWrittenToTheErrorLog(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'wb-reauth-log');
        $previous = ini_set('error_log', $log);
        try {
            $this->confirmation()->confirm(31, 'sentinel-password-value', '10.0.0.8');
            $this->confirmation()->confirm(999, 'sentinel-password-value', '10.0.0.8');
        } finally {
            ini_set('error_log', $previous);
        }
        $this->assertStringNotContainsString('sentinel-password-value', (string) file_get_contents($log));
        unlink($log);
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            $this->assertStringNotContainsString('sentinel-password-value', (string) file_get_contents($file));
        }
    }
}
