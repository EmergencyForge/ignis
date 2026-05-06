<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Form;
use App\Policies\FormsPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormsPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    /**
     * Hilfsmethode: Setzt eine vereinfachte User-Session mit einer
     * konkreten Permission-Liste. Permissions::check liest aus
     * \$_SESSION['permissions'] — daher reicht das hier.
     */
    private function loginWith(array $permissions, ?string $discordtag = null): void
    {
        $_SESSION['userid']      = 42;
        $_SESSION['permissions'] = $permissions;
        if ($discordtag !== null) {
            $_SESSION['discordtag'] = $discordtag;
        }
    }

    private function makeAntrag(?string $discordid): Form
    {
        $a = new Form();
        $a->discordid = $discordid;
        return $a;
    }

    // ─── viewAny ─────────────────────────────────────────────────────

    #[Test]
    public function view_any_requires_application_edit_permission(): void
    {
        $this->loginWith(['user']);
        $this->assertFalse(FormsPolicy::viewAny());

        $this->loginWith(['application.edit']);
        $this->assertTrue(FormsPolicy::viewAny());
    }

    #[Test]
    public function view_any_admin_pass_through(): void
    {
        $this->loginWith(['admin']);
        $this->assertTrue(FormsPolicy::viewAny());
    }

    // ─── view ────────────────────────────────────────────────────────

    #[Test]
    public function view_returns_true_for_application_view_permission(): void
    {
        $this->loginWith(['application.view']);
        $a = $this->makeAntrag('1234567890');
        $this->assertTrue(FormsPolicy::view($a));
    }

    #[Test]
    public function view_returns_true_for_own_antrag_via_discord_tag(): void
    {
        $this->loginWith(['user'], discordtag: '1234567890');
        $a = $this->makeAntrag('1234567890');
        $this->assertTrue(FormsPolicy::view($a));
    }

    #[Test]
    public function view_returns_false_for_other_users_antrag(): void
    {
        $this->loginWith(['user'], discordtag: 'me-tag');
        $a = $this->makeAntrag('other-tag');
        $this->assertFalse(FormsPolicy::view($a));
    }

    #[Test]
    public function view_with_null_target_falls_back_to_global_permission(): void
    {
        $this->loginWith(['user']);
        // Ohne Target und ohne application.view → false
        $this->assertFalse(FormsPolicy::view(null));

        $this->loginWith(['application.view']);
        $this->assertTrue(FormsPolicy::view(null));
    }

    #[Test]
    public function view_rejects_empty_discord_tag_match(): void
    {
        // Edge-Case: User ohne discordtag, Antrag mit leerem discordid → kein Match
        $_SESSION['userid']      = 42;
        $_SESSION['permissions'] = ['user'];
        $_SESSION['discordtag']  = '';
        $a = $this->makeAntrag('');
        $this->assertFalse(FormsPolicy::view($a));
    }

    // ─── create ──────────────────────────────────────────────────────

    #[Test]
    public function create_returns_true_for_any_logged_in_user(): void
    {
        $this->loginWith([]);
        $this->assertTrue(FormsPolicy::create());
    }

    #[Test]
    public function create_returns_false_when_not_logged_in(): void
    {
        $this->assertFalse(FormsPolicy::create());
    }

    // ─── decide ──────────────────────────────────────────────────────

    #[Test]
    public function decide_requires_application_edit_permission(): void
    {
        $this->loginWith(['application.view']);
        $this->assertFalse(FormsPolicy::decide());

        $this->loginWith(['application.edit']);
        $this->assertTrue(FormsPolicy::decide());
    }
}
