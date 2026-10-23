<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api\Translation;
use Wbengine\Db;
use Wbengine\I18n;
use Wbengine\I18n\Translator;
use Wbengine\User\Role;

class ApiTranslationTest extends ApiTestCase
{
    private int $site;

    protected function setUp(): void
    {
        parent::setUp();
        I18n::configure(
            (new Translator([], 'en'))->setLocale('en'),
            ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs'], 'urlPrefix' => true, 'prefixDefault' => false]
        );
        $this->site = Db::insert('cms_sites', ['link' => '/blog/', 'title' => 'Blog', 'visible' => 1]);
    }

    protected function tearDown(): void
    {
        I18n::configure(null, null);
        parent::tearDown();
    }

    private function controller(): Translation
    {
        return new Translation($this->api);
    }

    private function login(string $role): void
    {
        $this->asUser($this->createUser($role . '@example.com', $role));
    }

    public function testItNeedsAnEditor(): void
    {
        $c = $this->controller();
        $this->assertSame(401, $this->respond(fn () => $c->getTranslations('site', $this->site))['status']);

        $this->login(Role::USER);
        $this->assertSame(403, $this->respond(fn () => $c->getTranslations('site', $this->site))['status']);
        $this->assertSame(403, $this->respond(fn () => $c->putTranslation('site', $this->site, 'cs', ['title' => 'x']))['status']);
        $this->assertSame(403, $this->respond(fn () => $c->deleteTranslation('site', $this->site, 'cs'))['status']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_site_translations')['n']);
    }

    public function testAnEditorStoresReadsAndDeletes(): void
    {
        $this->login(Role::EDITOR);
        $c = $this->controller();

        $put = $this->respond(fn () => $c->putTranslation('site', $this->site, 'cs', ['title' => 'Blog česky']));
        $this->assertSame(200, $put['status']);
        $this->assertSame('Blog česky', $put['json']['translation']['title']);

        $get = $this->respond(fn () => $c->getTranslations('site', $this->site));
        $this->assertSame(200, $get['status']);
        $this->assertSame('en', $get['json']['default']);
        $this->assertSame('Blog česky', $get['json']['translations']['cs']['title']);

        $del = $this->respond(fn () => $c->deleteTranslation('site', $this->site, 'cs'));
        $this->assertTrue($del['json']['deleted']);
        $again = $this->respond(fn () => $c->getTranslations('site', $this->site));
        $this->assertSame([], $again['json']['translations'], 'an empty object, not a list');
        $this->assertStringContainsString('"translations": {}', $again['body']);
    }

    public function testBadInputIsA400AndAMissingItemA404(): void
    {
        $this->login(Role::EDITOR);
        $c = $this->controller();

        $this->assertSame(400, $this->respond(fn () => $c->putTranslation('site', $this->site, 'en', ['title' => 'x']))['status'], 'default language');
        $this->assertSame(400, $this->respond(fn () => $c->putTranslation('site', $this->site, 'de', ['title' => 'x']))['status'], 'unsupported');
        $this->assertSame(400, $this->respond(fn () => $c->putTranslation('site', $this->site, 'cs', ['bogus' => 'x']))['status'], 'unknown field');
        $this->assertSame(400, $this->respond(fn () => $c->putTranslation('site', $this->site, 'cs', null))['status'], 'no JSON body');
        $this->assertSame(404, $this->respond(fn () => $c->putTranslation('site', 99999, 'cs', ['title' => 'x']))['status'], 'no such site');
        $this->assertSame(404, $this->respond(fn () => $c->getTranslations('orders', 1))['status'], 'unknown entity');
        $this->assertFalse($this->respond(fn () => $c->deleteTranslation('site', 99999, 'cs'))['json']['success']);
    }
}
