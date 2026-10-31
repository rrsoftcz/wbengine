<?php

namespace Wbengine\Mcp\Api;

/** A successful API answer: the data, the pagination block when there is one and the ETag. */
final class Response
{
    public function __construct(public readonly int $status, public readonly mixed $data, public readonly ?array $pagination, public readonly ?string $etag)
    {
    }
}
