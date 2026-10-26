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
    private static $_columnLength = null;


    /**
     * Hash a new password. A missing schema migration must never weaken new hashes.
     */
    public static function hash($plain)
    {
        if (!self::canStoreModernHash()) {
            throw new UserException('Cannot store a password securely. Migrate cms_users.password to varchar(255).');
        }
        $hash = password_hash((string)$plain, PASSWORD_DEFAULT);
        if (strlen($hash) > self::_columnLength()) {
            throw new UserException('Cannot store a password securely. Migrate cms_users.password to varchar(255).');
        }
        return $hash;
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
            self::$_canStoreModernHash = self::_columnLength() >= self::MIN_COLUMN_LENGTH;

            if (!self::$_canStoreModernHash) {
                error_log(__CLASS__ . ': cms_users.password is narrower than ' . self::MIN_COLUMN_LENGTH
                    . ' characters. New password writes are refused. Widen the column to varchar(255).');
            }
        }
        return self::$_canStoreModernHash;
    }

    private static function _columnLength()
    {
        if (null !== self::$_columnLength) {
            return self::$_columnLength;
        }
        $row = Db::fetchAssoc(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            array(S_TABLE_USERS, 'password')
        );
        return self::$_columnLength = is_array($row) ? (int) $row['len'] : 0;
    }
}
