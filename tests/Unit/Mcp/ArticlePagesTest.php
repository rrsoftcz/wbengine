<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Api\Client;
use Wbengine\Mcp\Tools\ArticlePages;
use Wbengine\Mcp\Tools\SiteFacts;

/** The checklist against a site that answers from a table: languages, a key that cannot read boxes, a missing page. */
final class ArticlePagesTest extends TestCase
{
    private function pages(array $routes): ArticlePages
    {
        $transport = static function (string $method, string $url) use ($routes): array {
            $path = substr(parse_url($url, PHP_URL_PATH), strlen('/api/v2'));
            foreach ($routes as $route => $answer) {
                if ($path === $route) {
                    [$status, $data] = $answer;
                    $body = 200 === $status ? ['success' => true, 'data' => $data, 'pagination' => ['page' => 1, 'per_page' => 100, 'total' => is_array($data) && array_is_list($data) ? count($data) : 1, 'total_pages' => 1]] : ['success' => false, 'error' => ['code' => $data, 'message' => $data]];
                    return ['status' => $status, 'headers' => ['etag' => '"e"'], 'body' => json_encode($body)];
                }
            }
            return ['status' => 404, 'headers' => [], 'body' => json_encode(['success' => false, 'error' => ['code' => 'not_found', 'message' => 'x']])];
        };
        $api = new Client('https://site.example', 'wbv2_k', 'mcp:test', $transport);
        return new ArticlePages($api, new SiteFacts($api));
    }

    private function codes(array $findings): array
    {
        return array_column($findings, 'level', 'code');
    }

    private function site(array $extra = []): array
    {
        return $extra + [
            '/capabilities' => [200, ['i18n' => ['default' => 'cs', 'locales' => ['cs', 'en', 'de']], 'box_types' => []]],
            '/articles/5' => [200, ['id' => 5, 'site_id' => 9, 'bodytext' => 'text', 'introtext' => '', 'translations' => ['en' => ['title' => 'x']], 'draft' => null]],
            '/pages/9' => [200, ['site_id' => 9, 'link' => '/clanek/', 'title' => 'Clanek', 'html_title' => 'Titulek', 'html_description' => 'Popis', 'visible' => 1]],
            '/boxes' => [200, [['id' => 1, 'sites' => ['9'], 'shared' => 0, 'renders_articles' => true, 'section_id' => 1]]],
            '/sections' => [200, [['section_id' => 1, 'key' => 'central', 'active' => 1]]],
            '/menu' => [200, [['menu_id' => 1, 'site_id' => 9]]],
        ];
    }

    public function testEveryMissingSiteLanguageIsReported(): void
    {
        $codes = $this->codes($this->pages($this->site())->check(5));
        $this->assertSame(['translation_missing' => 'warning'], $codes, 'cs is the main language, en is there, de is missing');
        $finding = array_values(array_filter($this->pages($this->site())->check(5), static fn($f) => 'translation_missing' === $f['code']))[0];
        $this->assertStringContainsString('de', $finding['text']);
        $this->assertStringNotContainsString('en,', $finding['text']);
    }

    public function testAKeyThatCannotReadBoxesOrMenuIsStillChecked(): void
    {
        $site = $this->site(['/boxes' => [403, 'forbidden'], '/menu' => [403, 'forbidden']]);
        $codes = $this->codes($this->pages($site)->check(5));
        $this->assertSame('advice', $codes['boxes_unchecked']);
        $this->assertArrayNotHasKey('no_box', $codes);
        $this->assertArrayNotHasKey('no_menu', $codes);
        $unreadablePage = $this->codes($this->pages($this->site(['/pages/9' => [403, 'forbidden']]))->check(5));
        $this->assertSame(['page_unchecked' => 'advice'], $unreadablePage);
    }

    public function testAPageThatIsGoneAndAnEmptyArticleAreBlockers(): void
    {
        $gone = $this->site(['/pages/9' => [404, 'not_found']]);
        $this->assertSame('blocker', $this->codes($this->pages($gone)->check(5))['no_page']);
        $empty = $this->site(['/articles/5' => [200, ['id' => 5, 'site_id' => 9, 'bodytext' => ' ', 'introtext' => '', 'translations' => [], 'draft' => null]]]);
        $this->assertSame('blocker', $this->codes($this->pages($empty)->check(5))['empty_text']);
    }

