<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\IpList;

final class IpListTest extends TestCase
{
    public function testParseNormalizesDeduplicatesAndAcceptsAnySeparator(): void
    {
        $this->assertSame(['203.0.113.5', '198.51.100.0/24', '2001:db8::1'], IpList::parse("203.0.113.5, 198.51.100.0/24\n2001:0db8:0:0:0:0:0:1 203.0.113.5"));
        $this->assertSame([], IpList::parse('   '));
        $this->assertSame(['10.0.0.1'], IpList::parse(['10.0.0.1', '10.0.0.1/32']));
        $this->assertSame(['::1'], IpList::parse('::1/128'));
    }

    #[DataProvider('invalid')]
    public function testParseRejectsAnythingThatIsNotAnAddressOrRange(string $input): void
    {
        $this->assertNull(IpList::parse($input));
    }

    public static function invalid(): array
    {
        return ['name' => ['example.com'], 'octet' => ['256.1.1.1'], 'prefix' => ['10.0.0.0/33'], 'prefix v6' => ['::1/129'], 'text prefix' => ['10.0.0.0/x'], 'two slashes' => ['10.0.0.0/8/8'], 'wildcard' => ['10.0.*'], 'sql' => ["1.2.3.4'; DROP"]];
    }

    public function testTooManyEntriesAreRefused(): void
    {
        $list = implode(',', array_map(fn ($i) => '10.0.0.' . $i, range(1, IpList::MAX_ENTRIES + 1)));
        $this->assertNull(IpList::parse($list));
        $this->assertCount(IpList::MAX_ENTRIES, IpList::parse(implode(',', array_map(fn ($i) => '10.0.0.' . $i, range(1, IpList::MAX_ENTRIES)))));
    }

    #[DataProvider('addresses')]
    public function testAllows(array $list, ?string $ip, bool $expected): void
    {
        $this->assertSame($expected, IpList::allows($list, $ip));
    }

    public static function addresses(): array
    {
        return [
            'empty list allows anything' => [[], '203.0.113.5', true],
            'empty list allows an unknown address too' => [[], null, true],
            'unknown address matches nothing' => [['203.0.113.5'], null, false],
            'garbage address matches nothing' => [['203.0.113.5'], 'not-an-ip', false],
            'exact' => [['203.0.113.5'], '203.0.113.5', true],
            'other' => [['203.0.113.5'], '203.0.113.6', false],
            'range inside' => [['198.51.100.0/24'], '198.51.100.200', true],
            'range outside' => [['198.51.100.0/24'], '198.51.101.1', false],
            'odd prefix inside' => [['10.0.0.0/20'], '10.0.15.255', true],
            'odd prefix outside' => [['10.0.0.0/20'], '10.0.16.0', false],
            'v6 exact' => [['2001:db8::1'], '2001:0db8:0:0:0:0:0:1', true],
            'v6 range' => [['2001:db8::/32'], '2001:db8:ffff::5', true],
            'v6 outside' => [['2001:db8::/32'], '2001:db9::1', false],
            'families never mix' => [['10.0.0.0/8'], '::1', false],
            'mapped client address' => [['203.0.113.5'], '::ffff:203.0.113.5', true],
            'one of several' => [['192.0.2.1', '198.51.100.0/24'], '198.51.100.9', true],
        ];
    }
}
