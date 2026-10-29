<?php
namespace Wbengine\Audit;

use Wbengine\Api\V2\Problem;
use Wbengine\Db;

/**
 * One place that writes cms_api_changes, whatever the write path: API v2, API v1 or the command line.
 *
 * Actor array keys (all optional): integration_id (v2), central_actor (v2 label, or "cli:<os user>"),
 * local_user_id (v1). A row never stores passwords, tokens, secrets, keys or hashes: for the
 * sources v1 and cli the before/after data go through redact(). v2 data is stored as given,
 * because structure recovery replays it and v2 resources carry no secrets.
 */
final class ChangeLog
{
    public const SOURCES = ['v2', 'v1', 'cli'];
    public const REDACTED = '[redacted]';
    private const MAX_ACTOR = 128;

    /** Null until the first write: whether migration 4.8.0/008 columns exist. */
    private static ?bool $extended = null;

    /**
     * Insert a change row and verify it was stored exactly. Strict: failures throw, so a v2 caller
     * inside a transaction rolls back together with the content change.
     *
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     * @param array<string,mixed> $actor
     * @param string[] $exempt Keys that must not be redacted for this resource (for example a section `key`).
     */
    public static function record(string $resource, int|string $id, string $action, ?array $before, ?array $after, array $actor, string $source = 'v2', array $exempt = []): int
    {
        if (!in_array($source, self::SOURCES, true)) { throw new \InvalidArgumentException('Unknown change source.'); }
        if ('v2' !== $source) {
            $before = null === $before ? null : self::redact($before, $exempt);
            $after = null === $after ? null : self::redact($after, $exempt);
        }
        $central = $actor['central_actor'] ?? null;
        $data = [
            'resource' => $resource, 'resource_id' => (string) $id, 'action' => $action,
            'before_data' => null === $before ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_data' => null === $after ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'integration_id' => (int) ($actor['integration_id'] ?? 0),
            'central_actor' => null === $central ? null : substr((string) $central, 0, self::MAX_ACTOR),
            'created_at' => time(),
        ];
        $extra = ['source' => $source, 'local_user_id' => isset($actor['local_user_id']) ? (int) $actor['local_user_id'] : null];
        $changeId = null;
        if (false !== self::$extended) {
            try {
                $changeId = Db::insert('cms_api_changes', $data + $extra);
                self::$extended = true;
                $data += $extra;
            } catch (\Throwable $e) {
                if (!self::unknownColumn($e)) { throw $e; }
                self::$extended = false;
            }
        }
        if (null === $changeId) { $changeId = Db::insert('cms_api_changes', $data); }
        $stored = Db::fetchAssoc('SELECT * FROM cms_api_changes WHERE change_id = ?', [$changeId]);
        foreach ($data as $field => $value) {
            if (!is_array($stored) || !array_key_exists($field, $stored) || $stored[$field] !== $value) {
                throw new Problem('storage_mismatch', 'Storage cannot preserve the submitted content exactly.', 409);
            }
        }
        return (int) $changeId;
    }

    /**
     * For writes that are not transactional (v1, command line): the change is already made, so a
     * logging failure (for example before the migration was applied) is reported to error_log only.
     */
    public static function recordQuietly(string $resource, int|string $id, string $action, ?array $before, ?array $after, array $actor, string $source, array $exempt = []): void
    {
        try {
            self::record($resource, $id, $action, $before, $after, $actor, $source, $exempt);
        } catch (\Throwable $e) {
            error_log(__METHOD__ . ': change was not logged (' . get_class($e) . ').');
        }
    }

    /** Actor of a command line run: `cli:<operating system user>`. */
    public static function cliActor(): array
    {
        $name = '';
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            $name = is_array($info) ? (string) $info['name'] : '';
        }
        if ('' === $name) { $name = (string) (getenv('USER') ?: getenv('USERNAME') ?: get_current_user()); }
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        return ['central_actor' => 'cli:' . ('' === $name ? 'unknown' : substr($name, 0, 100))];
    }

    /** Recursively replace the value of every secret-like key (password, token, secret, key, hash). */
    public static function redact(mixed $data, array $exempt = []): mixed
    {
        if (is_object($data)) { $data = get_object_vars($data); }
        if (!is_array($data)) { return $data; }
        $result = [];
        foreach ($data as $name => $value) {
            $result[$name] = is_string($name) && !in_array($name, $exempt, true) && self::secretName($name)
                ? self::REDACTED : self::redact($value, $exempt);
        }
        return $result;
    }

    /** Whole word match on snake_case and camelCase names: ac_key, apiKey, key_hash, refreshToken; not monkey. */
    public static function secretName(string $name): bool
    {
        return 1 === preg_match('/(?:^|_)(?:password|passwd|token|secret|key|hash)(?:$|_)/i', $name)
            || 1 === preg_match('/[a-z0-9](?:Password|Passwd|Token|Secret|Key|Hash)(?:[A-Z]|$)/', $name);
    }

    /**
     * Recent changes, newest first, without the before/after bodies. $actor matches the actor
     * label exactly, or the local user id when it is a number.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 20, ?string $resource = null, ?string $actor = null): array
    {
        $limit = max(1, min(500, $limit));
        $where = []; $params = [];
        if (null !== $resource) { $where[] = 'resource = ?'; $params[] = $resource; }
        if (null !== $actor) {
            if (ctype_digit($actor)) { $where[] = '(central_actor = ? OR local_user_id = ?)'; $params[] = $actor; $params[] = (int) $actor; }
            else { $where[] = 'central_actor = ?'; $params[] = $actor; }
        }
        $filter = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $columns = 'change_id, created_at, resource, resource_id, action, integration_id, central_actor';
        try {
            $rows = Db::fetchAllAssoc('SELECT ' . $columns . ', source, local_user_id FROM cms_api_changes' . $filter . ' ORDER BY change_id DESC LIMIT ' . $limit, $params);
        } catch (\Throwable $e) {
            if (!self::unknownColumn($e)) { throw $e; }
            $rows = Db::fetchAllAssoc('SELECT ' . $columns . ' FROM cms_api_changes' . $filter . ' ORDER BY change_id DESC LIMIT ' . $limit, $params);
        }
        return array_map(static fn (array $row): array => [
            'change_id' => (int) $row['change_id'], 'created_at' => (int) $row['created_at'],
            'resource' => $row['resource'], 'resource_id' => $row['resource_id'], 'action' => $row['action'],
            'source' => $row['source'] ?? 'v2',
            'actor' => $row['central_actor'],
            'integration_id' => (int) $row['integration_id'] ?: null,
            'local_user_id' => isset($row['local_user_id']) ? (int) $row['local_user_id'] : null,
        ], $rows ?: []);
    }

    /** MySQL error 1054, "unknown column": the schema predates migration 4.8.0/008. */
    public static function unknownColumn(\Throwable $e): bool
    {
        for (; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof \mysqli_sql_exception && 1054 === $e->getCode()) { return true; }
        }
        return false;
    }

    /** For tests: forget the detected schema. */
    public static function resetSchemaDetection(): void { self::$extended = null; }
}
