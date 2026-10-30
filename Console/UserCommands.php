<?php
namespace Wbengine\Console;

use Wbengine\Api\Exception\ApiException;
use Wbengine\Api\Model\ApiUserModel;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Audit\ChangeLog;
use Wbengine\Db;
use Wbengine\User\Password;
use Wbengine\User\Role;
use Wbengine\User\UserException;

/**
 * user:create, user:password, user:list and user:delete. Validation, hashing and token
 * revocation are delegated to ApiUserModel, so the CLI and POST /api/users/create agree.
 * Results contain no password and no hash, and messages are safe to print.
 */
final class UserCommands
{
    /** The API enforces no minimum; the CLI refuses weaker passwords for the accounts it creates. */
    public const MIN_PASSWORD_LENGTH = 12;
    /** bcrypt ignores everything after 72 bytes, so a longer password would silently weaken. */
    public const MAX_PASSWORD_BYTES = 72;
    /** RFC 2606 and RFC 6761 reserved domains and top level domains. */
    public const EXAMPLE_DOMAINS = ['example.com', 'example.org', 'example.net'];
    public const EXAMPLE_TLDS = ['example', 'test', 'invalid', 'localhost'];

    private ApiUserModel $users;
    private PasswordPrompt $prompt;

    public function __construct(?PasswordPrompt $prompt = null, ?ApiUserModel $users = null)
    {
        $this->prompt = $prompt ?? new PasswordPrompt();
        $this->users = $users ?? new ApiUserModel();
    }

    /** True for an address in a reserved documentation domain, compared on whole labels. */
    public static function isExampleEmail(string $email): bool
    {
        $at = strrpos($email, '@');
        if ($at === false) { return false; }
        $domain = strtolower(rtrim(substr($email, $at + 1), '.'));
        foreach (self::EXAMPLE_DOMAINS as $reserved) {
            if ($domain === $reserved || str_ends_with($domain, '.' . $reserved)) { return true; }
        }
        foreach (self::EXAMPLE_TLDS as $tld) {
            if ($domain === $tld || str_ends_with($domain, '.' . $tld)) { return true; }
        }
        return false;
    }

    public function create(array $options): array
    {
        $email = $this->email($options);
        $role = $options['role'] ?? Role::USER;
        if (!is_string($role) || !Role::isValid($role)) {
            throw new CommandException('The role must be one of: ' . implode(', ', Role::all()) . '.');
        }
        if ($this->findByEmail($email) !== null) { throw new CommandException('A user with this e-mail already exists.'); }
        $username = $this->username($options, $email);

        $password = $this->checkedPassword($this->prompt->read());
        try {
            $result = $this->users->createUser(['email' => $email, 'username' => $username, 'password' => $password, 'role' => $role], false, ChangeLog::cliActor(), 'cli');
        } catch (ApiException | ApiModelException | UserException $e) {
            throw new CommandException($e->getMessage());
        }
        return ['created' => true, 'id' => (int) $result['user_id'], 'email' => $email, 'username' => $username, 'role' => $role];
    }

    public function password(array $options): array
    {
        $user = $this->findByEmail($this->email($options));
        if ($user === null) { throw new CommandException('No user with this e-mail exists.'); }
        $password = $this->checkedPassword($this->prompt->read());
        try {
            // Hashes the password.
            $this->users->updateUser((int) $user['user_id'], ['password' => $password], ChangeLog::cliActor(), 'cli');
        } catch (ApiException | ApiModelException | UserException $e) {
            throw new CommandException($e->getMessage());
        }
        // End every web session of the user.
        if ($this->tableExists(S_TABLE_SESSIONS)) {
            Db::query(sprintf('DELETE FROM %s WHERE `user_id` = ?', S_TABLE_SESSIONS), [(int) $user['user_id']]);
        }
        return ['updated' => true, 'id' => (int) $user['user_id'], 'email' => $user['email']];
    }

    public function list(): array
    {
        $rows = Db::fetchAllAssoc(sprintf('SELECT `user_id`, `username`, `email`, `role`, `password` FROM %s WHERE `user_id` > 0 ORDER BY `user_id`', S_TABLE_USERS)) ?: [];
        return ['users' => array_map(static fn (array $row): array => [
            'id' => (int) $row['user_id'], 'username' => $row['username'], 'email' => $row['email'], 'role' => $row['role'],
            'active' => self::hasUsablePassword($row['password']),
        ], $rows)];
    }

