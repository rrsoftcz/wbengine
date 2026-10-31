<?php

namespace Wbengine\Mcp\Tools;

use Wbengine\Mcp\Api\ApiError;
use Wbengine\Mcp\Api\Client;
use Wbengine\Mcp\ToolError;

/** What the tools need to know about the site: the key, the languages, the box catalog, the sections, the boxes. */
final class SiteFacts
{
    private ?array $caps = null;
    private ?array $identity = null;

    public function __construct(private readonly Client $api)
    {
    }

    public function capabilities(): array
    {
        return $this->caps ??= (array) $this->api->request('GET', '/capabilities')->data;
    }

    public function identity(): array
    {
        return $this->identity ??= (array) $this->api->request('GET', '/identity')->data;
    }

    /** @return string[] */
    public function scopes(): array
    {
        return array_values((array) ($this->identity()['scopes'] ?? []));
    }

    public function has(string ...$needed): bool
    {
        return [] === array_diff($needed, $this->scopes());
    }

    /** @return string[] */
    public function locales(): array
    {
        return array_values((array) ($this->capabilities()['i18n']['locales'] ?? []));
    }

    public function defaultLocale(): string
    {
        return (string) ($this->capabilities()['i18n']['default'] ?? 'en');
    }

    /** @return array<int, array{type: string, sections: string[], renders: ?string}> */
    public function boxTypes(): array
    {
        return array_map(static fn($t) => ['type' => (string) $t['type'], 'sections' => array_values((array) ($t['sections'] ?? [])), 'renders' => $t['renders'] ?? null], (array) ($this->capabilities()['box_types'] ?? []));
    }

    /** The first registered box type that renders articles, or null. @return null|array{type: string, sections: string[], renders: ?string} */
    public function articleBoxType(): ?array
    {
        foreach ($this->boxTypes() as $type) {
            if ('article' === $type['renders']) { return $type; }
        }
        return null;
    }

    /** All rows of a collection, 100 per request, at most $max rows. @return array<int, array> */
    public function all(string $path, array $query = [], int $max = 500): array
    {
        $rows = [];
        for ($page = 1; count($rows) < $max; $page++) {
            $r = $this->api->request('GET', $path, ['per_page' => 100, 'page' => $page] + $query);
            foreach ((array) $r->data as $row) { $rows[] = $row; }
            if ($page * 100 >= (int) ($r->pagination['total'] ?? 0)) { break; }
        }
        return array_slice($rows, 0, $max);
    }

    /** @return array<int, array> */
    public function sections(): array
    {
        return $this->all('/sections');
    }

    /** The id of a section given by its key or its id; ToolError when there is none. */
    public function sectionId(string|int $section): int
    {
        foreach ($this->sections() as $row) {
            if ((string) $row['key'] === (string) $section || ((int) $row['section_id'] === (int) $section && ctype_digit((string) $section))) { return (int) $row['section_id']; }
        }
        throw new ToolError('Unknown section "' . $section . '". Use list_sections to see the keys.');
    }

    /** Boxes shown on a page: listed for the page or shared. @return array<int, array> */
    public function boxesOnPage(int $pageId): array
    {
        return array_values(array_filter($this->all('/boxes'), static fn(array $box) => !empty($box['shared']) || in_array((string) $pageId, array_map('strval', (array) ($box['sites'] ?? [])), true)));
    }

    /** The page with exactly this link, or null. */
    public function pageByLink(string $link): ?array
    {
        foreach ($this->all('/pages', ['search' => $link], 100) as $page) {
            if (($page['link'] ?? null) === $link) { return $page; }
        }
        return null;
    }

    /** A link that no page uses: the given one, or with -2, -3 and so on. */
    public function freeLink(string $link): string
    {
        $candidate = $link;
        for ($n = 2; null !== $this->pageByLink($candidate); $n++) {
            if ($n > 50) { throw new ToolError('Could not find a free address near ' . $link . '. Give a link yourself.'); }
            $candidate = Slug::withSuffix($link, $n);
        }
        return $candidate;
    }

    /** Whether the page exists, null when the call failed for another reason. */
    public function page(int $id): ?array
    {
        try {
            return (array) $this->api->request('GET', '/pages/' . $id)->data;
        } catch (ApiError $e) {
            if ('not_found' === $e->errorCode) { return null; }
            throw $e;
        }
    }
}
