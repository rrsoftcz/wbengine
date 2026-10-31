<?php

namespace Wbengine\Mcp;

/** The tools a server offers, in a stable order. */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function add(Tool $tool): self
    {
        if (isset($this->tools[$tool->name])) {
            throw new \InvalidArgumentException('Duplicate tool: ' . $tool->name);
        }
        $this->tools[$tool->name] = $tool;
        return $this;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return Tool[] */
    public function all(): array
    {
        return array_values($this->tools);
    }
}
