<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormType;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Das Dashboard (index.php) zeigt die Kennzahlen als Kacheln, die zur
 * jeweiligen Liste führen, wenn das Recht dafür da ist, und darunter die
 * eigenen Dokumente und Anträge als ignis-Tabellen; die Listen der
 * Plugins nur bei aktivem Plugin. Die Schnellzugriffe (dashboard.php)
 * haben den Seitenkopf der übrigen Seiten und den Hosting-Hinweis als
 * versteckten Alert, den hosting-self-test.js einblendet.
 */
final class DashboardTest extends FeatureTestCase
{
    private string $discordId = '';

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions = ['full_admin']): void
    {
        $user = FixtureFactory::user();
        $this->discordId = (string) $user->discord_id;
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username, 'discordtag' => $this->discordId]);
    }

    #[Test]
    public function kennzahlen_verlinken_auf_die_listen_und_die_eigenen_antraege_stehen_als_tabelle(): void
    {
        $this->login();

        $typ = new FormType();
        $typ->name  = 'Urlaub_' . uniqid();
        $typ->aktiv = true;
        $typ->save();

        $antrag = new Form();
        $antrag->uniqueid      = 'D0000001';
        $antrag->antragstyp_id = $typ->id;
        $antrag->discordid     = $this->discordId;
        $antrag->name_dn       = 'Dana Dashboard';
        $antrag->cirs_status   = Form::STATUS_DEFERRED;
        $antrag->time_added    = new \DateTime('2026-03-01 10:00:00');
        $antrag->save();

        $page = $this->get('/index');

        $this->assertOk($page);
        $this->assertBodyContains('<a href="/users/list" class="twplus-stats__item">', $page);
        $this->assertBodyContains('<a href="/personnel/list" class="twplus-stats__item">', $page);
        $this->assertBodyContains('id="dashboard-documents-title">Eigene Dokumente', $page);
        $this->assertBodyContains('id="dashboard-applications-title">Eigene Anträge', $page);
        $this->assertBodyContains('<table class="ignis-table" id="dashboardApplications">', $page);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--dot ignis-chip--warn">Aufgeschoben</span>', $page);
        $this->assertBodyContains('href="/forms/view?antrag=D0000001"', $page);
        $this->assertBodyContains('Kein Mitarbeiterprofil verknüpft', $page);
        $this->assertBodyNotContains('table-striped', $page);
        $this->assertBodyNotContains('empty-state', $page);
    }

    #[Test]
    public function ohne_recht_bleibt_die_kennzahl_eine_zahl(): void
    {
        $this->login(['calendar.view']);

        $page = $this->get('/index');

        $this->assertOk($page);
        $this->assertBodyNotContains('href="/users/list" class="twplus-stats__item"', $page);
        $this->assertBodyContains('<div class="twplus-stats__item">', $page);
        $this->assertBodyContains('Noch keine Anträge', $page);
    }

    #[Test]
    public function schnellzugriffe_haben_seitenkopf_und_versteckten_hosting_hinweis(): void
    {
        $this->login();

        $page = $this->get('/dashboard');

        $this->assertOk($page);
        $this->assertBodyContains('<title>Schnellzugriffe', $page);
        $this->assertBodyContains('<span class="ignis-breadcrumb__item is-active">Schnellzugriffe</span>', $page);
        $this->assertBodyContains('href="/settings/dashboard/index" class="ignis-btn ignis-btn--secondary"', $page);
        $this->assertMatchesRegularExpression('~<div\s+id="hosting-self-test"\s+class="ignis-alert ignis-alert--warn mb-4"[^>]*\shidden~', $page->body);
        $this->assertBodyNotContains('alert-warning', $page);
        $this->assertBodyNotContains('d-none', $page);
    }
}
