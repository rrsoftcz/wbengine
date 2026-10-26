<?php
namespace Wbengine\Markdown;

/** Internal content tree shared by the block parser, inline parser and renderer. */
final readonly class Node
{
    /** @param list<Node> $children @param array<string, mixed> $attributes */
    public function __construct(public string $type, public string $literal = '', public array $children = [], public array $attributes = []) {}
}
