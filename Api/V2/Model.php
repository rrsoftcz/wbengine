<?php

namespace Wbengine\Api\V2;

use Wbengine\Db;
use Wbengine\Api\Model\ApiUserModel;
use Wbengine\User\Role;

/** Integration identities belong to this website. Raw keys are never persisted. */
class Model extends \Wbengine\Model\ModelAbstract
{
    public const KEYS_TABLE = 'cms_api_integrations';
    public const AUDIT_TABLE = 'cms_api_audit';
    /** Seconds between two last_used_at writes of one key. */
    public const LAST_USED_INTERVAL = 60;
    public const LIST_LIMIT = 500;
    public const SCOPES = ['discovery:read', 'articles:read', 'articles:write', 'pages:read', 'pages:write', 'menu:read', 'menu:write', 'sections:read', 'sections:write', 'boxes:read', 'boxes:write', 'translations:read', 'translations:write', 'images:read', 'images:write', 'preview:read', 'publish:write', 'analytics:read', 'audit:read'];

    /**
     * Trusted local provisioning API. The administrator's current role is read from DB.
     * Requires an idle connection: existing caller-owned transactions are refused.
     */
    public function provision(int $adminUserId, string $name, array $scopes): array
    {
        $this->validateNameAndScopes($name, $scopes);
        $this->requireAdministrator($adminUserId);
        $token = 'wbv2_' . bin2hex(random_bytes(32));
        $connection = $this->beginOwnedTransaction();
        try {
            $values = [
                'name' => $name,
                'key_hash' => hash('sha256', $token),
                'scopes' => json_encode(array_values(array_unique($scopes)), JSON_THROW_ON_ERROR),
                'created_by' => $adminUserId,
                'created_at' => $this->now(),
            ];
            $id = Db::insert(self::KEYS_TABLE, $values);
            $this->verifyExact(Db::fetchAssoc('SELECT * FROM `' . self::KEYS_TABLE . '` WHERE integration_id = ?', [$id]), $values);
            $this->audit($id, null, 'integration.provision', 'allowed', $adminUserId);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollback();
            throw $e;
        }
        // This is the only return path that contains the raw token.
        return ['integration_id' => $id, 'key' => $token, 'name' => $name, 'scopes' => array_values(array_unique($scopes))];
    }

    /**
     * Revocation is immediate: every authenticated request reads the active row.
     * Requires an idle connection: existing caller-owned transactions are refused.
     */
    public function revoke(int $adminUserId, $integrationId): bool
    {
        $id = $this->positiveId($integrationId);
        $this->requireAdministrator($adminUserId);
        $connection = $this->beginOwnedTransaction();
        try {
            Db::query('UPDATE `' . self::KEYS_TABLE . '` SET revoked_at = ? WHERE integration_id = ? AND revoked_at IS NULL', [$this->now(), $id]);
            $changed = Db::getAffected() > 0;
            if ($changed) {
                $this->audit($id, null, 'integration.revoke', 'allowed', $adminUserId);
            }
            $connection->commit();
            return $changed;
        } catch (\Throwable $e) {
            $connection->rollback();
            throw $e;
        }
    }

