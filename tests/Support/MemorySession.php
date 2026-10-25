<?php

namespace Wbengine\Tests\Support;

use Wbengine\Session;

/** In-memory session storage for tests that do not exercise PHP/SQL persistence. */
class MemorySession extends Session
{
    public array $values = [];
    public int $writes = 0;

    public function __construct() {}
    public function getValue($name) { return $this->values[$name] ?? null; }
    public function setValue($name, $value) { $this->values[$name] = $value; ++$this->writes; }
    public function unsetKey($name) { unset($this->values[$name]); }
}
