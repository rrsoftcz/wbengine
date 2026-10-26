<?php
namespace Wbengine\Formater\Adapter;

/** Preserve the established formatter adapter shape without template execution. */
final class Markdown
{
    private ?\Wbengine\Markdown $markdown = null;

    public function get(): self { return $this; }

    public function process(string $source): string
    {
        return ($this->markdown ??= new \Wbengine\Markdown())->render($source);
    }
}
