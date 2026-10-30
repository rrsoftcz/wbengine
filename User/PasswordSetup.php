<?php

namespace Wbengine\User;

use Wbengine\Db;

/**
 * One-time links to set a password: used for accounts created through the API and for resets.
 * Only the SHA-256 of the token is stored. A token works once, for 48 hours, and a new one
 * replaces every unused earlier token of the same account.
 */
final class PasswordSetup
{
    const TTL = 172800;
    const MIN_LENGTH = 12;
    const MAX_LENGTH = 256;

    /** @return array{token:string,expires_at:int} the token is shown once and never stored */
    public static function issue(int $userId, ?int $now = null): array
    {
        $now ??= time();
        $token = bin2hex(random_bytes(32));
        Db::query('UPDATE cms_password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', array($now, $userId));
        Db::insert('cms_password_resets', array(
            'token_hash' => hash('sha256', $token), 'user_id' => $userId, 'created_at' => $now, 'expires_at' => $now + self::TTL,
        ));
        return array('token' => $token, 'expires_at' => $now + self::TTL);
    }

    /** True for a well-formed token that is unused and not expired. */
    public static function isValid(string $token, ?int $now = null): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { return false; }
        $row = Db::fetchAssoc('SELECT `user_id` FROM cms_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ? LIMIT 1;', array(hash('sha256', $token), $now ?? time()));
        return is_array($row);
    }

    /**
     * Sets the password and ends every session of the account. The token is claimed first with one
     * conditional UPDATE, so two simultaneous submissions cannot both succeed.
     *
     * @throws UserException for a weak password or a token that is not valid
     */
    public static function consume(string $token, string $password, ?int $now = null): int
    {
        $length = mb_strlen($password);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new UserException(sprintf('The password must have %d to %d characters.', self::MIN_LENGTH, self::MAX_LENGTH));
        }
        $now ??= time();
        $hash = preg_match('/^[a-f0-9]{64}$/D', $token) ? hash('sha256', $token) : '';
        $row = '' === $hash ? null : Db::fetchAssoc('SELECT `user_id` FROM cms_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ? LIMIT 1;', array($hash, $now));
        if (!is_array($row)) { throw new UserException('The link is not valid or has expired.'); }
        $userId = (int) $row['user_id'];
        $newHash = Password::hash($password);
        Db::query('UPDATE cms_password_resets SET used_at = ? WHERE token_hash = ? AND used_at IS NULL', array($now, $hash));
        if (1 !== Db::getAffected()) { throw new UserException('The link is not valid or has expired.'); }
        Db::update(S_TABLE_USERS, array('password' => $newHash), array('user_id' => $userId));
        return $userId;
    }
}
