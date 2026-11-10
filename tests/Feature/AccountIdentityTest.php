<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Rank;
use App\Personnel\AccountLink;
use App\Support\AccountIdentity;
use App\Support\DeskShell;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Name und Bild oben rechts: mit verknüpftem Mitarbeiter dessen Name, Bild
 * und Dienstgrad, sonst der Benutzername. Kopfleiste und efOS lesen beide
 * App\Support\AccountIdentity.
 */
final class AccountIdentityTest extends FeatureTestCase
{
    private function login(?int $aktenid): string
    {
        $user = FixtureFactory::user(['username' => 'funker_' . uniqid(), 'aktenid' => $aktenid]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        AccountLink::forget();

        return $user->username;
    }

    #[Test]
    public function ohne_mitarbeiter_bleibt_der_benutzername(): void
    {
        $username = $this->login(null);

        $body = $this->get('/index')->body;

        $this->assertStringContainsString('<span class="ignis-topbar__avatar" aria-hidden="true">FU</span>', $body);
        $this->assertStringContainsString('<span class="ignis-topbar__username">' . $username . '</span>', $body);
        $this->assertStringContainsString('ignis-menu__role-dot', $body);
        $this->assertSame(['name' => $username, 'initials' => 'FU', 'photo' => null, 'rank' => null], AccountIdentity::current());
        $this->assertSame($username, DeskShell::config()['user']['name']);
    }

    #[Test]
    public function mitarbeiter_mit_bild_zeigt_name_bild_und_dienstgrad(): void
    {
        $person = FixtureFactory::personnel(['fullname' => 'Erika Beispiel', 'geschlecht' => 1, 'pfp' => '/storage/profile-pictures/erika.png']);
        $rank = Rank::query()->findOrFail($person->dienstgrad)->displayName(1);
        $this->login($person->id);

        $body = $this->get('/index')->body;

        $this->assertStringContainsString('<span class="ignis-topbar__avatar" aria-hidden="true"><img src="/storage/profile-pictures/erika.png" alt=""></span>', $body);
        $this->assertStringContainsString('<span class="ignis-topbar__username">Erika Beispiel</span>', $body);
        $this->assertStringContainsString('<span class="ignis-menu__identity-role">' . htmlspecialchars($rank) . '</span>', $body);
        $this->assertSame('Erika Beispiel', DeskShell::config()['user']['name']);
        $this->assertSame('EB', DeskShell::config()['user']['initials']);
    }

    #[Test]
    public function mitarbeiter_ohne_bild_zeigt_seine_initialen(): void
    {
        // Die Personalakte trägt ohne eigenes Bild den Platzhalter ein.
        $person = FixtureFactory::personnel(['fullname' => 'Max Muster', 'pfp' => '/assets/img/empty_user.png']);
        $this->login($person->id);

        $body = $this->get('/index')->body;

        $this->assertStringContainsString('<span class="ignis-topbar__avatar" aria-hidden="true">MM</span>', $body);
        $this->assertStringContainsString('<span class="ignis-topbar__username">Max Muster</span>', $body);
        $this->assertStringNotContainsString('empty_user.png" alt=""', $body);
        $this->assertNull(AccountIdentity::current()['photo']);
    }
}
