<?php
namespace Wbengine;

use Wbengine\Markdown\Parser;
use Wbengine\Markdown\HtmlRenderer;

/** Safe content renderer. Markdown is content and never executes template code. */
final class Markdown
{
    public function __construct(private readonly Parser $parser = new Parser(), private readonly HtmlRenderer $renderer = new HtmlRenderer()) {}

    public function render(string $source): string
    {
        return $this->renderer->render($this->parser->parse($source));
    }
}
