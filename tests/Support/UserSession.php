<?php

namespace Wbengine\Tests\Support;

/** MemorySession that also knows which user it belongs to, as a real logged in session does. */
class UserSession extends MemorySession
{
    public function __construct(public int $userId = 0)
    {
        $this->values['user_is_logged'] = $userId > 0;
    }

    public function getUserId() { return $this->userId; }
}
