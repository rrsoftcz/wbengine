<?php

namespace Wbengine\Mcp\Tools;

use Wbengine\Mcp\Api\ApiError;
use Wbengine\Mcp\Api\Client;
use Wbengine\Mcp\ToolRegistry;

/** Every tool of the server, registered in one place. */
final class Suite
{
    public static function register(ToolRegistry $tools, Client $api, string $mode = 'full'): void
    {
        $articles = new Articles($api, $mode);
        $facts = $articles->facts();
        $articles->register($tools);
        (new Structure($api, $facts))->register($tools);
        (new Images($api, $facts))->register($tools);
        (new Guide())->register($tools);
        $tools->filterByScopes(static function () use ($facts): ?array {
            try {
                return $facts->scopes();
            } catch (ApiError) {
                return null;
            }
        });
    }
}
