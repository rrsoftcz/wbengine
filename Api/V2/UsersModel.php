<?php

namespace Wbengine\Api\V2;

use Wbengine\Api\Auth\RefreshTokens;
use Wbengine\Audit\ChangeLog;
use Wbengine\Db;
use Wbengine\User\AccountState;
use Wbengine\User\PasswordSetup;
use Wbengine\User\Password;
use Wbengine\User\Role;

/**
 * Limited remote user management for the central Admin: list, read, create, change the role,
 * disable and enable, and issue a one-time password link. There is no delete, no way to set or
 * read a password, and the last active administrator cannot be demoted or disabled. Passwords,
 * hashes and links never enter the change log or any response except the one-time link itself.
 */
final class UsersModel
{
    private const FIELDS = ['email', 'username', 'firstname', 'lastname', 'role'];

    public function collection(array $query): array
    {
        $pagination = Contract::pagination($query);
        $where = ['`user_id` > 0']; $params = [];
        $search = Contract::search($query);
        if (null !== $search) {
            $where[] = "(`email` LIKE ? ESCAPE '\\\\' OR `username` LIKE ? ESCAPE '\\\\')";
            $pattern = Contract::likePattern($search);
            array_push($params, $pattern, $pattern);
        }
        if (array_key_exists('role', $query)) {
            if (!is_string($query['role']) || !Role::isValid($query['role'])) { throw new Problem('invalid_filter', 'The "role" filter is not valid.'); }
            $where[] = '`role` = ?'; $params[] = $query['role'];
        }
        $filter = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM ' . S_TABLE_USERS . $filter, $params)['n'];
        $rows = Db::fetchAllAssoc('SELECT ' . $this->columns() . ' FROM ' . S_TABLE_USERS . $filter . ' ORDER BY `user_id` LIMIT ? OFFSET ?', array_merge($params, [$pagination['per_page'], $pagination['offset']]));
        return Contract::page(array_map(fn (array $row): array => $this->publicRow($row), $rows ?: []), $query, $total);
    }

    public function read(int $id): array
    {
        return Contract::success($this->publicRow($this->find($id)));
    }

    /**
     * Creates an account that cannot sign in until its owner sets a password through the returned link.
     *
     * @return array the user and "password_setup" with the one-time token
     */
    public function create(array $data, array $actor): array
    {
        $email = $this->email($data['email'] ?? null);
        if (Db::fetchAssoc('SELECT `user_id` FROM ' . S_TABLE_USERS . ' WHERE `email` = ? LIMIT 1;', [$email])) {
            throw new Problem('duplicate_email', 'An account with this e-mail address already exists.', 409);
        }
        $role = $this->role($data['role'] ?? Role::USER);
        $row = ['email' => $email, 'username' => $this->text($data, 'username', 150) ?? $email, 'firstname' => $this->text($data, 'firstname', 45), 'lastname' => $this->text($data, 'lastname', 150), 'role' => $role,
            // An unusable password: nobody knows it, so the account is closed until the link is used.
            'password' => Password::hash(bin2hex(random_bytes(24)))];
        $id = (int) Db::insert(S_TABLE_USERS, $row);
        $setup = PasswordSetup::issue($id);
        ChangeLog::record('user', $id, 'create', null, array_diff_key($row, ['password' => 1]) + ['passwordSet' => false], $actor);
        return Contract::success($this->publicRow($this->find($id)) + ['password_setup' => $setup]);
    }

