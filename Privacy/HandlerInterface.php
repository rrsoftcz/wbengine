<?php

namespace Wbengine\Privacy;

interface HandlerInterface
{
    public function name(): string;

    /** Return a redacted summary and an opaque fingerprint for one target user. */
    public function inspect(int $userId): array;

    /** Perform the handler's deletion under a transaction using its inspection fingerprint. */
    public function erase(int $userId, string $fingerprint): void;
}
