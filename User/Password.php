<?php

namespace Wbengine\User;

use Wbengine\Db;

/**
 * Password hashing with transparent upgrade of legacy unsalted MD5 hashes.
 *
 * New passwords use password_hash(). A legacy MD5 row can still log in and is
 * re-hashed on the first successful login, but only if the "password" column
 * is wide enough to hold a modern hash (see schemas/migrations in the skeleton).
 * Otherwise the legacy hash is kept, so a narrow column can never truncate a
 * hash and lock a user out.
 */
class Password
{
    /** Length of a bcrypt hash, the shortest value password_hash() may return. */
    const MIN_COLUMN_LENGTH = 60;

    private static $_canStoreModernHash = null;


    /**
     * Hash a new password. Falls back to legacy MD5 only when the column is too narrow.
     */
    public static function hash($plain)
    {
        return self::canStoreModernHash()
            ? password_hash((string)$plain, PASSWORD_DEFAULT)
            : md5((string)$plain);
    }


    /**
     * Check a plain password against a stored hash (modern or legacy MD5).
     */
    public static function verify($plain, $stored)
    {
        $stored = (string)$stored;

        if (self::isLegacy($stored)) {
            return hash_equals(strtolower($stored), md5((string)$plain));
        }

        return password_verify((string)$plain, $stored);
    }


    /**
     * Burn the same time as a real check, used when the user does not exist.
     */
    public static function verifyDummy($plain)
    {
        static $dummy = null;
        if (null === $dummy) {
            $dummy = password_hash('wbengine-dummy', PASSWORD_DEFAULT);
        }
        password_verify((string)$plain, $dummy);
    }


    public static function isLegacy($stored)
    {
        return (bool)preg_match('/^[a-f0-9]{32}$/i', (string)$stored);
    }


    public static function needsRehash($stored)
    {
        return self::isLegacy($stored) || password_needs_rehash((string)$stored, PASSWORD_DEFAULT);
    }


    /**
     * Whether the users.password column can hold a modern hash.
     */
    public static function canStoreModernHash()
    {
        if (null === self::$_canStoreModernHash) {
            $row = Db::fetchAssoc(
                'SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
                array(S_TABLE_USERS, 'password')
            );
            self::$_canStoreModernHash = is_array($row) && (int)$row['len'] >= self::MIN_COLUMN_LENGTH;

            if (!self::$_canStoreModernHash) {
                error_log(__CLASS__ . ': cms_users.password is narrower than ' . self::MIN_COLUMN_LENGTH
                    . ' characters, passwords stay on legacy MD5. Widen the column to varchar(255).');
            }
        }
        return self::$_canStoreModernHash;
    }
}
