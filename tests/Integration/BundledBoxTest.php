<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Box\Integrations;
use Wbengine\Section;
use Wbengine\Site;
use Wbengine\Section\Exception\SectionException;

/** A box shipped with the framework is resolved from the box row alone, without a class in the application. */
final class BundledBoxTest extends DbTestCase
{
    private function section(): Section
    {
        $site = new class extends Site {
            public function __construct() {}
            public function getSiteId() { return 1; }
            public function getParent() { return new class { public function getParentNameSpace() { return 'Wbengine\\Tests\\NoSuchApplication'; } }; }
        };
        return new Section(['section_id' => 1, 'key' => 'central'], $site);
    }

    private function resolve(string $module): object
    {
        $method = new \ReflectionMethod(Section::class, '_getModuleBox');
        return $method->invoke($this->section(), ['module' => $module, 'method' => 'integrations', 'id' => 1, 'section_id' => 1]);
    }

    public function testIntegrationsBoxNeedsNoApplicationClass(): void
    {
        $this->assertInstanceOf(Integrations::class, $this->resolve('integrations'));
        $this->assertInstanceOf(Integrations::class, $this->resolve('Integrations'));
    }

    public function testOnlyTheFixedListOfBundledBoxesIsResolved(): void
    {
        foreach (['article', 'auth', 'WbengineBoxAbstract', '..\\Integrations'] as $module) {
            try {
                $this->resolve($module);
                $this->fail('A framework class was resolved from the module name ' . $module);
            } catch (SectionException $e) {
                $this->assertStringContainsString('Class not found', $e->getMessage());
            }
        }
    }
}
