<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\PageCache;
use Wbengine\Section\Model;

final class BoxDeviceTest extends TestCase
{
    public function testStoredDefaultShowsABoxOnEveryDevice(): void
    {
        foreach ([1, 2, 3] as $device) {
            self::assertTrue(Model::visibleOnDevice(['device_min' => 1, 'device_strict' => 0], $device));
            self::assertTrue(Model::visibleOnDevice(['device_min' => '0', 'device_strict' => '1'], $device));
            self::assertTrue(Model::visibleOnDevice([], $device));
        }
    }

    public function testMinimumDeviceHidesSmallerDevices(): void
    {
        $box = ['device_min' => 2, 'device_strict' => 0];
        self::assertFalse(Model::visibleOnDevice($box, 1));
        self::assertTrue(Model::visibleOnDevice($box, 2));
        self::assertTrue(Model::visibleOnDevice($box, 3));
    }

    public function testStrictBoxIsShownOnlyOnItsOwnDevice(): void
    {
        $box = ['device_min' => '2', 'device_strict' => '1'];
        self::assertFalse(Model::visibleOnDevice($box, 1));
        self::assertTrue(Model::visibleOnDevice($box, 2));
        self::assertFalse(Model::visibleOnDevice($box, 3));
    }

    public function testPageCacheKeyVariesByDevice(): void
    {
        \Wbengine\I18n::configure(null, null);
        self::assertSame('page:/blog/', PageCache::cacheKey('/blog/'));
        self::assertSame('page:d1:/blog/', PageCache::cacheKey('/blog/', 1));
        self::assertNotSame(PageCache::cacheKey('/blog/', 1), PageCache::cacheKey('/blog/', 3));
    }
}
