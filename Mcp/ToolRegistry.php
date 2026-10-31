<?php

namespace Wbengine\Mcp;

/** The tools a server offers, in a stable order. */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];
    /** @var null|callable(): ?array<int, string> */
    private $scopeProvider = null;
    /** @var null|string[] */
    private ?array $scopes = null;

    public function add(Tool $tool): self
    {
        if (isset($this->tools[$tool->name])) {
            throw new \InvalidArgumentException('Duplicate tool: ' . $tool->name);
        }
        $this->tools[$tool->name] = $tool;
        return $this;
    }

    /**
     * Offer only the tools whose scopes the key has. The provider returns the scopes of the key, or null when
     * they cannot be known (the site is unreachable): then every tool is offered and the API refuses what the key
     * cannot do. A known answer is kept for the life of the process.
     *
     * @param callable(): ?array<int, string> $provider
     */
    public function filterByScopes(callable $provider): self
    {
        $this->scopeProvider = $provider;
        return $this;
    }

    public function get(string $name): ?Tool
    {
        $tool = $this->tools[$name] ?? null;
        return null !== $tool && $this->offered($tool) ? $tool : null;
    }

    /** @return Tool[] */
    public function all(): array
    {
        return array_values(array_filter($this->tools, fn(Tool $tool) => $this->offered($tool)));
    }

    private function offered(Tool $tool): bool
    {
        if ([] === $tool->scopes || null === $this->scopeProvider) { return true; }
        $this->scopes ??= ($this->scopeProvider)();
        return null === $this->scopes || [] === array_diff($tool->scopes, $this->scopes);
    }
}
