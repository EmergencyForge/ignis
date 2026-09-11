<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die App-Hülle aus templates/layouts/admin.php: Topbar mit Suche und
 * Neu-Menü, gruppierte Sidebar mit aktivem Eintrag, Rechte entscheiden,
 * was davon erscheint. Root-Seite, Kern-Liste und Plugin-Seite rendern
 * hindurch; eNOTF behält seine eigene Hülle und bekommt auf seinen
 * Admin-Seiten Topbar und Sidebar über den Shim navbar.php.
 */
final class ShellTest extends FeatureTestCase
{
    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions = ['full_admin']): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    #[Test]
    public function dashboard_traegt_topbar_und_gruppierte_sidebar(): void
    {
        $this->login();

        $response = $this->get('/index');

        $this->assertOk($response);
        $this->assertSame(1, substr_count($response->body, '<!DOCTYPE html>'), 'Die Hülle steht doppelt.');
        $this->assertBodyContains('<html lang="de" data-theme="dark">', $response);
        $this->assertBodyContains('id="dashboard" class="ignis-app"', $response);
        $this->assertBodyContains('class="ignis-topbar"', $response);
        $this->assertBodyContains('data-ignis-sidebar-toggle', $response);
        $this->assertBodyContains('data-ignis-global-search', $response);
        $this->assertBodyContains('id="ignisSidebar"', $response);
        $this->assertBodyContains('<main class="ignis-main">', $response);
        $this->assertBodyContains('class="ignis-sidebar__group">Personal<', $response);
        $this->assertBodyContains('class="ignis-sidebar__group">Einstellungen<', $response);
        $this->assertBodyContains('href="/users/list"', $response);
        $this->assertBodyContains('action="/profile/theme"', $response);
        $this->assertBodyContains('href="/logout"', $response);
        $this->assertBodyContains('id="hosting-self-test"', $this->get('/dashboard'));
        $this->assertBodyNotContains('sidebar-flyout.js', $response);
        $this->assertBodyNotContains('intra-sidebar--a16', $response);
    }

    #[Test]
    public function liste_markiert_ihren_eintrag_und_bietet_schnellaktionen(): void
    {
        $this->login();

        $response = $this->get('/users/list');

        $this->assertOk($response);
        $this->assertBodyContains('<title>Benutzer &rsaquo;', $response);
        $this->assertMatchesRegularExpression('~href="/users/list"[^>]*aria-current="page"~', $response->body);
        $this->assertSame(1, substr_count($response->body, 'aria-current="page"'), 'Genau ein Eintrag ist aktiv.');
        $this->assertMatchesRegularExpression('~href="/personnel/create"[^>]*data-ignis-drawer~', $response->body);
        $this->assertBodyContains('data-quick-action-target="role-create"', $response);
        $this->assertBodyContains('ignis-topbar__new', $response);
    }

    #[Test]
    public function plugin_seite_rendert_durch_die_huelle(): void
    {
        $this->login();

        $response = $this->get('/lexicon/index');

        $this->assertOk($response);
        $this->assertSame(1, substr_count($response->body, '<!DOCTYPE html>'));
        $this->assertBodyContains('class="ignis-app"', $response);
        $this->assertMatchesRegularExpression('~href="/lexicon/index"[^>]*aria-current="page"~', $response->body);
        $this->assertMatchesRegularExpression('~href="/lexicon/create"\s+class="ignis-sidebar__quick"~', $response->body);
    }

    #[Test]
    public function rechte_bestimmen_gruppen_und_neu_menue(): void
    {
        $this->login(['calendar.view', 'calendar.create']);

        $response = $this->get('/index');

        $this->assertOk($response);
        $this->assertBodyContains('href="/calendar"', $response);
        $this->assertMatchesRegularExpression('~href="/calendar/create"[^>]*data-ignis-drawer~', $response->body);
        $this->assertBodyNotContains('class="ignis-sidebar__group">Personal<', $response);
        $this->assertBodyNotContains('href="/users/list"', $response);
        $this->assertBodyNotContains('href="/personnel/create"', $response);
    }

    #[Test]
    public function enotf_behaelt_seine_eigene_huelle(): void
    {
        $this->login();

        $response = $this->get('/enotf/login');

        $this->assertOk($response);
        $this->assertBodyContains('<!DOCTYPE html>', $response);
        $this->assertBodyNotContains('ignis-app', $response);
        $this->assertBodyNotContains('class="ignis-topbar"', $response);
    }

    #[Test]
    public function enotf_admin_seite_bekommt_die_leisten_ueber_den_shim(): void
    {
        $this->login();

        $response = $this->get('/settings/pois/index');

        $this->assertOk($response);
        $this->assertBodyContains('<!DOCTYPE html>', $response);
        $this->assertBodyContains("classList.add('ignis-app', 'ignis-app--legacy')", $response);
        $this->assertBodyContains('class="ignis-topbar"', $response);
        $this->assertBodyContains('id="ignisSidebar"', $response);
        $this->assertBodyContains('documentElement.dataset.theme = "dark"', $response);
    }
}