    public function testBoxesThatDoNotShowArticlesOrSitInAnInactiveSectionAreWarnings(): void
    {
        $unknown = $this->site(['/boxes' => [200, [['id' => 1, 'sites' => ['9'], 'shared' => 0, 'renders_articles' => false, 'section_id' => 1]]]]);
        $this->assertSame('warning', $this->codes($this->pages($unknown)->check(5))['box_unknown']);
        $inactive = $this->site(['/sections' => [200, [['section_id' => 1, 'key' => 'central', 'active' => 0]]]]);
        $this->assertSame('warning', $this->codes($this->pages($inactive)->check(5))['section_inactive']);
        $shared = $this->site(['/boxes' => [200, [['id' => 2, 'sites' => [], 'shared' => 1, 'renders_articles' => true, 'section_id' => 1]]]]);
        $this->assertArrayNotHasKey('no_box', $this->codes($this->pages($shared)->check(5)));
    }

    public function testADraftIsCheckedInsteadOfTheLiveText(): void
    {
        $draft = $this->site(['/articles/5' => [200, ['id' => 5, 'site_id' => 0, 'bodytext' => 'old', 'introtext' => '', 'translations' => [], 'draft' => ['content' => ['site_id' => 9, 'bodytext' => 'new', 'introtext' => ''], 'translations' => ['de' => ['title' => 'x'], 'en' => ['title' => 'y']]]]]]);
        $this->assertSame([], $this->codes($this->pages($draft)->check(5)), 'the page and the translations come from the draft');
    }

    public function testAPageAndAMenuEntryWithoutTheTranslationAreWarnedAbout(): void
    {
        $site = $this->site([
            '/articles/5' => [200, ['id' => 5, 'site_id' => 9, 'bodytext' => 'text', 'introtext' => '', 'translations' => ['en' => ['title' => 'x'], 'de' => ['title' => 'y']], 'draft' => null]],
            '/pages/9' => [200, ['site_id' => 9, 'link' => '/clanek/', 'title' => 'Clanek', 'html_title' => 'Titulek', 'html_description' => 'Popis', 'visible' => 1, 'translation_locales' => ['en']]],
            '/menu' => [200, [['menu_id' => 1, 'site_id' => 9, 'name' => 'Clanek', 'translation_locales' => []]]],
        ]);
        $findings = $this->pages($site)->check(5);
        $codes = $this->codes($findings);
        $this->assertSame('warning', $codes['page_translation_missing']);
        $this->assertSame('warning', $codes['menu_translation_missing']);
        $texts = array_column($findings, 'text', 'code');
        $this->assertStringContainsString('for: de', $texts['page_translation_missing'], 'cs is the main language, en is translated, de is missing');
        $this->assertStringContainsString('update_page', $texts['page_translation_missing']);
        $this->assertStringContainsString('set_menu_entry', $texts['menu_translation_missing']);
    }

    public function testTranslatedPageAndMenuGiveNoWarningAndUnknownTranslationsAreNotGuessed(): void
    {
        $done = $this->site([
            '/articles/5' => [200, ['id' => 5, 'site_id' => 9, 'bodytext' => 'text', 'introtext' => '', 'translations' => ['en' => [], 'de' => []], 'draft' => null]],
            '/pages/9' => [200, ['site_id' => 9, 'link' => '/clanek/', 'title' => 'Clanek', 'html_title' => 'Titulek', 'html_description' => 'Popis', 'visible' => 1, 'translation_locales' => ['de', 'en']]],
            '/menu' => [200, [['menu_id' => 1, 'site_id' => 9, 'name' => 'Clanek', 'translation_locales' => ['de', 'en']]]],
        ]);
        $this->assertSame([], $this->codes($this->pages($done)->check(5)));
        $this->assertSame([], $this->codes($this->pages($this->site(['/articles/5' => [200, ['id' => 5, 'site_id' => 9, 'bodytext' => 't', 'introtext' => '', 'translations' => ['en' => [], 'de' => []], 'draft' => null]]]))->check(5)), 'a site that does not report the translations is not warned about');
    }
}
