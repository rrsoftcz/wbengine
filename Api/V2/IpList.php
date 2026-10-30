<?php

namespace Wbengine\Api\V2;

/**
 * The addresses an integration key may be used from: single IPv4 or IPv6 addresses and CIDR ranges.
 * An empty list means any address. Entries are validated and normalized before they are stored.
 */
final class IpList
{
    public const MAX_ENTRIES = 20;

    /**
     * Parse user input (comma, space or newline separated text, or a list) into normalized entries.
     * Null when any entry is not an IP address or a CIDR range, or there are too many.
     *
     * @param string|array<int, mixed> $input
     * @return string[]|null
     */
    public static function parse(string|array $input): ?array
    {
        $items = is_array($input) ? $input : preg_split('/[\s,;]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        $entries = [];
        foreach ($items ?: [] as $item) {
            if (!is_string($item)) { return null; }
            $entry = self::normalize(trim($item));
            if (null === $entry) { return null; }
            $entries[$entry] = $entry;
        }
        return count($entries) > self::MAX_ENTRIES ? null : array_values($entries);
    }

    /** One address or range in its canonical text form, or null. */
    public static function normalize(string $value): ?string
    {
        $parts = explode('/', $value);
        if (count($parts) > 2) { return null; }
        $address = self::address($parts[0]);
        if (null === $address) { return null; }
        if (1 === count($parts)) { return $address; }
        $bits = strlen(inet_pton($address)) * 8;
        if (!ctype_digit($parts[1]) || (int) $parts[1] > $bits) { return null; }
        return (int) $parts[1] === $bits ? $address : $address . '/' . (int) $parts[1];
    }

    /**
     * Whether $ip matches any entry of the list. An empty list allows everything, an unknown
     * client address matches nothing.
     *
     * @param string[] $list
     */
    public static function allows(array $list, ?string $ip): bool
    {
        if ([] === $list) { return true; }
        $client = null === $ip ? null : self::address($ip);
        if (null === $client) { return false; }
        $packed = inet_pton($client);
        foreach ($list as $entry) {
            $parts = explode('/', $entry);
            $network = self::address($parts[0]);
            if (null === $network) { continue; }
            $networkPacked = inet_pton($network);
            if (strlen($networkPacked) !== strlen($packed)) { continue; }
            $bits = isset($parts[1]) ? (int) $parts[1] : strlen($networkPacked) * 8;
            $bytes = intdiv($bits, 8);
            $remaining = $bits % 8;
            if (substr($packed, 0, $bytes) === substr($networkPacked, 0, $bytes)
                && (0 === $remaining || ((ord($packed[$bytes]) ^ ord($networkPacked[$bytes])) & (0xff << (8 - $remaining)) & 0xff) === 0)) {
                return true;
            }
        }
        return false;
    }

    /** Canonical text of an address; an IPv4-mapped IPv6 address is reduced to IPv4. */
    public static function address(string $value): ?string
    {
        if (false === filter_var($value, FILTER_VALIDATE_IP)) { return null; }
        $packed = inet_pton($value);
        if (16 === strlen($packed) && str_starts_with($packed, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
            $packed = substr($packed, 12);
        }
        return inet_ntop($packed);
    }
}