    /** Metadata only: neither the key nor its hash ever leaves storage through this method. */
    public function list(): array
    {
        $rows = Db::fetchAllAssoc('SELECT integration_id, name, created_by, created_at, revoked_at, scopes, last_used_at FROM `' . self::KEYS_TABLE . '` ORDER BY integration_id DESC LIMIT ' . self::LIST_LIMIT);
        $result = [];
        foreach ($rows ?: [] as $row) {
            $scopes = json_decode((string) $row['scopes'], true);
            $result[] = [
                'integration_id' => (int) $row['integration_id'],
                'name' => (string) $row['name'],
                'created_by' => (int) $row['created_by'],
                'created_at' => (int) $row['created_at'],
                'revoked_at' => null === $row['revoked_at'] ? null : (int) $row['revoked_at'],
                'scopes' => is_array($scopes) && array_is_list($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
                'last_used_at' => null === $row['last_used_at'] ? null : (int) $row['last_used_at'],
            ];
        }
        return $result;
    }

    public function authenticate(string $token): ?array
    {
        if (!preg_match('/^wbv2_[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $row = Db::fetchAssoc('SELECT integration_id, name, scopes FROM `' . self::KEYS_TABLE . '` WHERE key_hash = ? AND revoked_at IS NULL LIMIT 1', [hash('sha256', $token)]);
        if (!is_array($row)) {
            return null;
        }
        try {
            $scopes = json_decode($row['scopes'], true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
        if (!is_array($scopes) || !array_is_list($scopes)) {
            return null;
        }
        foreach ($scopes as $scope) { if (!is_string($scope) || !in_array($scope, self::SCOPES, true)) { return null; } }
        $this->touchLastUsed((int) $row['integration_id']);
        return ['integration_id' => (int) $row['integration_id'], 'name' => $row['name'], 'scopes' => $scopes];
    }

    /**
     * One conditional UPDATE per key and interval, so a busy integration does not write on every
     * request and concurrent workers do not race. A failed write (for example before migration
     * 4.0.0/007 was applied) never fails the request.
     */
    private function touchLastUsed(int $integrationId): void
    {
        $now = $this->now();
        try {
            Db::query('UPDATE `' . self::KEYS_TABLE . '` SET last_used_at = ? WHERE integration_id = ? AND (last_used_at IS NULL OR last_used_at <= ?)', [$now, $integrationId, $now - self::LAST_USED_INTERVAL]);
        } catch (\Throwable $e) {
            error_log(__METHOD__ . ': last_used_at was not updated (' . get_class($e) . ').');
        }
    }

    protected function now(): int
    {
        return time();
    }

    /**
     * central_actor is an untrusted attribution label, never an authorization fact.
     * $detail is an error code or a reference (never a request body), stored when migration 4.8.0/008 exists.
     */
    /**
     * GET /changes: the change feed, or with outcome=rejected the rejected and failed writes. Filters are validated
     * here; before/after bodies are never part of the feed.
     */
    public function changeFeed(array $query): array
    {
        $pagination = Contract::pagination($query);
        $filters = [];
        $text = static function (string $key, string $pattern, int $max) use ($query, &$filters): void {
            if (!array_key_exists($key, $query)) { return; }
            $value = $query[$key];
            if (!is_string($value) || '' === $value || strlen($value) > $max || !preg_match($pattern, $value)) {
                throw new Problem('invalid_filter', 'The "' . $key . '" filter is not valid.');
            }
            $filters[$key] = $value;
        };
        $text('resource', '/^[a-z_]+$/D', 32);
        $text('resource_id', '/^[A-Za-z0-9_:.-]+$/D', 64);
        $text('action', '/^[A-Za-z0-9_.:-]+$/D', 64);
        $text('source', '/^(v2|v1|cli)$/D', 8);
        if (array_key_exists('actor', $query)) {
            $actor = $query['actor'];
            if (!is_string($actor) || '' === $actor || strlen($actor) > 128 || !preg_match('//u', $actor) || preg_match('/[\x00-\x1f\x7f]/', $actor)) { throw new Problem('invalid_filter', 'The "actor" filter is not valid.'); }
            $filters['actor'] = $actor;
        }
        foreach (['since', 'until'] as $key) {
            if (!array_key_exists($key, $query)) { continue; }
            if (!is_string($query[$key]) || !preg_match('/^[0-9]{1,12}$/D', $query[$key])) { throw new Problem('invalid_filter', 'The "' . $key . '" filter must be a time in epoch seconds.'); }
            $filters[$key] = (int) $query[$key];
        }
        $outcome = $query['outcome'] ?? 'applied';
        if (!is_string($outcome) || !in_array($outcome, ['applied', 'rejected'], true)) { throw new Problem('invalid_filter', 'The "outcome" filter must be applied or rejected.'); }
        $feed = \Wbengine\Audit\ChangeLog::page($filters, $pagination['per_page'], $pagination['offset'], 'rejected' === $outcome);
        return Contract::page($feed['items'], $query, $feed['total']);
    }

    public function audit(?int $integrationId, ?string $centralActor, string $action, string $outcome, ?int $localUserId = null, ?string $detail = null): void
    {
        if (null !== $centralActor && (strlen($centralActor) > 128 || preg_match('/[\x00-\x1f\x7f]/', $centralActor))) {
            throw new Problem('invalid_actor', 'Invalid central actor identifier.');
        }
        $values = [
            'integration_id' => $integrationId,
            'central_actor' => $centralActor,
            'local_user_id' => $localUserId,
            'action' => $action,
            'outcome' => $outcome,
            'created_at' => $this->now(),
        ];
        $withDetail = null === $detail ? $values : $values + ['detail' => substr($detail, 0, 128)];
        try {
            $id = Db::insert(self::AUDIT_TABLE, $withDetail);
        } catch (\Throwable $e) {
            if (null === $detail || !\Wbengine\Audit\ChangeLog::unknownColumn($e)) { throw $e; }
            $withDetail = $values;
            $id = Db::insert(self::AUDIT_TABLE, $values);
        }
        $this->verifyExact(Db::fetchAssoc('SELECT * FROM `' . self::AUDIT_TABLE . '` WHERE audit_id = ?', [$id]), $withDetail);
    }

    /** Local operator maintenance: at most $batch old audit entries per call. */
    public function pruneAudit(int $adminUserId, int $retainDays = 90, int $batch = 1000): int
    {
        if ($retainDays < 1 || $retainDays > 3650 || $batch < 1 || $batch > 1000) { throw new Problem('invalid_retention', 'Retention must be 1 to 3650 days; batch size 1 to 1000.'); }
        $this->requireAdministrator($adminUserId);
        $connection = $this->beginOwnedTransaction([self::AUDIT_TABLE]);
        try {
            Db::query('DELETE FROM `' . self::AUDIT_TABLE . '` WHERE created_at < ? ORDER BY audit_id LIMIT ?', [time() - $retainDays * 86400, $batch]);
            $deleted = (int) Db::getAffected();
            $this->audit(null, null, 'audit.prune', 'allowed', $adminUserId);
            $connection->commit();
            return $deleted;
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
    }

    private function verifyExact(array $stored, array $expected): void
    {
        foreach ($expected as $field => $value) {
            if (!array_key_exists($field, $stored) || $stored[$field] !== $value) { throw new Problem('storage_mismatch', 'Storage cannot preserve submitted metadata exactly.', 409); }
        }
    }

    /** Refuse nested transactions before BEGIN can implicitly commit caller work. */
    protected function beginOwnedTransaction(array $tables = [self::KEYS_TABLE, self::AUDIT_TABLE]): \mysqli
    {
        $connection = Db::getConnection();
        try {
            // Without SESSION/GLOBAL this applies to the next transaction and is
            // forbidden within an active transaction on both MySQL and MariaDB.
            // The guard deliberately sits outside the rollback catch in callers.
            if (false === $connection->query('SET TRANSACTION READ WRITE')) {
                throw new \RuntimeException('Unable to establish transaction ownership.');
            }
        } catch (\mysqli_sql_exception $e) {
            if (1568 === $e->getCode()) {
                throw new Problem('nested_transaction', 'Integration key changes require an idle database connection.', 409);
            }
            throw $e;
        }
        foreach ($tables as $table) {
            $row = Db::fetchAssoc('SHOW CREATE TABLE ' . Db::identifier($table));
            if (!preg_match('/\bENGINE=InnoDB\b/i', (string) ($row['Create Table'] ?? ''))) {
                throw new Problem('unsafe_storage', 'Transactional storage is required.', 503);
            }
        }
        if (!$connection->begin_transaction()) {
            throw new \RuntimeException('Unable to begin integration key transaction.');
        }
        return $connection;
    }

    private function validateNameAndScopes(string $name, array $scopes): void
    {
        if ('' === trim($name) || strlen($name) > 128 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new Problem('invalid_name', 'An integration name of 1 to 128 bytes is required.');
        }
        if (!$scopes || !array_is_list($scopes)) {
            throw new Problem('invalid_scope', 'At least one supported scope is required.');
        }
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !in_array($scope, self::SCOPES, true)) {
                throw new Problem('invalid_scope', 'Unsupported integration scope.');
            }
        }
    }

    private function requireAdministrator(int $userId): void
    {
        $actor = (new ApiUserModel())->getAuthorization($userId);
        if (!$actor || !Role::atLeast($actor['role'], Role::ADMIN)) {
            throw new Problem('forbidden', 'Administrator access is required.', 403);
        }
    }

    private function positiveId($id): int
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $id) || (int) $id > 2147483647) {
            throw new Problem('invalid_id', 'Invalid integration identifier.');
        }
        return (int) $id;
    }
}
