<?php

namespace Wbengine\Application\Env;

use Wbengine\Application\Application;
use Wbengine\Config;

/** Connection metadata shared by the application tree and legacy HTTP entry points. */
class RequestContext
{
    private ?Application $_parent;

    // Only compiled deployment policy is cached, never request metadata.
    private ?array $_settings = null;
    private array $_ranges = [];

    public function __construct(?Application $parent = null)
    {
        $this->_parent = $parent;
    }

    public function getParent(): ?Application
    {
        return $this->_parent;
    }

    public function getClientIp(): string
    {
        $settings = $this->_policy();
        $peer = $this->_address($_SERVER['REMOTE_ADDR'] ?? null);
        if (null === $peer) {
            return '';
        }
        if (!in_array('x-forwarded-for', $settings['forwardedHeaders'], true) || !$this->_trusted($peer)) {
            return $peer;
        }
        $header = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (!is_string($header) || strlen($header) > 8192) {
            return $peer;
        }
        $chain = explode(',', $header);
        if (count($chain) > 64) {
            return $peer;
        }
        $address = $peer;
        for ($i = count($chain) - 1; $i >= 0; --$i) {
            if (!$this->_trusted($address)) {
                break;
            }
            $address = $this->_address(trim($chain[$i]));
            if (null === $address) {
                return $peer;
            }
        }
        return $address;
    }

    public function isSecure(?array $server = null): bool
    {
        $server = $server ?? $_SERVER;
        $settings = $this->_policy();
        $peer = $this->_address($server['REMOTE_ADDR'] ?? null);
        if (null !== $peer && in_array('x-forwarded-proto', $settings['forwardedHeaders'], true) && $this->_trusted($peer)) {
            $proto = $server['HTTP_X_FORWARDED_PROTO'] ?? null;
            // The trusted proxy must overwrite this header with one original scheme.
            if (is_string($proto)) {
                $proto = strtolower(trim($proto));
                if ('http' === $proto || 'https' === $proto) {
                    return 'https' === $proto;
                }
            }
        }
        $https = $server['HTTPS'] ?? null;
        return is_string($https) && in_array(strtolower($https), ['on', '1'], true);
    }

    private function _policy(): array
    {
        $settings = Config::getHttpSettings();
        if ($settings === $this->_settings) {
            return $settings;
        }
        $ranges = [];
        foreach ($settings['trustedProxies'] as $range) {
            if (!is_string($range)) {
                throw new \InvalidArgumentException('Trusted proxies must contain IP addresses or CIDR ranges.');
            }
            $parts = explode('/', $range);
            $address = $this->_address($parts[0]);
            $packed = null === $address ? false : inet_pton($address);
            $bits = false === $packed ? 0 : strlen($packed) * 8;
            $prefix = $parts[1] ?? (string) $bits;
            if (count($parts) > 2 || false === $packed || !ctype_digit($prefix) || (int) $prefix > $bits) {
                throw new \InvalidArgumentException('Invalid trusted proxy IP address or CIDR range.');
            }
            $ranges[] = [$packed, (int) $prefix];
        }
        $this->_ranges = $ranges;
        $this->_settings = $settings;
        return $settings;
    }

    private function _trusted(string $address): bool
    {
        $packed = inet_pton($address);
        foreach ($this->_ranges as [$network, $bits]) {
            if (strlen($packed) !== strlen($network)) {
                continue;
            }
            $bytes = intdiv($bits, 8);
            $remaining = $bits % 8;
            if (substr($packed, 0, $bytes) === substr($network, 0, $bytes)
                && (0 === $remaining || ((ord($packed[$bytes]) ^ ord($network[$bytes])) & (0xff << (8 - $remaining))) === 0)) {
                return true;
            }
        }
        return false;
    }

    private function _address($value): ?string
    {
        if (!is_string($value) || false === filter_var($value, FILTER_VALIDATE_IP)) {
            return null;
        }
        return inet_ntop(inet_pton($value));
    }
}
