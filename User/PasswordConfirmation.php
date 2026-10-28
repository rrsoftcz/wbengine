<?php

namespace Wbengine\User;

use Wbengine\Config;
use Wbengine\Db;
use Wbengine\RateLimit\Limiter;

/**
 * Fresh password confirmation for sensitive actions of an already logged in user.
 *
 * The password is checked against the hash stored for the session's own user id, never
 * for a name or id taken from the form. Nothing is remembered: every sensitive request
 * has to carry the password again, so an unlocked, long running session is not enough.
 * Failures are counted per user and per client IP in the login limiter's directory.
 * The submitted password is never logged, stored or returned.
 */
class PasswordConfirmation
{
    /** Failed or pending confirmations of one user inside the window. */
    const USER_ATTEMPTS = 5;
    /** Failed confirmations from one client IP inside the window. */
    const IP_ATTEMPTS = 20;
    const MAX_LENGTH = 1024;

    private Limiter $_limiter;
    private int $_window;

    public function __construct(?Limiter $limiter = null, ?int $window = null)
    {
        if (null === $limiter || null === $window) {
            $limits = Config::getLoginRateLimit();
            $limiter = $limiter ?? new Limiter($limits['dir']);
            $window = $window ?? $limits['window'];
        }
        $this->_limiter = $limiter;
        $this->_window = $window;
    }

    /**
     * @param mixed $plain the submitted value, anything but a non empty string fails
     * @throws TooManyAttemptsException when the user or the client IP has to wait
     */
    public function confirm(int $userId, $plain, string $clientIp = ''): bool
    {
        $userKey = 'reauth:user:' . $userId;
        $ipKey = 'reauth:ip:' . $clientIp;
        $wait = max(
            $this->_limiter->retryAfter($userKey, self::USER_ATTEMPTS, $this->_window),
            $this->_limiter->retryAfter($ipKey, self::IP_ATTEMPTS, $this->_window)
        );
        if ($wait > 0) {
            throw new TooManyAttemptsException($wait);
        }
        // The attempt is counted before it is checked, so parallel guesses cannot all pass the gate.
        $this->_limiter->hit($userKey, $this->_window);

        $row = $userId > 0 ? Db::fetchAssoc('SELECT `password` FROM `' . S_TABLE_USERS . '` WHERE `user_id` = ? LIMIT 1', array($userId)) : null;
        $stored = is_array($row) ? $row['password'] : null;
        $valid = is_string($plain) && '' !== $plain && strlen($plain) <= self::MAX_LENGTH;
        if (is_string($stored) && '' !== $stored && $valid) {
            $ok = Password::verify($plain, $stored);
        } else {
            // Same work for an unknown user or an unusable input.
            Password::verifyDummy($valid ? $plain : '');
            $ok = false;
        }

        if ($ok) {
            $this->_limiter->clear($userKey);
            return true;
        }
        $this->_limiter->hit($ipKey, $this->_window);
        return false;
    }
}
