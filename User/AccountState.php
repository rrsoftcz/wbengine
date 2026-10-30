<?php

namespace Wbengine\User;

use Wbengine\Db;

/**
 * Whether an account is disabled (cms_users.disabled_at, migration 4.10.0/009). A site that has not
 * applied the migration has no disabled accounts, so every check answers "active" and nothing breaks.
 */
final class AccountState
{
    private static ?bool $present = null;

    public static function isPresent(): bool
    {
        if (null === self::$present) {
            try {
                Db::fetchAssoc(sprintf('SELECT `disabled_at` FROM %s LIMIT 1;', S_TABLE_USERS));
                self::$present = true;
            } catch (\Throwable $e) {
                if (!self::unknownColumn($e)) { throw $e; }
                self::$present = false;
            }
        }
        return self::$present;
    }

    public static function isDisabled(int $userId): bool
    {
        if (!self::isPresent()) { return false; }
        $row = Db::fetchAssoc(sprintf('SELECT `disabled_at` FROM %s WHERE `user_id` = ? LIMIT 1;', S_TABLE_USERS), array($userId));
        return is_array($row) && null !== $row['disabled_at'];
    }

    /** SQL fragment excluding disabled accounts, empty before the migration. */
    public static function activeSql(): string
    {
        return self::isPresent() ? ' AND `disabled_at` IS NULL' : '';
    }

    public static function set(int $userId, bool $disabled, ?int $now = null): void
    {
        if (!self::isPresent()) { throw new UserException('The schema has no account state. Apply migration 4.10.0/009.'); }
        Db::update(S_TABLE_USERS, array('disabled_at' => $disabled ? ($now ?? time()) : null), array('user_id' => $userId));
    }

    /** For tests: forget the detected schema. */
    public static function reset(): void { self::$present = null; }

    private static function unknownColumn(\Throwable $e): bool
    {
        for (; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof \mysqli_sql_exception && 1054 === $e->getCode()) { return true; }
        }
        return false;
    }
}
