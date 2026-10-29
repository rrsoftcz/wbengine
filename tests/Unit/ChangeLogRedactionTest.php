<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Audit\ChangeLog;

final class ChangeLogRedactionTest extends TestCase
{
    public function testSecretKeysAreRedactedAtEveryDepthAndOtherValuesStay(): void
    {
        $redacted = ChangeLog::redact([
            'email' => 'a@example.com', 'password' => 'hunter2', 'role' => 'admin',
            'nested' => ['token' => 'abc', 'refresh_token' => 'def', 'list' => [['secret' => 's', 'title' => 'ok']]],
            'ac_key' => 'activation', 'key_hash' => 'x', 'apiKey' => 'k', 'Password' => 'P',
        ]);
        self::assertSame('a@example.com', $redacted['email']);
        self::assertSame('admin', $redacted['role']);
        self::assertSame('ok', $redacted['nested']['list'][0]['title']);
        foreach (['password', 'ac_key', 'key_hash', 'apiKey', 'Password'] as $name) { self::assertSame(ChangeLog::REDACTED, $redacted[$name], $name); }
        self::assertSame(ChangeLog::REDACTED, $redacted['nested']['token']);
        self::assertSame(ChangeLog::REDACTED, $redacted['nested']['refresh_token']);
        self::assertSame(ChangeLog::REDACTED, $redacted['nested']['list'][0]['secret']);
        self::assertStringNotContainsString('hunter2', json_encode($redacted));
    }

    #[DataProvider('names')]
    public function testSecretNameMatchesWholeWordsOnly(string $name, bool $secret): void
    {
        self::assertSame($secret, ChangeLog::secretName($name));
    }

    public static function names(): array
    {
        return [
            ['password', true], ['token', true], ['secret', true], ['key', true], ['hash', true],
            ['user_password', true], ['access_token', true], ['key_hash', true], ['apiKey', true], ['clientSecret', true],
            ['monkey', false], ['keyboard', false], ['hashtag', false], ['title', false], ['passwordChanged', false], ['password_changed', true], ['section_id', false],
        ];
    }

    public function testExemptKeysStayVisible(): void
    {
        $redacted = ChangeLog::redact(['key' => 'central', 'password' => 'x'], ['key']);
        self::assertSame('central', $redacted['key']);
        self::assertSame(ChangeLog::REDACTED, $redacted['password']);
    }

    public function testScalarsPassThroughAndObjectsAreRedactedToo(): void
    {
        self::assertSame('plain', ChangeLog::redact('plain'));
        self::assertSame(['token' => ChangeLog::REDACTED, 'a' => 1], ChangeLog::redact((object) ['token' => 't', 'a' => 1]));
    }

    public function testCliActorIsPrefixedAndContainsNoControlCharacters(): void
    {
        $actor = ChangeLog::cliActor()['central_actor'];
        self::assertMatchesRegularExpression('/^cli:[A-Za-z0-9._-]{1,100}$/D', $actor);
    }

    public function testUnknownSourceIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ChangeLog::record('user', 1, 'create', null, null, [], 'web');
    }
}
