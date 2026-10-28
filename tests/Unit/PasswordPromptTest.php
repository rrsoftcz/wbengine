<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Console\CommandException;
use Wbengine\Console\PasswordPrompt;
use Wbengine\Console\UserCommands;

final class PasswordPromptTest extends TestCase
{
    private function stream(string $data)
    {
        $stream = fopen('php://memory', 'w+'); fwrite($stream, $data); rewind($stream);
        return $stream;
    }
    public function testWithoutTerminalReadsExactlyOneLineFromStdin(): void
    {
        $prompt = new PasswordPrompt($this->stream("correct horse battery\nsecond line\n"), $this->stream(''), false);
        self::assertSame('correct horse battery', $prompt->read());
    }
    public function testKeepsLeadingAndTrailingSpacesAndStripsCrLf(): void
    {
        self::assertSame('  pass word  ', (new PasswordPrompt($this->stream("  pass word  \r\n"), $this->stream(''), false))->read());
    }
    public function testEmptyInputIsAnErrorNotAnEmptyPassword(): void
    {
        $this->expectException(CommandException::class);
        (new PasswordPrompt($this->stream(''), $this->stream(''), false))->read();
    }
    public function testTerminalReadsWithoutEchoConfirmsAndAlwaysRestoresTheTerminal(): void
    {
        $calls = [];
        $stty = function (string $argument) use (&$calls): string { $calls[] = $argument; return $argument === '-g' ? "saved-state\n" : ''; };
        $out = $this->stream('');
        $prompt = new PasswordPrompt($this->stream("long enough secret\nlong enough secret\n"), $out, true, $stty);
        self::assertSame('long enough secret', $prompt->read());
        self::assertSame(['-g', '-echo', "'saved-state'", '-g', '-echo', "'saved-state'"], $calls);
        rewind($out);
        self::assertStringNotContainsString('secret', stream_get_contents($out));
    }
    public function testMismatchedConfirmationIsRefusedAndEchoRestored(): void
    {
        $calls = [];
        $stty = function (string $argument) use (&$calls): string { $calls[] = $argument; return $argument === '-g' ? 'state' : ''; };
        try {
            (new PasswordPrompt($this->stream("one one one one\ntwo two two two\n"), $this->stream(''), true, $stty))->read();
            self::fail('Expected a mismatch');
        } catch (CommandException $e) {
            self::assertStringContainsString('do not match', $e->getMessage());
            self::assertSame("'state'", end($calls));
        }
    }
    public function testEchoIsRestoredWhenReadingFails(): void
    {
        $calls = [];
        $stty = function (string $argument) use (&$calls): string { $calls[] = $argument; return $argument === '-g' ? 'state' : ''; };
        try { (new PasswordPrompt($this->stream(''), $this->stream(''), true, $stty))->read(); self::fail('Expected EOF error'); }
        catch (CommandException) { self::assertSame(['-g', '-echo', "'state'"], $calls); }
    }
    public function testTerminalWithoutSttyFailsClosed(): void
    {
        $this->expectException(CommandException::class);
        (new PasswordPrompt($this->stream("x\n"), $this->stream(''), true, static fn (): string => ''))->read();
    }
    public function testExampleDomainsMatchWholeLabelsOnly(): void
    {
        foreach (['a@example.com', 'a@EXAMPLE.org', 'a@mail.example.net', 'a@x.example', 'a@host.test', 'a@foo.invalid', 'a@localhost', 'a@example.com.'] as $email) {
            self::assertTrue(UserCommands::isExampleEmail($email), $email);
        }
        foreach (['a@notexample.com', 'a@example.com.cz', 'a@example.cz', 'a@mytest.com', 'a@contest', 'a@test.cz', 'example.com', 'a@corp.cz'] as $email) {
            self::assertFalse(UserCommands::isExampleEmail($email), $email);
        }
    }
}
