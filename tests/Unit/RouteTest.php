<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Router\Route;

class RouteTest extends TestCase
{
    private function routeMatches(string $route, string $uri): bool
    {
        return (new Route($route))->compare($uri)->isRouteMatch();
    }

    public function testExactPath(): void
    {
        $this->assertTrue($this->routeMatches('/api/users', '/api/users'));
        $this->assertFalse($this->routeMatches('/api/users', '/api/user'));
        $this->assertFalse($this->routeMatches('/api/users', '/api/users/extra'));
    }

    public function testTrailingSlashIsOptionalOnBothSides(): void
    {
        $this->assertTrue($this->routeMatches('/api/users', '/api/users/'));
        $this->assertTrue($this->routeMatches('/api/users/', '/api/users'));
        $this->assertTrue($this->routeMatches('/api/users/', '/api/users/'));
    }

    public function testQueryStringIsIgnored(): void
    {
        $this->assertTrue($this->routeMatches('/api/sections', '/api/sections?x=1&y=2'));
        $this->assertTrue($this->routeMatches('/api/sections/', '/api/sections/?x=1'));
    }

    public function testPlaceholderIsCaptured(): void
    {
        $route = (new Route('/api/users/{id}'))->compare('/api/users/42/');

        $this->assertTrue($route->isRouteMatch());
        $this->assertSame('42', $route->getParams('id'));
    }

    public function testPlaceholderRejectsPathSeparatorsAndQuotes(): void
    {
        $this->assertFalse($this->routeMatches('/api/users/{id}', "/api/users/1'/2"));
        $this->assertFalse($this->routeMatches('/api/users/{id}', '/api/users/1/2'));
    }

    public function testRootRoute(): void
    {
        $this->assertTrue($this->routeMatches('/', '/'));
        $this->assertFalse($this->routeMatches('/', '/x'));
    }
}
