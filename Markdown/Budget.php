<?php
namespace Wbengine\Markdown;

/** Shared allocation/work budget for one document, including nested inline parses. */
final class Budget
{
    private int $work = 0;
    private int $nodes = 0;

    public function __construct(private readonly int $maxWork, private readonly int $maxNodes) {}

    public function work(int $amount = 1): void
    {
        if ($amount < 0 || $amount > $this->maxWork - $this->work) { throw new \LengthException('Markdown work limit exceeded'); }
        $this->work += $amount;
    }

    public function node(string $type, string $literal = '', array $children = [], array $attributes = []): Node
    {
        if (++$this->nodes > $this->maxNodes) { throw new \LengthException('Markdown node allocation limit exceeded'); }
        return new Node($type, $literal, $children, $attributes);
    }
}
