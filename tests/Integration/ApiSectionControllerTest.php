<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Api\Section;
use Wbengine\Db;
use Wbengine\User\Role;

class ApiSectionControllerTest extends ApiTestCase
{
    private Section $sections;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sections = new Section($this->api);
    }

    private function actions(): array
    {
        return [
            'list'   => fn () => $this->sections->getSections(),
            'get'    => fn () => $this->sections->getSectionById(1),
            'add'    => fn () => $this->sections->addNewSection(['title' => 't', 'key' => 'k']),
            'update' => fn () => $this->sections->updateSection(1, ['title' => 'x']),
            'delete' => fn () => $this->sections->deleteSection(1),
        ];
    }

    #[DataProvider('actionNames')]
    public function testNoTokenIs401(string $action): void
    {
        $r = $this->respond($this->actions()[$action]);

        $this->assertSame(401, $r['status']);
        $this->assertFalse($r['json']['success']);
    }

    #[DataProvider('actionNames')]
    public function testMalformedAndWrongTokensAre401(string $action): void
    {
        foreach (['Bearer garbage', 'Bearer a.b.c', 'Basic dXNlcjpwYXNz', 'Bearer ' . str_repeat('x', 5000)] as $header) {
            $_SERVER['HTTP_AUTHORIZATION'] = $header;
            $this->assertSame(401, $this->respond($this->actions()[$action])['status'], $header);
        }
    }

    #[DataProvider('actionNames')]
    public function testRegularUsersAndEditorsAreForbidden(string $action): void
    {
        foreach ([Role::USER, Role::EDITOR] as $role) {
            $this->asUser($this->createUser($role . '@example.com', $role));
            $r = $this->respond($this->actions()[$action]);

            $this->assertSame(403, $r['status'], "$role must not use $action");
            $this->assertSame('Forbidden.', $r['json']['message']);
        }
    }

    public static function actionNames(): array
    {
        return [['list'], ['get'], ['add'], ['update'], ['delete']];
    }

    public function testExpiredTokenIs401(): void
    {
        $id = $this->createUser('a@example.com', Role::ADMIN);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->tokenFor($id, -60);

        $r = $this->respond(fn () => $this->sections->getSections());

        $this->assertSame(401, $r['status']);
        $this->assertSame('Expired token.', $r['json']['message']);
    }

    public function testAdminCanReadAndWrite(): void
    {
        $this->asUser($this->createUser('admin@example.com', Role::ADMIN));

        $created = $this->respond(fn () => $this->sections->addNewSection(['title' => "It's", 'key' => 'k1', 'active' => 1]));
        $this->assertSame(201, $created['status']);
        $id = $created['json']['section_id'];
        $this->assertSame("It's", $created['json']['title']);

        $list = $this->respond(fn () => $this->sections->getSections());
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, $list['json']);

        $this->assertSame(1, $this->respond(fn () => $this->sections->updateSection($id, ['title' => 'New']))['json']['updated']);
        $this->assertSame('New', $this->respond(fn () => $this->sections->getSectionById($id))['json']['title']);

        $this->assertSame(1, $this->respond(fn () => $this->sections->deleteSection($id))['json']['deleted']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_sections')['c']);
    }

    public function testAdminInputIsStillValidated(): void
    {
        $this->asUser($this->createUser('admin@example.com', Role::ADMIN));

        $this->expectException(ApiModelException::class);
        $this->respond(fn () => $this->sections->updateSection(1, ['evil` = 1 --' => 'x']));
    }

    public function testRoleIsReadFromTheDatabaseNotFromTheToken(): void
    {
        $id = $this->createUser('admin@example.com', Role::ADMIN);
        $this->createUser('other@example.com', Role::ADMIN);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->tokenFor($id);
        $this->assertSame(200, $this->respond(fn () => $this->sections->getSections())['status']);

        Db::update('cms_users', ['role' => Role::USER], ['user_id' => $id]);

        $this->assertSame(403, $this->respond(fn () => $this->sections->getSections())['status'], 'the same token loses its privileges at once');
    }

    public function testTokenOfADeletedUserIsForbidden(): void
    {
        $id = $this->createUser('gone@example.com', Role::ADMIN);
        $this->asUser($id);
        Db::query('DELETE FROM cms_users WHERE user_id = ?', [$id]);

        $this->assertSame(403, $this->respond(fn () => $this->sections->getSections())['status']);
    }

    public function testTokenForTheAnonymousUserIsForbidden(): void
    {
        Db::query("INSERT INTO cms_users (user_id, username, role) VALUES (0, 'ANONYMOUS', 'admin')");
        $this->asUser(0);

        $this->assertSame(403, $this->respond(fn () => $this->sections->getSections())['status']);
    }

    public function testHeaderNameCaseDoesNotMatter(): void
    {
        $this->createUser('keep@example.com', Role::ADMIN);
        $id = $this->createUser('admin@example.com', Role::ADMIN);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'bearer ' . $this->tokenFor($id);

        $this->assertSame(200, $this->respond(fn () => $this->sections->getSections())['status']);
    }

    public function testWritesFlushTheCache(): void
    {
        $this->asUser($this->createUser('admin@example.com', Role::ADMIN));
        $dir = sys_get_temp_dir() . '/wb-api-cache-' . bin2hex(random_bytes(4));
        \Wbengine\Cache::setDriver(new \Wbengine\Cache\FileCache($dir), 60);
        \Wbengine\Cache::store('sections:active', ['stale'], 60);

        $this->respond(fn () => $this->sections->addNewSection(['title' => 't', 'key' => 'k']));

        \Wbengine\Cache::fetch('sections:active', $found);
        $this->assertFalse($found, 'a section write drops cached data');

        \Wbengine\Cache::setDriver(null);
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
