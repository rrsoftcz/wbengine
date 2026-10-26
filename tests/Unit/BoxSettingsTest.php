<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Box\WbengineBoxAbstract;

final class BoxSettingsTest extends TestCase
{
    public function testSettingsAreDecodedAsPlainStringConfiguration(): void
    {
        $box = new class(['api_settings' => '{"variant":"compact","literal":"<?php wb_pwn();"}'], null) extends WbengineBoxAbstract {};
        self::assertTrue(method_exists($box, 'getSettings'), 'Boxes must expose decoded API settings.');
        self::assertSame(['variant' => 'compact', 'literal' => '<?php wb_pwn();'], $box->getSettings());
        self::assertSame('compact', $box->getSetting('variant', 'full'));
        self::assertSame('fallback', $box->getSetting('missing', 'fallback'));
        self::assertNull($box->getSetting('missing'));
        self::assertEmpty($GLOBALS['wb_pwned'] ?? false);
    }

    public function testAbsentMalformedAndUnboundedSettingsFailClosed(): void
    {
        foreach ([[], ['api_settings' => null], ['api_settings' => 'not json'], ['api_settings' => 'null'], ['api_settings' => '[]'], ['api_settings' => '{"variant":[]}'], ['api_settings' => '{"variant":1}'], ['api_settings' => '{"variant":"' . str_repeat('x', 129) . '"}'], ['api_settings' => str_repeat(' ', 65537)], ['api_settings' => ['variant' => 'compact']]] as $row) {
            $box = new class($row, null) extends WbengineBoxAbstract {};
            self::assertTrue(method_exists($box, 'getSettings'), 'Boxes must expose decoded API settings.');
            self::assertSame([], $box->getSettings());
            self::assertSame('full', $box->getSetting('variant', 'full'));
        }
    }
}
