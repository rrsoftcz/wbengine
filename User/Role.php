<?php

namespace Wbengine\User;

/**
 * User roles, from the least to the most privileged.
 * The role is stored in cms_users.role and checked against the database on every
 * privileged call, so changing or removing a role takes effect immediately.
 */
class Role
{
    const USER = 'user';
    const EDITOR = 'editor';
    const ADMIN = 'admin';

    private const RANK = array(
        self::USER => 1,
        self::EDITOR => 2,
        self::ADMIN => 3,
    );

    public static function all()
    {
        return array_keys(self::RANK);
    }

    public static function isValid($role)
    {
        return is_string($role) && isset(self::RANK[$role]);
    }

    /**
     * Whether $actual is at least as privileged as $required. Unknown roles never are.
     */
    public static function atLeast($actual, $required)
    {
        return self::isValid($actual) && self::isValid($required)
            && self::RANK[$actual] >= self::RANK[$required];
    }
}
