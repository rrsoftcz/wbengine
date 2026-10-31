<?php

namespace Wbengine\Mcp;

/** One tool: name, description, JSON Schema of the input, behaviour hints and the handler. */
final class Tool
{
    /** @var callable(array): ToolResult */
    private $handler;

    /**
     * @param array<string, mixed> $inputSchema JSON Schema (2020-12 subset understood by Schema)
     * @param array{readOnly: bool, destructive: bool, idempotent: bool, openWorld?: bool} $hints
     * @param callable(array): ToolResult $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly array $inputSchema,
        private readonly array $hints,
        callable $handler,
    ) {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,128}$/', $name)) {
            throw new \InvalidArgumentException('Invalid tool name: ' . $name);
        }
        $this->handler = $handler;
    }

    public function run(array $arguments): ToolResult
    {
        return ($this->handler)($arguments);
    }

    /** The tool as listed by tools/list. */
    public function definition(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
            'annotations' => [
                'title' => $this->title,
                'readOnlyHint' => $this->hints['readOnly'],
                'destructiveHint' => $this->hints['destructive'],
                'idempotentHint' => $this->hints['idempotent'],
                'openWorldHint' => $this->hints['openWorld'] ?? true,
            ],
        ];
    }
}
