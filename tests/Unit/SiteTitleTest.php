<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Site;
use Wbengine\Vars;

class SiteTitleTest extends TestCase
{
    private function site(?Vars $vars): Site
    {
        $site = (new \ReflectionClass(Site::class))->newInstanceWithoutConstructor();
        if (null !== $vars) {
            $app = new class($vars) {
                public function __construct(private Vars $vars) {}
                public function getClassVars() { return $this->vars; }
            };
            $site->_parent = $app;
        }
        return $site;
    }

    public function testATitleSetAfterInitializationReachesTheTemplateVariables(): void
    {
        $vars = (new \ReflectionClass(Vars::class))->newInstanceWithoutConstructor();
        $vars->addValue('title', '', 'meta');
        $site = $this->site($vars);

        $site->setHtmlTitle('HTTP/1.1 404 Not Found');

        $this->assertSame('HTTP/1.1 404 Not Found', $vars->getValue('meta')['title']);
        $this->assertSame('HTTP/1.1 404 Not Found', $site->getHtmlTitle());
    }

    public function testATitleSetBeforeTheSiteHasAnApplicationOnlyStoresIt(): void
    {
        $site = $this->site(null);

        $site->setHtmlTitle('Early');

        $this->assertSame('Early', $site->getHtmlTitle());
    }
}
