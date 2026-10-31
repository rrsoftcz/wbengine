<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Schema;

final class SchemaTest extends TestCase
{
    public function testValidAndInvalidValues(): void
    {
        $schema = ['type' => 'object', 'required' => ['id'], 'additionalProperties' => false, 'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'mode' => ['type' => 'string', 'enum' => ['a', 'b']],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string', 'minLength' => 1], 'maxItems' => 2],
            'link' => ['type' => 'string', 'pattern' => '^/[a-z0-9/-]*$'],
            'translations' => ['type' => 'object', 'properties' => ['cs' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]]]],
        ]];
        $this->assertNull(Schema::validate(['id' => 3, 'mode' => 'a', 'tags' => ['x'], 'link' => '/blog/', 'translations' => ['cs' => ['title' => 'T']]], $schema));
        $this->assertStringContainsString('required field "id"', Schema::validate([], $schema));
        $this->assertStringContainsString('arguments.id must be integer', Schema::validate(['id' => '3'], $schema));
        $this->assertStringContainsString('at least 1', Schema::validate(['id' => 0], $schema));
        $this->assertStringContainsString('one of: "a", "b"', Schema::validate(['id' => 1, 'mode' => 'c'], $schema));
        $this->assertStringContainsString('arguments.tags[1]', Schema::validate(['id' => 1, 'tags' => ['x', '']], $schema));
        $this->assertStringContainsString('at most 2 items', Schema::validate(['id' => 1, 'tags' => ['a', 'b', 'c']], $schema));
        $this->assertStringContainsString('invalid format', Schema::validate(['id' => 1, 'link' => 'blog'], $schema));
        $this->assertStringContainsString('arguments.translations.cs.title must be string', Schema::validate(['id' => 1, 'translations' => ['cs' => ['title' => 5]]], $schema));
        $this->assertStringContainsString('unknown field "x"', Schema::validate(['id' => 1, 'x' => 1], $schema));
    }

    public function testBooleansAreNotIntegersAndEmptyArraysAreBothObjectsAndLists(): void
    {
        $this->assertNotNull(Schema::validate(true, ['type' => 'integer']));
        $this->assertNull(Schema::validate([], ['type' => 'object']));
        $this->assertNull(Schema::validate([], ['type' => 'array']));
        $this->assertNotNull(Schema::validate(['a' => 1], ['type' => 'array']));
    }
}
