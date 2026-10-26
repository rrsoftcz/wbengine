<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Application\Application;
use Wbengine\Url;

class UrlRequestTest extends TestCase
{
    public static function links(): array
    {
        return [
            'missing rewrite' => [[], '/login/', ['login']],
            'null rewrite' => [['link' => null], '/login/', ['login']],
            'array rewrite' => [['link' => ['bad']], '/login/', ['login']],
            'legacy rewrite' => [['link' => 'front'], '/', ['front']],
        ];
    }

    #[DataProvider('links')]
    public function testMissingOrNonStringRewriteFallsBackToRequestPath(array $request, string $uri, array $parts): void
    {
        $previous = [$_REQUEST, $_SERVER];
        $_REQUEST = $request;
        $_SERVER += ['HTTP_HOST' => 'example.test', 'SERVER_PROTOCOL' => 'HTTP/1.1'];
        $_SERVER['REQUEST_URI'] = $uri;
        set_error_handler(static function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });
        try {
            $url = new Url((new \ReflectionClass(Application::class))->newInstanceWithoutConstructor());
            $this->assertSame($uri, $url->getUrl());
            $this->assertSame($parts, $url->getUrlParts());
        } finally {
            restore_error_handler();
            [$_REQUEST, $_SERVER] = $previous;
        }
    }
}