    /** Role and the disabled flag, plus the names. Anything else is rejected. */
    public function update(int $id, array $data, array $actor): array
    {
        $before = $this->find($id);
        $changes = [];
        foreach (['firstname', 'lastname'] as $name) {
            if (array_key_exists($name, $data)) { $changes[$name] = $this->text($data, $name, 'firstname' === $name ? 45 : 150); }
        }
        $adminLeaves = false;
        if (array_key_exists('role', $data)) {
            $role = $this->role($data['role']);
            if ($role !== $before['role']) { $changes['role'] = $role; $adminLeaves = Role::ADMIN === $before['role']; }
        }
        $disable = null;
        if (array_key_exists('disabled', $data)) {
            if (!is_bool($data['disabled'])) { throw new Problem('invalid_field', 'The "disabled" field must be true or false.'); }
            $disable = $data['disabled'];
            if ($disable && !$this->disabled($before)) { $adminLeaves = $adminLeaves || Role::ADMIN === $before['role']; }
            if (!AccountState::isPresent()) { throw new Problem('unsupported', 'The site database has no account state yet.', 409); }
        }
        if ($adminLeaves && !$this->disabled($before) && $this->activeAdmins() <= 1) {
            throw new Problem('last_admin', 'The last administrator cannot be demoted or disabled.', 409);
        }
        if ([] !== $changes) { Db::update(S_TABLE_USERS, $changes, ['user_id' => $id]); }
        if (null !== $disable && $disable !== $this->disabled($before)) {
            AccountState::set($id, $disable);
            // Disabling ends API sessions at once.
            if ($disable) { $this->revokeTokens($id); }
        }
        if ([] !== $changes && isset($changes['role'])) { $this->revokeTokens($id); }
        $after = $this->find($id);
        $action = null !== $disable && $disable !== $this->disabled($before) ? ($disable ? 'disable' : 'enable') : 'update';
        ChangeLog::record('user', $id, $action, $this->audited($before), $this->audited($after), $actor);
        return Contract::success($this->publicRow($after));
    }

    /** A new one-time link for an active account. The link is shown once. */
    public function passwordReset(int $id, array $actor): array
    {
        $user = $this->find($id);
        if ($this->disabled($user)) { throw new Problem('account_disabled', 'A disabled account cannot be reset. Enable it first.', 409); }
        $setup = PasswordSetup::issue($id);
        // The log records that a link was issued, never the link.
        ChangeLog::record('user', $id, 'password_reset', null, ['passwordLinkIssued' => true], $actor);
        return Contract::success(['user_id' => $id, 'password_setup' => $setup]);
    }

    private function find(int $id): array
    {
        $row = Db::fetchAssoc('SELECT ' . $this->columns() . ' FROM ' . S_TABLE_USERS . ' WHERE `user_id` = ? AND `user_id` > 0 LIMIT 1;', [$id]);
        if (!is_array($row)) { throw new Problem('not_found', 'The user was not found.', 404); }
        return $row;
    }

    private function columns(): string
    {
        return '`user_id`, `email`, `username`, `firstname`, `lastname`, `role`' . (AccountState::isPresent() ? ', `disabled_at`' : '');
    }

    private function disabled(array $row): bool { return null !== ($row['disabled_at'] ?? null); }

    private function publicRow(array $row): array
    {
        return ['id' => (int) $row['user_id'], 'email' => $row['email'], 'username' => $row['username'], 'firstname' => $row['firstname'], 'lastname' => $row['lastname'], 'role' => $row['role'], 'disabled' => $this->disabled($row)];
    }

    private function audited(array $row): array
    {
        return array_diff_key($this->publicRow($row), ['id' => 1]);
    }

    private function activeAdmins(): int
    {
        return (int) Db::fetchAssoc('SELECT COUNT(*) AS c FROM ' . S_TABLE_USERS . ' WHERE `role` = ?' . AccountState::activeSql() . ';', [Role::ADMIN])['c'];
    }

    private function revokeTokens(int $id): void
    {
        try { (new RefreshTokens())->revokeAllForUser($id); } catch (\Throwable $e) { error_log(__METHOD__ . ': ' . $e->getMessage()); }
    }

    private function email($value): string
    {
        if (!is_string($value) || strlen($value) > 100 || false === filter_var($value, FILTER_VALIDATE_EMAIL)) { throw new Problem('invalid_email', 'A valid e-mail address of at most 100 characters is required.'); }
        return strtolower($value);
    }

    private function role($value): string
    {
        if (!is_string($value) || !Role::isValid($value)) { throw new Problem('invalid_role', 'The role must be one of: ' . implode(', ', Role::all()) . '.'); }
        return $value;
    }

    private function text(array $data, string $field, int $max): ?string
    {
        if (!array_key_exists($field, $data) || null === $data[$field]) { return null; }
        $value = $data[$field];
        if (!is_string($value) || mb_strlen($value) > $max || !preg_match('//u', $value) || preg_match('/[\x00-\x1f\x7f]/', $value)) { throw new Problem('invalid_field', 'The "' . $field . '" field is not valid.'); }
        return '' === trim($value) ? null : trim($value);
    }

    public static function fields(): array { return array_merge(self::FIELDS, ['disabled']); }
}