    public function delete(array $options): array
    {
        $example = ($options['example'] ?? false) === true;
        $dryRun = ($options['dry-run'] ?? false) === true;
        if (($options['example'] ?? false) !== false && !$example || ($options['dry-run'] ?? false) !== false && !$dryRun) {
            throw new CommandException('Use --example and --dry-run without a value.');
        }
        if ($example === isset($options['email'])) {
            throw new CommandException('Supply exactly one of --email=EMAIL or --example.');
        }
        $targets = $this->targets($example ? null : $this->email($options));
        if ($example && !$targets) { return ['deleted' => [], 'dryRun' => $dryRun]; }
        if (!$example && !$targets) { throw new CommandException('No user with this e-mail exists.'); }
        $this->assertAdminRemains($targets, false);
        if ($dryRun) { return ['deleted' => $this->summary($targets), 'dryRun' => true]; }

        $ids = array_map('intval', array_column($targets, 'user_id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        Db::getConnection()->begin_transaction();
        try {
            // Re-check under a lock, so two concurrent deletions cannot remove every administrator.
            $this->assertAdminRemains($this->targets($example ? null : $this->email($options), true), true);
            if ($this->tableExists(S_TABLE_SESSIONS)) { Db::query(sprintf('DELETE FROM %s WHERE `user_id` IN (%s)', S_TABLE_SESSIONS, $marks), $ids); }
            // The audit trail stays, only its link to the removed account is cleared.
            if ($this->tableExists('cms_api_audit')) { Db::query(sprintf('UPDATE cms_api_audit SET `local_user_id` = NULL WHERE `local_user_id` IN (%s)', $marks), $ids); }
            Db::query(sprintf('DELETE FROM %s WHERE `user_id` IN (%s) AND `user_id` > 0', S_TABLE_USERS, $marks), $ids);
            Db::getConnection()->commit();
        } catch (\Throwable $e) {
            try { Db::getConnection()->rollback(); } catch (\Throwable) { }
            throw $e;
        }
        foreach ($this->summary($targets) as $row) {
            ChangeLog::recordQuietly('user', $row['id'], 'delete', $row, null, ChangeLog::cliActor(), 'cli');
        }
        return ['deleted' => $this->summary($targets), 'dryRun' => false];
    }

    /** A hash that cannot be verified (empty or the column default 0) means nobody can log in. */
    private static function hasUsablePassword($stored): bool
    {
        return !in_array((string) $stored, ['', '0'], true);
    }

    private function email(array $options): string
    {
        $email = $options['email'] ?? null;
        if (!is_string($email) || $email === '') { throw new CommandException('Supply --email=EMAIL.'); }
        if (strlen($email) > 100 || false === filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new CommandException('Invalid e-mail address.'); }
        return $email;
    }

    private function username(array $options, string $email): string
    {
        if (isset($options['username'])) {
            $username = $options['username'];
            if (!is_string($username) || !preg_match('/^[A-Za-z0-9._+@-]{1,150}$/D', $username)) {
                throw new CommandException('The username may contain 1 to 150 letters, digits and . _ + @ - only.');
            }
            if ($this->usernameTaken($username)) { throw new CommandException('A user with this username already exists.'); }
            return $username;
        }
        $base = substr(preg_replace('/[^A-Za-z0-9._+-]/', '_', (string) strstr($email, '@', true)), 0, 140);
        $base = $base === '' ? 'user' : $base;
        for ($suffix = 1, $username = $base; $this->usernameTaken($username); $suffix++) {
            if ($suffix > 1000) { throw new CommandException('Could not derive a free username, pass --username.'); }
            $username = $base . '-' . ($suffix + 1);
        }
        return $username;
    }

    private function checkedPassword(string $password): string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new CommandException('The password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters long.');
        }
        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            throw new CommandException('The password must be at most ' . self::MAX_PASSWORD_BYTES . ' bytes long.');
        }
        return $password;
    }

    private function findByEmail(string $email): ?array
    {
        $row = Db::fetchAssoc(sprintf('SELECT `user_id`, `email` FROM %s WHERE `email` = ? AND `user_id` > 0 LIMIT 1', S_TABLE_USERS), [$email]);
        return is_array($row) ? $row : null;
    }

    private function usernameTaken(string $username): bool
    {
        return is_array(Db::fetchAssoc(sprintf('SELECT `user_id` FROM %s WHERE `username` = ? LIMIT 1', S_TABLE_USERS), [$username]));
    }

    /** Users to delete: one by e-mail, or every example address when $email is null. */
    private function targets(?string $email, bool $lock = false): array
    {
        $rows = Db::fetchAllAssoc(sprintf('SELECT `user_id`, `email`, `role`, `password` FROM %s WHERE `user_id` > 0 ORDER BY `user_id`%s', S_TABLE_USERS, $lock ? ' FOR UPDATE' : '')) ?: [];
        return array_values(array_filter($rows, static fn (array $row): bool => $email === null
            ? self::isExampleEmail((string) $row['email'])
            : strcasecmp((string) $row['email'], $email) === 0));
    }

    private function assertAdminRemains(array $targets, bool $lock): void
    {
        $gone = array_column($targets, 'user_id');
        $rows = Db::fetchAllAssoc(sprintf('SELECT `user_id`, `password` FROM %s WHERE `role` = ? AND `user_id` > 0%s', S_TABLE_USERS, $lock ? ' FOR UPDATE' : ''), [Role::ADMIN]) ?: [];
        $remaining = array_filter($rows, static fn (array $row): bool => !in_array($row['user_id'], $gone, false) && self::hasUsablePassword($row['password']));
        if (!$remaining) {
            throw new CommandException('Refused: no active administrator would remain. Create one first with user:create --role=admin.');
        }
    }

    private function summary(array $targets): array
    {
        return array_map(static fn (array $row): array => ['id' => (int) $row['user_id'], 'email' => $row['email'], 'role' => $row['role']], $targets);
    }

    private function tableExists(string $table): bool
    {
        return is_array(Db::fetchAssoc('SELECT 1 AS present FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1', [$table]));
    }
}
