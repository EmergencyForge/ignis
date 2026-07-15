<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use Plugin\Enotf\Policies\EnotfPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnotfPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset session
        $_SESSION = [];
    }

    #[Test]
    public function has_crew_session_returns_false_when_session_empty(): void
    {
        $this->assertFalse(EnotfPolicy::hasCrewSession());
    }

    #[Test]
    public function has_crew_session_returns_true_when_fahrername_and_protfzg_set(): void
    {
        $_SESSION['fahrername'] = 'Max Mustermann';
        $_SESSION['protfzg']    = 'RTW-1';

        $this->assertTrue(EnotfPolicy::hasCrewSession());
    }

    #[Test]
    public function has_klinik_access_returns_false_when_no_session_keys(): void
    {
        $this->assertFalse(EnotfPolicy::hasKlinikAccess());
    }

    #[Test]
    public function has_klinik_access_returns_true_within_ttl(): void
    {
        $_SESSION['klinik_access_enr']  = 'ENR123';
        $_SESSION['klinik_access_time'] = time() - 60; // 1 minute ago

        $this->assertTrue(EnotfPolicy::hasKlinikAccess());
    }

    #[Test]
    public function has_klinik_access_returns_false_after_ttl(): void
    {
        $_SESSION['klinik_access_enr']  = 'ENR123';
        $_SESSION['klinik_access_time'] = time() - EnotfPolicy::KLINIK_ACCESS_TTL - 60;

        $this->assertFalse(EnotfPolicy::hasKlinikAccess());
    }

    #[Test]
    public function pin_verified_returns_true_within_timeout(): void
    {
        if (!defined('ENOTF_USE_PIN')) define('ENOTF_USE_PIN', true);
        // Skip if PIN is disabled in this env
        if (ENOTF_USE_PIN !== true) {
            $this->markTestSkipped('ENOTF_USE_PIN not enabled');
        }

        $_SESSION['userid']            = null; // not exempt
        $_SESSION['pin_verified']      = true;
        $_SESSION['pin_last_activity'] = time() - 60;

        // Either pin exempt (admin) or correctly verified
        $this->assertTrue(EnotfPolicy::pinVerified() || EnotfPolicy::pinExempt());
    }

    #[Test]
    public function passed_user_auth_gate_returns_true_when_no_gate_active(): void
    {
        // Default: ENOTF_REQUIRE_USER_AUTH not defined
        if (!defined('ENOTF_REQUIRE_USER_AUTH')) {
            $this->assertTrue(EnotfPolicy::passedUserAuthGate());
        } else {
            $this->markTestSkipped('ENOTF_REQUIRE_USER_AUTH already defined');
        }
    }
}
