<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Api\Model\ApiSectionModel;
use Wbengine\Api\Model\ApiUserModel;
use Wbengine\Api\Model\Exception\ApiModelException;

/**
 * Input validation runs before any query, so no database is needed here.
 */
class ApiModelValidationTest extends TestCase
{
    #[DataProvider('badIds')]
    public function testSectionRejectsNonNumericIds($id): void
    {
        $this->expectException(ApiModelException::class);
        (new ApiSectionModel())->deleteSection($id);
    }

    #[DataProvider('badIds')]
    public function testUserRejectsNonNumericIds($id): void
    {
        $this->expectException(ApiModelException::class);
        (new ApiUserModel())->getUserById($id);
    }

    public static function badIds(): array
    {
        return [
            'quote'    => ["1'"],
            'or'       => ['1 OR 1=1'],
            'negative' => ['-1'],
            'float'    => ['1.5'],
            'hex'      => ['0x1A'],
            'array'    => [[1]],
            'empty'    => [''],
        ];
    }

    public function testSectionRejectsUnknownColumns(): void
    {
        $this->expectException(ApiModelException::class);
        $this->expectExceptionMessage('Unknown section field');
        (new ApiSectionModel())->updateSection(1, array('title' => 'x', 'evil` = 1 --' => 'y'));
    }

    public function testSectionRejectsEmptyData(): void
    {
        $this->expectException(ApiModelException::class);
        (new ApiSectionModel())->updateSection(1, array());
    }

    public function testUserCannotSetPrivilegedColumns(): void
    {
        $this->expectException(ApiModelException::class);
        $this->expectExceptionMessage('Unknown user field');
        (new ApiUserModel())->updateUser(1, array('user_type' => 0));
    }

    #[DataProvider('badRoles')]
    public function testUserRejectsUnknownRoles($role): void
    {
        $this->expectException(ApiModelException::class);
        $this->expectExceptionMessage('The role must be one of');
        (new ApiUserModel())->updateUser(1, array('role' => $role));
    }

    public static function badRoles(): array
    {
        return [
            'superuser' => ['superuser'],
            'uppercase' => ['ADMIN'],
            'sql'       => ["admin' OR '1'='1"],
            'int'       => [1],
            'empty'     => [''],
            'null'      => [null],
        ];
    }

    public function testUserRejectsInvalidEmail(): void
    {
        $this->expectException(ApiModelException::class);
        (new ApiUserModel())->updateUser(1, array('email' => "a'b"));
    }

    public function testNonScalarValuesAreRejected(): void
    {
        $this->expectException(ApiModelException::class);
        (new ApiUserModel())->updateUser(1, array('firstname' => array('x')));
    }
}
