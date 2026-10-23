<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Error\Exception\ErrorHandlerException;
use Wbengine\Error\Handler;

class ErrorHandlerTest extends TestCase
{
    private int $previousReporting;

    protected function setUp(): void
    {
        // PHPUnit lowers error_reporting while it runs, the handler honors it.
        $this->previousReporting = error_reporting(E_ALL);
    }

    protected function tearDown(): void
    {
        error_reporting($this->previousReporting);
    }

    public function testEmptyHandlerHasNoErrors(): void
    {
        $handler = new Handler();

        $this->assertSame(0, $handler->getcount());
        $this->assertSame('No errors found...', (string)$handler);
    }

    public function testWarningBecomesException(): void
    {
        $handler = new Handler();

        $this->expectException(ErrorHandlerException::class);
        $handler->SetErrorHandler(E_WARNING, 'boom', 'file.php', 10);
    }

    public function testDeprecationsAndNoticesDoNotThrow(): void
    {
        $handler = new Handler();

        $this->assertFalse($handler->SetErrorHandler(E_DEPRECATED, 'old', 'file.php', 1));
        $this->assertFalse($handler->SetErrorHandler(E_USER_DEPRECATED, 'old', 'file.php', 1));
        $this->assertFalse($handler->SetErrorHandler(E_NOTICE, 'note', 'file.php', 1));
        $this->assertSame(0, $handler->getcount());
    }

    public function testSuppressedErrorIsIgnored(): void
    {
        $handler = new Handler();
        $previous = error_reporting(0);
        try {
            $this->assertFalse($handler->SetErrorHandler(E_WARNING, 'silenced', 'file.php', 1));
        } finally {
            error_reporting($previous);
        }
    }

    public function testErrorIsCountedBeforeThrowing(): void
    {
        $handler = new Handler();
        try {
            $handler->SetErrorHandler(E_WARNING, 'boom', 'file.php', 10);
        } catch (ErrorHandlerException $e) {
        }

        $this->assertSame(1, $handler->getcount());
        $this->assertStringContainsString('boom', (string)$handler);
    }
}
