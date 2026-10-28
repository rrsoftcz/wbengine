<?php

namespace Wbengine\Api\V2;

use Wbengine\Console\Doctor;
use Wbengine\Console\MigrationStatus;

/**
 * Read-only checks before the local screen may issue an integration key. It never runs DDL:
 * a failed check yields a code, the screen turns it into an instruction for the operator.
 */
final class Preflight
{
    public const API_DISABLED = 'api_disabled';
    public const NOT_HTTPS = 'not_https';
    public const SCHEMA_INTEGRATIONS = 'schema_integrations';
    public const SCHEMA_LAST_USED = 'schema_last_used';
    public const DATABASE = 'database';
    /** Optional analytics permission: why it cannot be offered. These never block issuing a key. */
    public const ANALYTICS_DISABLED = 'analytics_disabled';
    public const ANALYTICS_SCHEMA = 'analytics_schema';
    public const ANALYTICS_DATABASE = 'analytics_database';

    /** @var callable(array): bool */
    private $inspector;

    /** @param callable(array): bool|null $inspector returns whether the schema requirement is met */
    public function __construct(?callable $inspector = null)
    {
        $this->inspector = $inspector ?? static fn (array $schema): bool => (new MigrationStatus())->inspect($schema);
    }

    /**
     * @param string $baseUrl configured public origin, empty when the address comes from the request
     * @return string[] problem codes, empty when a key may be issued
     */
    public function problems(bool $apiEnabled, bool $secureRequest, string $baseUrl = ''): array
    {
        $problems = [];
        if (!$apiEnabled) {
            $problems[] = self::API_DISABLED;
        }
        if (!$secureRequest || ('' !== $baseUrl && !str_starts_with(strtolower($baseUrl), 'https://'))) {
            $problems[] = self::NOT_HTTPS;
        }
        try {
            if (!($this->inspector)(Doctor::SCHEMA_INTEGRATIONS)) {
                $problems[] = self::SCHEMA_INTEGRATIONS;
            } elseif (!($this->inspector)(Doctor::SCHEMA_INTEGRATIONS_LAST_USED)) {
                $problems[] = self::SCHEMA_LAST_USED;
            }
        } catch (\Throwable $e) {
            $problems[] = self::DATABASE;
        }
        return $problems;
    }

    /**
     * Whether the optional analytics permission may be offered: analytics must be enabled in the
     * configuration and the schema of migrations/4.0.0/006-analytics.sql must exist.
     *
     * @return string[] codes, empty when analytics is usable on this website
     */
    public function analyticsProblems(bool $analyticsEnabled): array
    {
        $problems = [];
        if (!$analyticsEnabled) {
            $problems[] = self::ANALYTICS_DISABLED;
        }
        try {
            if (!($this->inspector)(Doctor::SCHEMA_ANALYTICS)) {
                $problems[] = self::ANALYTICS_SCHEMA;
            }
        } catch (\Throwable $e) {
            $problems[] = self::ANALYTICS_DATABASE;
        }
        return $problems;
    }
}
