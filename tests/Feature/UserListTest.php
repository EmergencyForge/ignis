<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Benutzerliste sortiert, filtert und blättert auf dem Server
 * (UserController::index mit ListQuery). Kein DataTables mehr: die
 * Kopfzellen sind Links, das Suchfeld ein GET-Formular.
 */
final class UserListTest extends FeatureTestCase
{
    private function login(): void
    {
        $user = FixtureFactory::user(['username' => 'zz_admin_' . uniqid()]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    /**
     * Position eines Textes im Body, damit sich die Reihenfolge der Zeilen prüfen lässt.
     */
    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function sortiert_nach_erlaubter_spalte_in_beide_richtungen(): void
    {
        $this->login();
        $role = FixtureFactory::role();
        FixtureFactory::user(['username' => 'aa_anton', 'role' => $role->id]);
        FixtureFactory::user(['username' => 'ab_berta', 'role' => $role->id]);

        $asc = $this->get('/users/list', ['query' => ['sort' => 'name', 'dir' => 'asc']]);
        $this->assertOk($asc);
        $this->assertLessThan($this->pos($asc->body, 'aa_anton'), $this->pos($asc->body, '<th scope="col" aria-sort="ascending">'));
        $this->assertLessThan($this->pos($asc->body, 'ab_berta'), $this->pos($asc->body, 'aa_anton'));
        $this->assertBodyNotContains('DataTable(', $asc);
        $this->assertBodyContains('class="ignis-table"', $asc);

        $desc = $this->get('/users/list', ['query' => ['sort' => 'name', 'dir' => 'desc']]);
        $this->assertLessThan($this->pos($desc->body, 'aa_anton'), $this->pos($desc->body, 'ab_berta'));
        $this->assertBodyContains('is-desc" href="/users/list">Name', $desc);
    }

    #[Test]
    public function unbekannte_spalte_faellt_auf_den_benutzernamen_zurueck(): void
    {
        $this->login();

        $response = $this->get('/users/list', ['query' => ['sort' => 'password', 'dir' => 'desc']]);

        $this->assertOk($response);
        $this->assertMatchesRegularExpression('~<th scope="col" aria-sort="ascending"><a class="ignis-table__sort is-asc" href="/users/list\?sort=name&amp;dir=desc">Name~', $response->body);
        $this->assertSame(1, substr_count($response->body, 'aria-sort="ascending"'));
    }

    #[Test]
    public function suche_und_statusfilter_grenzen_die_liste_ein(): void
    {
        $this->login();
        $role = FixtureFactory::role();
        FixtureFactory::user(['username' => 'suchtreffer_eins', 'role' => $role->id]);
        FixtureFactory::user(['username' => 'suchtreffer_zwei', 'role' => $role->id, 'is_active' => false]);
        FixtureFactory::user(['username' => 'anderer_name', 'role' => $role->id]);

        $found = $this->get('/users/list', ['query' => ['q' => 'suchtreffer']]);
        $this->assertBodyContains('suchtreffer_eins', $found);
        $this->assertBodyContains('suchtreffer_zwei', $found);
        $this->assertBodyNotContains('anderer_name', $found);
        $this->assertBodyContains('2 von 2 Benutzer', $found);
        $this->assertBodyContains('value="suchtreffer"', $found);

        $inactive = $this->get('/users/list', ['query' => ['q' => 'suchtreffer', 'status' => 'inactive']]);
        $this->assertBodyContains('suchtreffer_zwei', $inactive);
        $this->assertBodyNotContains('suchtreffer_eins', $inactive);
        $this->assertBodyContains('href="/users/list?q=suchtreffer" class="is-active"', $this->get('/users/list', ['query' => ['q' => 'suchtreffer']]));

        $wildcard = $this->get('/users/list', ['query' => ['q' => '%']]);
        $this->assertBodyContains('Keine Benutzer gefunden', $wildcard);
    }

    #[Test]
    public function seite_zwei_zeigt_die_restlichen_zeilen(): void
    {
        $this->login();
        $role = FixtureFactory::role();
        for ($i = 1; $i <= 26; $i++) {
            FixtureFactory::user(['username' => sprintf('pg_%02d', $i), 'role' => $role->id]);
        }

        $first = $this->get('/users/list', ['query' => ['q' => 'pg_']]);
        $this->assertBodyContains('pg_25', $first);
        $this->assertBodyNotContains('pg_26', $first);
        $this->assertBodyContains('1–25 von 26 Benutzer', $first);
        $this->assertBodyContains('href="/users/list?q=pg_&amp;page=2"', $first);

        $second = $this->get('/users/list', ['query' => ['q' => 'pg_', 'page' => '2']]);
        $this->assertBodyContains('pg_26', $second);
        $this->assertBodyNotContains('pg_25', $second);
        $this->assertBodyContains('26–26 von 26 Benutzer', $second);
        $this->assertBodyContains('aria-current="page">2<', $second);
    }
}
