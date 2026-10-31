<?php

namespace Wbengine\Mcp\Tools;

use Wbengine\Mcp\Tool;
use Wbengine\Mcp\ToolError;
use Wbengine\Mcp\ToolRegistry;
use Wbengine\Mcp\ToolResult;

/** How WBengine works, as short texts shipped with the server (Mcp/Guide/*.md). They are ours, not site data. */
final class Guide
{
    private readonly string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? dirname(__DIR__) . '/Guide';
    }

    /** @return string[] topic names in reading order */
    public function topics(): array
    {
        $order = ['overview', 'publish_article', 'addresses_and_seo', 'boxes', 'menu', 'languages', 'images', 'statistics', 'troubleshooting'];
        $found = [];
        // scandir, not glob: glob does not work inside a phar
        foreach (@scandir($this->dir) ?: [] as $file) {
            if (str_ends_with($file, '.md')) { $found[] = basename($file, '.md'); }
        }
        $known = array_values(array_intersect($order, $found));
        $rest = array_values(array_diff($found, $order));
        sort($rest);
        return array_merge($known, $rest);
    }

    public function text(string $topic): ?string
    {
        if (!in_array($topic, $this->topics(), true)) { return null; }
        $text = file_get_contents($this->dir . '/' . $topic . '.md');
        return false === $text ? null : $text;
    }

    /** The first heading of a topic, as its title. */
    public function title(string $topic): string
    {
        return ltrim((string) strtok((string) $this->text($topic), "\n"), '# ');
    }

    public function register(ToolRegistry $tools): void
    {
        $topics = $this->topics();
        $tools->add(new Tool('wbengine_guide', 'How WBengine works', 'Short texts that explain how a WBengine site works and how to do common jobs: overview, publish_article (the steps of publishing a new article), addresses_and_seo, boxes, menu, languages, images, troubleshooting. Call it with no topic to list them. Read overview first when you do not know the site.', ['type' => 'object', 'additionalProperties' => false, 'properties' => ['topic' => ['type' => 'string', 'enum' => $topics]]], ['readOnly' => true, 'destructive' => false, 'idempotent' => true, 'openWorld' => false], function (array $a): ToolResult {
            if (!isset($a['topic'])) {
                return ToolResult::ok("Topics:\n" . implode("\n", array_map(fn($t) => $t . ': ' . $this->title($t), $this->topics())), ['topics' => $this->topics()]);
            }
            return ToolResult::ok($this->text($a['topic']) ?? throw new ToolError('Unknown topic.'), ['topic' => $a['topic']]);
        }));
    }
}
