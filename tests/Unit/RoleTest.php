<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\User\Role;

class RoleTest extends TestCase
{
    #[DataProvider('hierarchy')]
    public function testHierarchy(string $actual, string $required, bool $expected): void
    {
        $this->assertSame($expected, Role::atLeast($actual, $required));
    }

    public static function hierarchy(): array
    {
        return [
            'admin as admin'   => [Role::ADMIN, Role::ADMIN, true],
            'admin as editor'  => [Role::ADMIN, Role::EDITOR, true],
            'admin as user'    => [Role::ADMIN, Role::USER, true],
            'editor as admin'  => [Role::EDITOR, Role::ADMIN, false],
            'editor as editor' => [Role::EDITOR, Role::EDITOR, true],
            'user as editor'   => [Role::USER, Role::EDITOR, false],
            'user as user'     => [Role::USER, Role::USER, true],
        ];
    }

    #[DataProvider('invalid')]
    public function testUnknownRolesAreNeverEnough($actual): void
    {
        $this->assertFalse(Role::atLeast($actual, Role::USER));
        $this->assertFalse(Role::isValid($actual));
    }

    public static function invalid(): array
    {
        return [['root'], [''], [null], [1], [true], [['admin']], ['Admin'], [' admin']];
    }

    public function testAllListsEveryRoleOnce(): void
    {
        $this->assertSame(['user', 'editor', 'admin'], Role::all());
    }
}
