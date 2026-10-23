<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Db;
use Wbengine\Db\Exception\DbException;

class DbIdentifierTest extends TestCase
{
    public function testValidIdentifierIsQuoted(): void
    {
        $this->assertSame('`cms_users`', Db::identifier('cms_users'));
        $this->assertSame('`Key2`', Db::identifier('Key2'));
    }

    #[DataProvider('invalid')]
    public function testInvalidIdentifierIsRejected($name): void
    {
        $this->expectException(DbException::class);
        Db::identifier($name);
    }

    public static function invalid(): array
    {
        return [
            'backtick'  => ['a` = 1 --'],
            'space'     => ['a b'],
            'comma'     => ['a,b'],
            'dot'       => ['db.table'],
            'empty'     => [''],
            'array'     => [['a']],
            'null'      => [null],
            'semicolon' => ['a;DROP TABLE x'],
        ];
    }
}
