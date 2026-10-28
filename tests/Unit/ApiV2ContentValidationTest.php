<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\BoxRegistry;
use Wbengine\Api\V2\Resources;
use Wbengine\Api\V2\Problem;

class ApiV2ContentValidationTest extends TestCase
{
    public function testFiniteResourcesRejectTableNamesAndUnknownFields(): void
    {
        foreach ([fn () => Resources::definition('cms_users'), fn () => Resources::validate('pages', ['role' => 'admin']), fn () => Resources::validate('articles', ['views' => 3]), fn () => Resources::validate('boxes', ['module' => 'Auth', 'method' => 'delete'])] as $work) {
            try { $work(); self::fail('Unsafe field or resource accepted'); }
            catch (Problem $e) { self::assertSame(400, $e->getCode()); }
        }
    }

    public function testNewArticleRequiresMarkdownAndFormatSwitchReplacesBothFields(): void
    {
        self::assertSame('markdown', Resources::validate('articles', ['title' => 'New', 'introtext' => '', 'bodytext' => '# Body'], null, true)['content_format']);
        foreach ([['content_format' => 'legacy_html'], ['content_format' => 'markdown', 'bodytext' => 'new']] as $data) {
            try { Resources::validate('articles', $data, ['content_format' => 'legacy_html', 'introtext' => '<b>old</b>', 'bodytext' => 'old']); self::fail('Mixed format accepted'); }
            catch (Problem $e) { self::assertSame(400, $e->getCode()); }
        }
        self::assertSame('unchanged', Resources::validate('articles', ['title' => 'unchanged'], ['content_format' => 'legacy_html'])['title']);
    }

    public function testLegacyTextChangesCannotOmitTheirFormat(): void
    {
        $source = ['content_format' => 'legacy_html', 'introtext' => '<p>Trusted intro</p>', 'bodytext' => '<p>Trusted body</p>'];
        self::assertSame(['title' => 'Metadata'], Resources::validate('articles', ['title' => 'Metadata'], $source));
        self::assertSame(['bodytext' => '<p>Trusted body</p>'], Resources::validate('articles', ['bodytext' => '<p>Trusted body</p>'], $source));
        foreach ([['bodytext' => '<script>alert(1)</script>'], ['introtext' => 'New', 'bodytext' => 'New'], ['bodytext' => 'New', 'content_format' => 'markdown']] as $data) {
            try { Resources::validate('articles', $data, $source); self::fail('Changed legacy text accepted'); }
            catch (Problem $e) { self::assertSame('format_replacement_required', $e->errorCode); }
        }
        self::assertSame('markdown', Resources::validate('articles', ['introtext' => '', 'bodytext' => '**New**', 'content_format' => 'markdown'], $source)['content_format']);
    }

    public function testBoxCatalogOnlyAcceptsLocallyRegisteredPairsAndValidatedSettings(): void
    {
        $registry = new BoxRegistry();
        $registry->register('story', 'blog', 'story', ['variant' => ['compact', 'full']]);
        self::assertSame(['module' => 'blog', 'method' => 'story'], $registry->resolve('story', ['variant' => 'compact']));
        foreach ([['evil', []], ['story', ['variant' => '<?php']], ['story', ['class' => 'Auth']]] as [$type, $settings]) {
            try { $registry->resolve($type, $settings); self::fail('Unregistered box accepted'); }
            catch (Problem $e) { self::assertSame(400, $e->getCode()); }
        }
    }

    public function testArticleSourceIsOptionalFreeText(): void
    {
        $values = ['https://example.test/reference', '/references/book/', '#reference', 'Plain Name, The Book (2020)', 'javascript:alert(1)', 'data:text/html,<b>x</b>', "Zdroj: \u{010D}esky", str_repeat('a', 255), '', null];
        foreach ($values as $source) { self::assertSame($source, Resources::validate('articles', ['source' => $source])['source']); }
        self::assertArrayNotHasKey('source', Resources::validate('articles', ['title' => 'Without source']));
    }

    public function testArticleSourceRejectsControlCharactersAndLongText(): void
    {
        foreach (["line\nbreak", "tab\tsource", "bell\x07", "del\x7f"] as $source) {
            try { Resources::validate('articles', ['source' => $source]); self::fail('Control character accepted'); }
            catch (Problem $e) {
                self::assertSame('invalid_source', $e->errorCode);
                self::assertSame('Source must be plain text without control characters.', $e->getMessage());
            }
        }
        try { Resources::validate('articles', ['source' => str_repeat('a', 256)]); self::fail('Too long source accepted'); }
        catch (Problem $e) { self::assertSame(400, $e->getCode()); }
    }

    public function testStrictTypesPathAndUtf8Validation(): void
    {
        foreach ([['pages', ['link' => 'https://evil.example']], ['pages', ['link' => '/../../index.php']], ['menu', ['visible' => 'yes']], ['sections', ['active' => []]], ['articles', ['bodytext' => "\xff"]]] as [$resource, $data]) {
            try { Resources::validate($resource, $data); self::fail('Unsafe value accepted'); }
            catch (Problem $e) { self::assertSame(400, $e->getCode()); }
        }
    }
}
