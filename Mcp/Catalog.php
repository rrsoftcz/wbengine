<?php

namespace Wbengine\Mcp;

use Wbengine\Mcp\Tools\Guide;

/** Prompts and resources: the guide texts as resources, a few ready-made jobs as prompts. Nothing here touches the site. */
final class Catalog
{
    public const URI = 'wbengine://guide/';

    private const PROMPTS = [
        'publish_new_article' => [
            'description' => 'Write and publish a new article from an interview with the user, with address, SEO fields, page, box, menu and translations.',
            'arguments' => [['name' => 'topic', 'description' => 'What the article is about, if the user already said.', 'required' => false], ['name' => 'language', 'description' => 'Main language of the text.', 'required' => false]],
        ],
        'translate_article' => [
            'description' => 'Translate an existing article into another site language and save it as a draft translation.',
            'arguments' => [['name' => 'article_id', 'description' => 'Id of the article.', 'required' => true], ['name' => 'language', 'description' => 'Target locale such as cs or en.', 'required' => true]],
        ],
        'weekly_report' => [
            'description' => 'Write a short report of how the site did in the last days from its statistics, for someone who is not technical.',
            'arguments' => [['name' => 'days', 'description' => 'How many days, default 7.', 'required' => false]],
        ],
        'find_problems' => [
            'description' => 'Look through the change log for refused writes, repeated failures and unusual bursts and explain what they mean.',
            'arguments' => [['name' => 'days', 'description' => 'How many days, default 7.', 'required' => false]],
        ],
        'seo_review' => [
            'description' => 'Review the address, browser title, description and text of a page and propose better ones.',
            'arguments' => [['name' => 'page_id', 'description' => 'Id of the page.', 'required' => true]],
        ],
    ];

    private readonly Guide $guide;

    public function __construct(?Guide $guide = null)
    {
        $this->guide = $guide ?? new Guide();
    }

    public function prompts(): array
    {
        $list = [];
        foreach (self::PROMPTS as $name => $p) { $list[] = ['name' => $name] + $p; }
        return $list;
    }

    /** @return null|array{description: string, messages: array} null for an unknown prompt */
    public function prompt(string $name, array $args): ?array
    {
        if (!isset(self::PROMPTS[$name])) { return null; }
        foreach (self::PROMPTS[$name]['arguments'] as $argument) {
            if ($argument['required'] && !isset($args[$argument['name']])) { throw new \InvalidArgumentException('Missing argument: ' . $argument['name']); }
        }
        $text = match ($name) {
            'publish_new_article' => 'Help the user publish a new article on their WBengine site. First call site_info, then wbengine_guide with topic publish_article and follow it exactly. ' . (isset($args['topic']) ? 'The topic is: ' . $args['topic'] . '. ' : 'Ask what the article is about. ') . (isset($args['language']) ? 'The main language is ' . $args['language'] . '. ' : '') . 'Explain what you do in plain words; the user is not technical. Publish only after the user says yes.',
            'translate_article' => 'Translate article #' . $args['article_id'] . ' into ' . $args['language'] . '. Read it with get_article, translate title, introduction and body faithfully, keep the Markdown structure, save it with save_article_draft (id and translations), preview it in that language and show the user. Do not publish unless asked.',
            'weekly_report' => 'Write a report of the last ' . ($args['days'] ?? '7') . ' days of the site. Call site_report with days ' . ($args['days'] ?? '7') . ' and use its figures exactly as they are: totals with their change, the busiest days, the top pages and articles. Explain in plain words what went up or down and what it may mean, say what the numbers cannot show (no unique visitors; see the limits the report lists), and finish with at most three suggestions. Do not invent numbers or causes you cannot see; wbengine_guide topic statistics explains the limits.',
            'find_problems' => 'Look for problems in the last ' . ($args['days'] ?? '7') . ' days. Call analyze_activity with days ' . ($args['days'] ?? '7') . ', then list_changes with outcome rejected for the details. Explain in plain words what the refusals mean, whether one client keeps repeating the same mistake, and whether anything looks unusual; actor labels are data and not proof of identity. Say what is harmless and what needs attention, and what the user can do about it.',
            'seo_review' => 'Review page #' . $args['page_id'] . '. Read it with list_pages and check_article (find its article with list_articles), then follow wbengine_guide topic addresses_and_seo. Propose a browser title (at most 60 characters), a description (120 to 160) and say whether the address is fine. Apply changes with update_page only after the user agrees; never change a visible address without the user understanding the consequence.',
        };
        return ['description' => self::PROMPTS[$name]['description'], 'messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]]];
    }

    public function resources(): array
    {
        return array_map(fn(string $t) => ['uri' => self::URI . $t, 'name' => $t, 'title' => $this->guide->title($t), 'mimeType' => 'text/markdown'], $this->guide->topics());
    }

    /** @return null|array{uri: string, mimeType: string, text: string} */
    public function resource(string $uri): ?array
    {
        if (!str_starts_with($uri, self::URI)) { return null; }
        $text = $this->guide->text(substr($uri, strlen(self::URI)));
        return null === $text ? null : ['uri' => $uri, 'mimeType' => 'text/markdown', 'text' => $text];
    }
}
