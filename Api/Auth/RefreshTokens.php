<?php

namespace Wbengine\Api\Auth;

use Wbengine\Api\Auth\Exception\InvalidRefreshTokenException;
use Wbengine\Db;

/**
 * Server side refresh tokens.
 *
 * A refresh token is an opaque random string. Only its SHA-256 hash is stored, so a
 * database leak does not leak usable tokens. Tokens are single use: every refresh
 * issues a new token in the same family and marks the old one as used. Presenting a
 * used token again means it was copied, so the whole family is revoked and the real
 * owner and the thief are both logged out. A short grace period covers two browser
 * tabs refreshing at the same moment.
 *
 * Table: cms_refresh_tokens (see schemas/migrations in the skeleton application).
 */
class RefreshTokens
{
    /** Seconds in which a second use of a just rotated token is refused without revoking. */
    const REUSE_GRACE = 10;

    private $_clock;

    /**
     * @param callable|null $clock returns the current unix time, for tests
     */
    public function __construct(?callable $clock = null)
    {
        $this->_clock = $clock ?? 'time';
    }

    private function _now()
    {
        return (int) call_user_func($this->_clock);
    }

    public static function hash($token)
    {
        return hash('sha256', (string) $token);
    }

    /**
     * Create a token. A new family is started unless $familyId is given.
     *
     * @return string the raw token, to be sent to the client once and never stored
     */
    public function issue($userId, $ttl, $familyId = null, $ip = '', $userAgent = '')
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = $this->_now();

        Db::insert(S_TABLE_REFRESH_TOKENS, array(
            'user_id' => (int) $userId,
            'family_id' => $familyId ?? bin2hex(random_bytes(16)),
            'token_hash' => self::hash($token),
            'expires_at' => $now + max(1, (int) $ttl),
            'created_at' => $now,
            'ip' => substr((string) $ip, 0, 45),
            'user_agent' => substr((string) $userAgent, 0, 255),
        ));

        $this->_purge($now);
        return $token;
    }

    /**
     * Exchange a token for a new one of the same family.
     *
     * @return array user_id and token (the new raw token)
     * @throws InvalidRefreshTokenException
     */
    public function rotate($token, $ttl, $ip = '', $userAgent = '')
    {
        $row = $this->_find($token);
        if (null === $row) {
            throw new InvalidRefreshTokenException('unknown');
        }

        $now = $this->_now();
        if (null !== $row['revoked_at']) {
            throw new InvalidRefreshTokenException('revoked');
        }
        if ((int) $row['expires_at'] <= $now) {
            throw new InvalidRefreshTokenException('expired');
        }
        if (null !== $row['used_at']) {
            if ($now - (int) $row['used_at'] > self::REUSE_GRACE) {
                $this->_revokeFamily($row['family_id'], $now);
                throw new InvalidRefreshTokenException('reused: family revoked');
            }
            throw new InvalidRefreshTokenException('reused within the grace period');
        }

        $user = Db::fetchAssoc(
            sprintf('SELECT `user_id` FROM %s WHERE `user_id` = ? AND `user_id` > 0 LIMIT 1', S_TABLE_USERS),
            array((int) $row['user_id'])
        );
        if (!is_array($user)) {
            $this->_revokeFamily($row['family_id'], $now);
            throw new InvalidRefreshTokenException('user no longer exists');
        }

        // Claim the token. Of two simultaneous requests only one changes the row.
        Db::query(
            sprintf('UPDATE %s SET `used_at` = ? WHERE `id` = ? AND `used_at` IS NULL AND `revoked_at` IS NULL', S_TABLE_REFRESH_TOKENS),
            array($now, (int) $row['id'])
        );
        if (1 !== Db::getAffected()) {
            throw new InvalidRefreshTokenException('claimed by another request');
        }

        return array(
            'user_id' => (int) $row['user_id'],
            'token' => $this->issue((int) $row['user_id'], $ttl, $row['family_id'], $ip, $userAgent),
        );
    }

    /**
     * Log out one device: revoke the family of the presented token.
     * An unknown token is ignored, logging out must always work.
     */
    public function revoke($token)
    {
        $row = $this->_find($token);
        if (null !== $row) {
            $this->_revokeFamily($row['family_id'], $this->_now());
        }
    }

    /**
     * Log out everywhere: revoke every token of the user.
     *
     * @return int number of tokens revoked
     */
    public function revokeAllForUser($userId)
    {
        Db::query(
            sprintf('UPDATE %s SET `revoked_at` = ? WHERE `user_id` = ? AND `revoked_at` IS NULL', S_TABLE_REFRESH_TOKENS),
            array($this->_now(), (int) $userId)
        );
        return (int) Db::getAffected();
    }

    private function _find($token)
    {
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{20,128}$/', $token)) {
            return null;
        }
        $row = Db::fetchAssoc(
            sprintf('SELECT * FROM %s WHERE `token_hash` = ? LIMIT 1', S_TABLE_REFRESH_TOKENS),
            array(self::hash($token))
        );
        return is_array($row) ? $row : null;
    }

    private function _revokeFamily($familyId, $now)
    {
        Db::query(
            sprintf('UPDATE %s SET `revoked_at` = ? WHERE `family_id` = ? AND `revoked_at` IS NULL', S_TABLE_REFRESH_TOKENS),
            array($now, $familyId)
        );
    }

    /**
     * Now and then delete tokens that expired more than a day ago.
     */
    private function _purge($now)
    {
        if (random_int(1, 50) === 1) {
            Db::query(sprintf('DELETE FROM %s WHERE `expires_at` < ?', S_TABLE_REFRESH_TOKENS), array($now - 86400));
        }
    }
}
