<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Db;
use Wbengine\Section;
use Wbengine\Section\Model;
use Wbengine\Site;

/**
 * Which boxes a section shows on a site. The query uses JSON_SEARCH on the "sites"
 * column, which MySQL and MariaDB implement differently inside, so it is tested on both.
 */
class BoxLookupTest extends DbTestCase
{
    private function box(string $name, int $section, string $sites, int $shared = 0, int $order = 0): void
    {
        Db::insert('cms_boxes', ['name' => $name, 'module' => $name, 'method' => $name, 'section_id' => $section, 'sites' => $sites, 'shared' => $shared, 'order' => $order]);
    }

    private function names(int $siteId, int $sectionId): array
    {
        $site = $this->createStub(Site::class);
        $site->method('getSiteId')->willReturn($siteId);
        $section = $this->createStub(Section::class);
        $section->method('getSite')->willReturn($site);
        $section->method('getSectionId')->willReturn($sectionId);

        return array_column((new Model())->getBoxes($section), 'name');
    }

    public function testBoxesOfTheSiteAndSectionOnly(): void
    {
        $this->box('here', 1, '["2"]');
        $this->box('other-site', 1, '["3"]');
        $this->box('other-section', 4, '["2"]');

        $this->assertSame(['here'], $this->names(2, 1));
    }

    public function testASiteListIsMatchedExactly(): void
    {
        $this->box('multi', 1, '["1","22","3"]');

        $this->assertSame(['multi'], $this->names(22, 1));
        $this->assertSame([], $this->names(2, 1), '2 is not a member of ["1","22","3"]');
        $this->assertSame([], $this->names(222, 1));
    }

    public function testSharedBoxesAppearOnEverySiteOfTheirSection(): void
    {
        $this->box('banner', 1, '["9"]', 1);
        $this->box('shared-elsewhere', 4, '["9"]', 1);

        $this->assertSame(['banner'], $this->names(2, 1));
        $this->assertSame(['shared-elsewhere'], $this->names(2, 4));
    }

    public function testBoxesAreOrdered(): void
    {
        $this->box('third', 1, '["2"]', 0, 30);
        $this->box('first', 1, '["2"]', 0, 10);
        $this->box('second', 1, '["2"]', 0, 20);

        $this->assertSame(['first', 'second', 'third'], $this->names(2, 1));
    }

    public function testRowsCarryTheSiteId(): void
    {
        $this->box('here', 1, '["2"]');
        $site = $this->createStub(Site::class);
        $site->method('getSiteId')->willReturn(2);
        $section = $this->createStub(Section::class);
        $section->method('getSite')->willReturn($site);
        $section->method('getSectionId')->willReturn(1);

        $rows = (new Model())->getBoxes($section);

        $this->assertSame('2', (string) $rows[0]['site_id']);
    }

    public function testHostileSiteIdsCannotBreakTheQuery(): void
    {
        $this->box('here', 1, '["2"]');

        $this->assertSame([], $this->names(0, 1));
        $this->assertSame([], $this->names(-5, 1));
    }
}
