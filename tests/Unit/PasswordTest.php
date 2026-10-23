<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\User\Password;

class PasswordTest extends TestCase
{
    public function testLegacyMd5Verifies(): void
    {
        $this->assertTrue(Password::isLegacy(md5('secret')));
        $this->assertTrue(Password::verify('secret', md5('secret')));
        $this->assertFalse(Password::verify('other', md5('secret')));
    }

    public function testModernHashVerifies(): void
    {
        $hash = password_hash("p&ss\"w'<rd>", PASSWORD_DEFAULT);

        $this->assertFalse(Password::isLegacy($hash));
        $this->assertTrue(Password::verify("p&ss\"w'<rd>", $hash));
        $this->assertFalse(Password::verify('p&ss', $hash));
    }

    public function testLegacyHashNeedsRehashModernDoesNot(): void
    {
        $this->assertTrue(Password::needsRehash(md5('x')));
        $this->assertFalse(Password::needsRehash(password_hash('x', PASSWORD_DEFAULT)));
    }

    public function testEmptyStoredHashNeverVerifies(): void
    {
        $this->assertFalse(Password::verify('', ''));
        $this->assertFalse(Password::verify('anything', '0'));
    }

    public function testDummyVerifyDoesNotThrow(): void
    {
        Password::verifyDummy('anything');
        $this->addToAssertionCount(1);
    }
}
