<?php

namespace Wbengine\Privacy;

use Wbengine\Db;
use Wbengine\Config;
use Wbengine\RateLimit\Limiter;

final class DatabaseHandler implements HandlerInterface
{
    private const OPTIONAL_TABLES = [
        'cms_sessions' => ['user_id', 'session_id'],
        'cms_refresh_tokens' => ['user_id', 'id'],
        'cms_api_integrations' => ['created_by', 'integration_id'],
        'cms_api_audit' => ['local_user_id', 'audit_id'],
        'cms_api_changes' => ['central_actor', 'change_id'],
        'cms_api_revisions' => ['central_actor', 'revision_id'],
    ];

    public function name(): string
    {
        return 'database';
    }

    public function inspect(int $userId): array
    {
        return $this->inspectSnapshot($userId, null);
    }

    private function inspectSnapshot(int $userId, ?int $lockedAdministratorCount): array
    {
        $columns = $this->columns('cms_users');
        if (!in_array('user_id', $columns, true) || !in_array('role', $columns, true)) {
            throw new \RuntimeException('The standard user table is not available.');
        }

        $select = array_values(array_intersect(['user_id', 'username', 'email', 'firstname', 'lastname', 'user_ip', 'role'], $columns));
        $account = Db::fetchAssoc(
            'SELECT ' . implode(', ', array_map([Db::class, 'identifier'], $select)) . ' FROM `cms_users` WHERE `user_id` = ?',
            [$userId]
        );
        $account = is_array($account) ? $account : null;

        $snapshots = ['account' => $account];
        $actions = [
            'account' => ['action' => 'delete_user_account', 'records' => null === $account ? 0 : 1],
            'sessions' => ['action' => 'delete_sessions', 'records' => 0],
            'refresh_tokens' => ['action' => 'delete_refresh_tokens', 'records' => 0],
            'api_integrations' => ['action' => 'remove_creator_link', 'records' => 0],
            'api_audit' => ['action' => 'anonymize_actor_reference', 'records' => 0],
            'api_history' => ['action' => 'anonymize_actor_label', 'records' => 0],
        ];

        foreach (self::OPTIONAL_TABLES as $table => [$ownerColumn, $keyColumn]) {
            $tableColumns = $this->columns($table, true);
            if (!$tableColumns) {
                $snapshots[$table] = [];
                continue;
            }
            if (!in_array($keyColumn, $tableColumns, true)) {
                $snapshots[$table] = ['unsupported' => true];
                continue;
            }

            if (in_array($table, ['cms_api_audit', 'cms_api_changes', 'cms_api_revisions'], true)) {
                $rows = $this->actorRows($table, $keyColumn, $tableColumns, $account, $userId);
                $action = 'cms_api_audit' === $table ? 'api_audit' : 'api_history';
                $actions[$action]['records'] += count($rows);
            } elseif (in_array($ownerColumn, $tableColumns, true)) {
                $rows = Db::fetchAllAssoc(
                    'SELECT * FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($ownerColumn) . ' = ? ORDER BY ' . Db::identifier($keyColumn),
                    [$userId]
                );
                $action = 'cms_sessions' === $table ? 'sessions'
                    : ('cms_refresh_tokens' === $table ? 'refresh_tokens' : 'api_integrations');
                if ('cms_api_integrations' === $table) {
                    foreach ($rows as &$row) {
                        $row['created_by'] = 0;
                    }
                    unset($row);
                }
                $actions[$action]['records'] += count($rows);
            } else {
                $rows = [];
            }
            $snapshots[$table] = $rows;
        }

        $blocked = false;
        if ($account && ($account['role'] ?? null) === 'admin') {
            if (null === $lockedAdministratorCount) {
                $admins = Db::fetchAssoc('SELECT COUNT(*) AS count FROM `cms_users` WHERE `role` = ?', ['admin']);
                $lockedAdministratorCount = (int) ($admins['count'] ?? 0);
            }
            $blocked = $lockedAdministratorCount <= 1;
        }

        return [
            'actions' => $actions,
            'blocked' => $blocked,
            'fingerprint' => hash('sha256', json_encode($snapshots, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
        ];
    }

    public function erase(int $userId, string $fingerprint): void
    {
        $connection = Db::getConnection();
        $tables = ['cms_users'];
        foreach (array_keys(self::OPTIONAL_TABLES) as $table) {
            if ($this->columns($table, true)) {
                $tables[] = $table;
            }
        }

        try {
            $connection->query('SET TRANSACTION READ WRITE');
        } catch (\mysqli_sql_exception $exception) {
            if (1568 === $exception->getCode()) {
                throw new \RuntimeException('Privacy erasure requires an idle database connection.');
            }
            throw new \RuntimeException('Unable to prepare the privacy erasure transaction.');
        }
        foreach (array_unique($tables) as $table) {
            try {
                $definition = Db::fetchAssoc('SHOW CREATE TABLE ' . Db::identifier($table));
            } catch (\Throwable) {
                throw new \RuntimeException('Privacy erasure requires all affected tables to use InnoDB.');
            }
            if (!preg_match('/\bENGINE=InnoDB\b/i', (string) ($definition['Create Table'] ?? ''))) {
                throw new \RuntimeException('Privacy erasure requires all affected tables to use InnoDB.');
            }
        }
        if (!$connection->begin_transaction()) {
            throw new \RuntimeException('Unable to begin the privacy erasure transaction.');
        }

        try {
            // Lock administrators in a stable order before deleting any account.
            // Concurrent erasures cannot each observe the same last-admin count.
            $administrators = Db::fetchAllAssoc('SELECT `user_id` FROM `cms_users` WHERE `role` = ? ORDER BY `user_id` FOR UPDATE', ['admin']);
            $account = Db::fetchAssoc('SELECT * FROM `cms_users` WHERE `user_id` = ? FOR UPDATE', [$userId]);
            $account = is_array($account) ? $account : null;
            $identifiers = array_values(array_unique(array_filter([
                $account['email'] ?? null,
                $account['username'] ?? null,
            ], static fn ($value) => is_string($value) && '' !== $value)));
            foreach (array_slice($tables, 1) as $table) {
                $this->lockTargetRows($table, $userId, $identifiers);
            }

            $current = $this->inspectSnapshot($userId, count($administrators));
            if (!hash_equals($fingerprint, $current['fingerprint'])) {
                throw new \RuntimeException('The inspection has changed. Run privacy:inspect again.');
            }
            if ($current['blocked']) {
                throw new \RuntimeException('The last administrator cannot be erased.');
            }
            $this->clearLoginLimiterKeys($userId, $account);
            if (in_array('user_id', $this->columns('cms_sessions', true), true)) {
                Db::query('DELETE FROM `cms_sessions` WHERE `user_id` = ?', [$userId]);
            }
            if (in_array('user_id', $this->columns('cms_refresh_tokens', true), true)) {
                Db::query('DELETE FROM `cms_refresh_tokens` WHERE `user_id` = ?', [$userId]);
            }
            if (in_array('created_by', $this->columns('cms_api_integrations', true), true)) {
                Db::query('UPDATE `cms_api_integrations` SET `created_by` = 0 WHERE `created_by` = ?', [$userId]);
            }
            if ($this->columns('cms_api_audit', true)) {
                $auditColumns = $this->columns('cms_api_audit');
                if (in_array('local_user_id', $auditColumns, true)) {
                    Db::query('UPDATE `cms_api_audit` SET `local_user_id` = NULL WHERE `local_user_id` = ?', [$userId]);
                }
                $this->anonymizeActors('cms_api_audit', $identifiers);
            }
            $this->anonymizeActors('cms_api_changes', $identifiers);
            $this->anonymizeActors('cms_api_revisions', $identifiers);
            Db::query('DELETE FROM `cms_users` WHERE `user_id` = ?', [$userId]);
            if (!$connection->commit()) {
                throw new \RuntimeException('Unable to commit the privacy erasure transaction.');
            }
        } catch (\Throwable $exception) {
            $connection->rollback();
            if ($exception instanceof \RuntimeException
                && in_array($exception->getMessage(), ['The inspection has changed. Run privacy:inspect again.', 'The last administrator cannot be erased.'], true)) {
                throw $exception;
            }
            throw new \RuntimeException('Privacy erasure failed and all database changes were rolled back.');
        }
    }

    private function lockTargetRows(string $table, int $userId, array $identifiers): void
    {
        $columns = $this->columns($table, true);
        if (!$columns) {
            return;
        }
        $owner = self::OPTIONAL_TABLES[$table][0] ?? null;
        $where = [];
        $params = [];
        if ($owner && in_array($owner, $columns, true)) {
            $where[] = Db::identifier($owner) . ' = ?';
            $params[] = $userId;
        }
        if ('cms_api_audit' === $table && in_array('local_user_id', $columns, true)) {
            $where[] = '`local_user_id` = ?';
            $params[] = $userId;
        }
        if (in_array('central_actor', $columns, true) && $identifiers) {
            $where[] = 'BINARY `central_actor` IN (' . implode(', ', array_fill(0, count($identifiers), '?')) . ')';
            array_push($params, ...$identifiers);
        }
        if ($where) {
            Db::fetchAllAssoc('SELECT * FROM ' . Db::identifier($table) . ' WHERE (' . implode(' OR ', $where) . ') FOR UPDATE', $params);
        }
    }

    private function anonymizeActors(string $table, array $identifiers): void
    {
        if (!$identifiers || !in_array('central_actor', $this->columns($table, true), true)) {
            return;
        }
        Db::query('UPDATE ' . Db::identifier($table) . ' SET `central_actor` = ? WHERE BINARY `central_actor` IN (' . implode(', ', array_fill(0, count($identifiers), '?')) . ')', array_merge(['[erased]'], $identifiers));
    }

    private function clearLoginLimiterKeys(int $userId, ?array $account): void
    {
        if (!$account) {
            return;
        }
        $limits = Config::getLoginRateLimit();
        if (!isset($limits['dir']) || !is_string($limits['dir']) || '' === $limits['dir']) {
            return;
        }
        $accounts = array_values(array_unique(array_filter([
            $account['username'] ?? null,
            $account['email'] ?? null,
        ], static fn ($value) => is_string($value) && '' !== trim($value))));
        $accounts = array_map(static fn ($value) => mb_strtolower(trim($value)), $accounts);
        $ips = [];
        foreach ([['cms_users', 'user_id', 'user_ip'], ['cms_sessions', 'user_id', 'user_ip'], ['cms_refresh_tokens', 'user_id', 'ip']] as [$table, $owner, $ipColumn]) {
            $columns = $this->columns($table, true);
            if (!in_array($owner, $columns, true) || !in_array($ipColumn, $columns, true)) {
                continue;
            }
            foreach (Db::fetchAllAssoc('SELECT ' . Db::identifier($ipColumn) . ' AS ip FROM ' . Db::identifier($table) . ' WHERE ' . Db::identifier($owner) . ' = ?', [$userId]) as $row) {
                if (is_string($row['ip'] ?? null) && '' !== $row['ip']) {
                    $ips[] = $row['ip'];
                }
            }
        }
        $limiter = new Limiter($limits['dir']);
        $keys = [];
        foreach ($accounts as $login) {
            $keys[] = 'login:account:' . $login;
            foreach (array_unique($ips) as $ip) {
                $keys[] = 'login:pair:' . $ip . '|' . $login;
            }
        }
        foreach (array_unique($keys) as $key) {
            if (!$limiter->clear($key)) {
                throw new \RuntimeException('Unable to remove all login rate-limit records.');
            }
        }
    }

    private function actorRows(string $table, string $keyColumn, array $columns, ?array $account, int $userId): array
    {
        $where = [];
        $params = [];
        if ('cms_api_audit' === $table && in_array('local_user_id', $columns, true)) {
            $where[] = '`local_user_id` = ?';
            $params[] = $userId;
        }
        if (in_array('central_actor', $columns, true) && $account) {
            $identifiers = array_values(array_unique(array_filter([
                $account['email'] ?? null,
                $account['username'] ?? null,
            ], static fn ($value) => is_string($value) && '' !== $value)));
            if ($identifiers) {
                $where[] = 'BINARY `central_actor` IN (' . implode(', ', array_fill(0, count($identifiers), '?')) . ')';
                array_push($params, ...$identifiers);
            }
        }
        if (!$where) {
            return [];
        }
        return Db::fetchAllAssoc(
            'SELECT * FROM ' . Db::identifier($table) . ' WHERE (' . implode(' OR ', $where) . ') ORDER BY ' . Db::identifier($keyColumn),
            $params
        );
    }

    private function columns(string $table, bool $optional = false): array
    {
        try {
            $result = Db::getConnection()->query('SHOW COLUMNS FROM ' . Db::identifier($table));
            return array_column($result->fetch_all(MYSQLI_ASSOC), 'Field');
        } catch (\Throwable $exception) {
            if ($optional) {
                return [];
            }
            throw $exception;
        }
    }
}
