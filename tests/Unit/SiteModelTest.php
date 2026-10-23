<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Site\SiteModel;

class SiteModelTest extends TestCase
{
    public function testNonStrictLinkExpandsToAllPrefixes(): void
    {
        $this->assertSame(
            array('/blog/', '/blog/2024/', '/blog/2024/post/'),
            (new SiteModel())->getUrlCandidates('/blog/2024/post/', false)
        );
    }

    public function testStrictLinkIsKept(): void
    {
        $this->assertSame(array('/blog/x/'), (new SiteModel())->getUrlCandidates('/blog/x/', true));
    }

    public function testQuotesStayDataNotSql(): void
    {
        $candidates = (new SiteModel())->getUrlCandidates("/a'/b/", false);

        $this->assertSame(array("/a'/", "/a'/b/"), $candidates);
    }
}
