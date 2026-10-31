<?php

namespace Wbengine\Mcp\Tools;

use Wbengine\Mcp\Api\Client;
use Wbengine\Mcp\Tool;
use Wbengine\Mcp\ToolRegistry;
use Wbengine\Mcp\ToolResult;

/**
 * What an article needs around it before it can be seen: a page with a clean address, SEO fields, a box that
 * shows the article, a menu entry and every language. plan_article_page proposes it, check_article checks it
 * and publish_article refuses to publish into a page that cannot show anything.
 */
final class ArticlePages
{
    public const BLOCKER = 'blocker';
    public const WARNING = 'warning';
    public const ADVICE = 'advice';

    public function __construct(private readonly Client $api, private readonly SiteFacts $facts)
    {
    }

    public function register(ToolRegistry $tools): void
    {
        $read = ['readOnly' => true, 'destructive' => false, 'idempotent' => true];
        $tools->add(new Tool('plan_article_page', 'Plan the page of a new article', 'Proposes everything a new article needs around it, without changing anything: a clean address that no page uses, the browser title and description, the box that shows the article, and a menu name. Show the plan to the user, adjust it, then pass the "new_page" object to save_article_draft (and "menu_name" when a menu entry is wanted). Call wbengine_guide with addresses_and_seo for the rules.', ['type' => 'object', 'additionalProperties' => false, 'required' => ['title'], 'properties' => [
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'introtext' => ['type' => 'string', 'maxLength' => 65535, 'description' => 'The introduction of the article; the description for search engines is taken from it.'],
            'prefix' => ['type' => 'string', 'maxLength' => 60, 'description' => 'Where the address lives, for example /blog/. Default: /blog/ when the site has a blog page, else the top level.'],
        ]], $read, fn(array $a) => Guard::run(fn() => $this->plan($a)), ['pages:read']));
        $tools->add(new Tool('check_article', 'Check that an article is ready', 'Checks an article and its page before publishing: the page exists, a box shows the article, the address and the SEO fields are fine, every site language has a translation, a menu entry exists. Lists blockers (publishing is refused), warnings and advice. publish_article runs this itself; call it to show the user what is missing.', ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]]], $read, fn(array $a) => Guard::run(fn() => $this->checkResult((int) $a['id'])), ['articles:read', 'pages:read']));
    }

    private function plan(array $a): ToolResult
    {
        $title = trim($a['title']);
        $prefix = isset($a['prefix']) ? Slug::prefix($a['prefix']) : (null !== $this->facts->pageByLink('/blog/') ? '/blog/' : '/');
        $wanted = Slug::fromTitle($title, $prefix);
        $link = $this->facts->freeLink($wanted);
        $box = $this->facts->articleBoxType();
        $blockers = [];
        foreach (['pages:write' => 'create pages', 'boxes:write' => 'attach the article box'] as $scope => $what) {
            if (!$this->facts->has($scope)) { $blockers[] = 'The key cannot ' . $what . ' (' . $scope . '). The administrator can add the group on the Integrations page.'; }
        }
        if (null === $box) { $blockers[] = 'The site has no box type that shows articles, so a new page would stay empty. The administrator must register one (the skeleton registers "story"); until then write only into pages that already show articles (list_pages marks them).'; }
        $page = ['title' => mb_substr($title, 0, 120), 'link' => $link, 'html_title' => Slug::titleTag($title)];
        $description = Slug::description((string) ($a['introtext'] ?? ''));
        if (null !== $description) { $page['html_description'] = $description; }
        if (null !== $box) {
            $page['box_type'] = $box['type'];
            $page['section'] = in_array('central', $box['sections'], true) ? 'central' : ($box['sections'][0] ?? 'central');
        }
        $lines = ['Proposed page for the article "' . $title . '":', 'Address: ' . $link . ($link !== $wanted ? ' (' . $wanted . ' is taken)' : ''), 'Browser title: ' . $page['html_title'] . ' (the site name is added by the template)', 'Description: ' . ($description ?? 'none yet: write 120 to 160 characters that say what the page is about'), 'Article box: ' . (null === $box ? 'none available' : $box['type'] . ' in section ' . $page['section']), 'Menu entry (optional): ' . Slug::menuName($title)];
        foreach ($blockers as $blocker) { $lines[] = 'Blocker: ' . $blocker; }
        $lines[] = [] === $blockers ? 'Show this to the user, then call save_article_draft with this object as new_page.' : 'Resolve the blockers before creating the page.';
        return ToolResult::ok(implode("\n", $lines), ['new_page' => $page, 'menu_name' => Slug::menuName($title), 'blockers' => $blockers]);
    }

    private function checkResult(int $id): ToolResult
    {
        $findings = $this->check($id);
        $blockers = array_filter($findings, static fn($f) => self::BLOCKER === $f['level']);
        $lines = [[] === $blockers ? 'Ready to publish: no blockers.' : 'Not ready: ' . count($blockers) . ' blocker(s).'];
        foreach ([self::BLOCKER, self::WARNING, self::ADVICE] as $level) {
            foreach ($findings as $f) {
                if ($level === $f['level']) { $lines[] = ucfirst($level) . ': ' . $f['text']; }
            }
        }
        return ToolResult::ok(implode("\n", $lines), ['ready' => [] === $blockers, 'findings' => $findings]);
    }

    /** @return array<int, array{level: string, code: string, text: string}> */
    public function check(int $id): array
    {
        $article = (array) $this->api->request('GET', '/articles/' . $id)->data;
        $draft = is_array($article['draft'] ?? null) ? $article['draft'] : null;
        $content = (array) ($draft['content'] ?? $article);
        $pageId = (int) ($content['site_id'] ?? $article['site_id'] ?? 0);
        $f = [];
        $add = static function (string $level, string $code, string $text) use (&$f): void { $f[] = ['level' => $level, 'code' => $code, 'text' => $text]; };
        if (!empty($article['hidden'])) { $add(self::ADVICE, 'hidden', 'The article is hidden: its address answers 404 until unhide_article.'); }
        if ('' === trim((string) ($content['bodytext'] ?? '') . (string) ($content['introtext'] ?? ''))) { $add(self::BLOCKER, 'empty_text', 'The article has no text.'); }
        $page = $pageId > 0 ? Guard::optional(fn() => $this->facts->page($pageId), false) : null;
        if (false === $page) {
            $add(self::ADVICE, 'page_unchecked', 'The key cannot read pages, so the page of the article was not checked.');
            return $f;
        }
        if (null === $page) {
            $add(self::BLOCKER, 'no_page', 'The article has no page. Save the draft with page_id or new_page.');
            return $f;
        }
        $boxes = Guard::optional(fn() => $this->facts->boxesOnPage($pageId));
        if (null === $boxes) {
            $add(self::ADVICE, 'boxes_unchecked', 'The key cannot read boxes, so it was not checked that the page shows the article.');
        } elseif ([] === $boxes) {
            $add(self::BLOCKER, 'no_box', 'Page #' . $pageId . ' has no box, so its address would be empty. Add a box that shows articles (add_box with the article box type, see list_box_types) or use a page that already shows articles.');
        } else {
            $rendering = array_values(array_filter($boxes, static fn($b) => !empty($b['renders_articles'])));
            if ([] === $rendering) {
                $add(self::WARNING, 'box_unknown', 'None of the boxes on the page is marked as showing articles. If the page was built for articles this is fine; open the address after publishing to see the text.');
            } else {
                $sections = (array) Guard::optional(fn() => $this->facts->sections(), []);
                $active = array_map('intval', array_column(array_filter($sections, static fn($s) => !empty($s['active'])), 'section_id'));
                $shown = [] === $sections ? $rendering : array_filter($rendering, static fn($b) => in_array((int) $b['section_id'], $active, true));
                if ([] === $shown) { $add(self::WARNING, 'section_inactive', 'The box that shows the article is in a section that is not active, so it is not displayed.'); }
            }
        }
        $titleTag = (string) ($page['html_title'] ?? '');
        if ('' === $titleTag) { $add(self::WARNING, 'seo_title', 'The page has no browser title (html_title). Set it with update_page; at most 60 characters, without the site name.'); }
        elseif (mb_strlen($titleTag) > 60) { $add(self::ADVICE, 'seo_title_long', 'The browser title is ' . mb_strlen($titleTag) . ' characters; search engines cut it near 60.'); }
        $description = (string) ($page['html_description'] ?? '');
        if ('' === $description) { $add(self::WARNING, 'seo_description', 'The page has no description (html_description). Set it with update_page; 120 to 160 characters.'); }
        elseif (mb_strlen($description) > 160) { $add(self::ADVICE, 'seo_description_long', 'The description is ' . mb_strlen($description) . ' characters; search engines cut it near 160.'); }
        $have = array_merge(array_keys((array) ($article['translations'] ?? [])), array_keys((array) ($draft['translations'] ?? [])));
        $missing = array_values(array_diff($this->facts->locales(), [$this->facts->defaultLocale()], $have));
        if ([] !== $missing) { $add(self::WARNING, 'translation_missing', 'No translation for: ' . implode(', ', $missing) . '. Visitors in those languages see the main language. Save translations with save_article_draft.'); }
        $others = array_values(array_diff($this->facts->locales(), [$this->facts->defaultLocale()]));
        if ([] !== $others && array_key_exists('translation_locales', $page)) {
            $noPage = array_values(array_diff($others, (array) $page['translation_locales']));
            if ([] !== $noPage) { $add(self::WARNING, 'page_translation_missing', 'The page has no translation of its title, browser title and description for: ' . implode(', ', $noPage) . '. In those languages the browser tab and the search result stay in the main language. Add them with update_page (translations).'); }
        }
        $link = (string) ($page['link'] ?? '');
        if (!Slug::valid($link) || $link !== strtolower($link)) { $add(self::ADVICE, 'address', 'The address ' . $link . ' is not a clean lowercase address.'); }
        $menu = Guard::optional(fn() => $this->facts->all('/menu'));
        $mine = null === $menu ? [] : array_values(array_filter($menu, static fn($m) => (int) $m['site_id'] === $pageId));
        if (null !== $menu && [] === $mine) { $add(self::ADVICE, 'no_menu', 'The page has no menu entry. Add one with set_menu_entry if visitors should find it there.'); }
        foreach ($mine as $entry) {
            $noMenu = array_values(array_diff($others, (array) ($entry['translation_locales'] ?? $others)));
            if (array_key_exists('translation_locales', $entry) && [] !== $noMenu) { $add(self::WARNING, 'menu_translation_missing', 'The menu entry "' . str_replace('"', "'", (string) $entry['name']) . '" has no translation for: ' . implode(', ', $noMenu) . '. Visitors in those languages see the main language name. Add it with set_menu_entry (translations).'); break; }
        }
        return $f;
    }

    /** The blocker text when the page has no box at all (the published address would be empty), else null. */
    public function boxBlocker(int $pageId): ?string
    {
        $boxes = Guard::optional(fn() => $this->facts->boxesOnPage($pageId));
        return [] === $boxes ? 'Warning: page #' . $pageId . ' has no box, so its address stays empty even when the article is published. Add a box that shows articles (add_box, see list_box_types) or use a page that already shows articles.' : null;
    }
}
