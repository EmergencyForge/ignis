<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormType;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Das Dashboard (index.php) ist die Übersicht „ignis im Dienst“ aus der
 * Spec zu ui 0.7.0: Kennzahl-Kacheln mit der Alarmkachel für offene
 * eNOTF-Protokolle, die Einsätze als Schale mit Sparklines, die Fahrzeuge
 * als Strichskala mit Status-Zeilen und rechts die Hinweise. Alles nur mit
 * Recht und echten Daten (App\Support\Overview). Darunter stehen die
 * eigenen Dokumente und Anträge als Tabellenkarten, die Listen der Plugins
 * nur bei aktivem Plugin. Die Schnellzugriffe (dashboard.php) haben den
 * Seitenkopf der übrigen Seiten und den Hosting-Hinweis als versteckten
 * Alert, den hosting-self-test.js einblendet.
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

    /** Nur die Daten dieses Tests zählen, die Transaktion rollt alles zurück. */
    private function onlyTheseRows(): void
    {
        Capsule::table('intra_fahrzeuge')->update(['active' => 0]);
        Capsule::table('intra_edivi')->update(['hidden' => 1]);
        if (Capsule::schema()->hasTable('intra_fire_incidents')) {
            Capsule::table('intra_fire_incidents')->update(['archived' => 1]);
        }
    }

    private function vehicle(string $name, ?string $status, string $since = '-10 minutes'): void
    {
        Capsule::table('intra_fahrzeuge')->insert([
            'name' => $name, 'identifier' => strtolower(str_replace([' ', '/'], '_', $name)) . uniqid(), 'veh_type' => 'RTW', 'rd_type' => 2,
            'priority' => 1, 'active' => 1, 'current_status' => $status,
            'status_updated_at' => $status !== null ? date('Y-m-d H:i:s', (int) strtotime($since)) : null,
        ]);
    }

    private function protocol(bool $released, string $when = 'now'): void
    {
        Capsule::table('intra_edivi')->insert([
            'enr' => 'DASH-' . uniqid(), 'patname' => 'P', 'protokoll_status' => 0, 'hidden' => 0, 'hidden_user' => 0,
            'freigegeben' => $released ? 1 : 0, 'sendezeit' => date('Y-m-d H:i:s', (int) strtotime($when)),
        ]);
    }

    #[Test]
    public function uebersicht_zeigt_kacheln_einsaetze_fahrzeuge_und_hinweise_aus_den_daten(): void
    {
        $this->login();
        $this->onlyTheseRows();
        $this->vehicle('RTW 1/83-1', '2');
        $this->vehicle('RTW 1/83-2', '6', '-40 minutes');
        $this->vehicle('MTF 1/19-1', null);
        $this->protocol(false);
        $this->protocol(true);
        $this->protocol(true);

        $body = $this->get('/index')->body;

        // Kacheln: drei Einsätze heute, eins von zwei Fahrzeugen mit Status bereit, ein Protokoll offen.
        $this->assertStringContainsString('<div class="ignis-kpis">', $body);
        $this->assertMatchesRegularExpression('~Einsätze heute</span>.*?<div class="ignis-kpi__value"><span data-ignis-count>3</span>~s', $body);
        $this->assertMatchesRegularExpression('~Fahrzeuge einsatzbereit</span>.*?<span data-ignis-count>1</span> <span class="ignis-unit">von 2</span>~s', $body);
        $this->assertStringContainsString('1 ohne Statusmeldung', $body);
        $this->assertMatchesRegularExpression('~<a class="ignis-kpi" href="/enotf/admin/list\?view=2" data-tone="danger" data-ignis-reveal>.*?fa-triangle-exclamation.*?eNOTF-Protokolle offen</span>.*?<span data-ignis-count>1</span>~s', $body);

        // Delta gegen gestern: die Richtung als Text, der Pfeil nur als Bild.
        $this->assertStringContainsString('<span class="ignis-delta"><span class="ignis-sr-only">plus </span><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>3</span><span class="ignis-sr-only"> ggü. gestern um diese Zeit</span>', $body);

        // Einsätze: Sparklines aus echten Zählungen, der letzte Tag ist heute.
        $this->assertMatchesRegularExpression('~data-ignis-spark data-values="[\d,]*3" data-label="Einsätze je Tag in den letzten sieben Tagen"~', $body);
        $this->assertStringContainsString('assets/js/ui/spark.js', $body);

        // Fahrzeuge: Strichskala mit einem Strich je Fahrzeug, Status als getönter Chip.
        $this->assertStringContainsString('role="meter" aria-valuenow="1" aria-valuemin="0" aria-valuemax="2" aria-label="Fahrzeuge einsatzbereit, 1 von 2"', $body);
        $this->assertSame(1, substr_count($body, 'ignis-meter__tick is-on'));
        $this->assertMatchesRegularExpression('~<div class="ignis-bezel__item ignis-entry">\s*<span class="ignis-glyph ignis-glyph--sm" aria-hidden="true">~', $body);
        $this->assertStringContainsString('data-tone="ok"><b>2</b> Einsatzbereit Wache</span>', $body);
        $this->assertStringContainsString('<span class="ignis-chip ignis-chip--sm"><i class="fa-solid fa-ban" aria-hidden="true"></i><b>6</b> Nicht einsatzbereit</span>', $body);

        // Hinweise: Regeln aus den Daten, rechts neben der Hauptspalte.
        $this->assertStringContainsString('<div class="ignis-dash">', $body);
        $this->assertStringContainsString('<section class="ignis-bezel ignis-hints"', $body);
        $this->assertStringContainsString('Ein eNOTF-Protokoll nicht freigegeben', $body);
        $this->assertStringContainsString('aria-label="67 % der Protokolle dieser Woche freigegeben"', $body);
        $this->assertMatchesRegularExpression('~<span class="ignis-mono">RTW 1/83-2</span> seit 40 min Status 6~', $body);
        $this->assertStringNotContainsString('Hilfsfrist', $body);
        $this->assertStringNotContainsString('data-ignis-enter', $body);
    }

    #[Test]
    public function alarmkachel_ist_bei_null_eine_ruhige_kachel_und_ohne_status_fehlen_die_fahrzeuge(): void
    {
        $this->login();
        $this->onlyTheseRows();
        $this->vehicle('RTW 1/83-1', null);
        $this->protocol(true);

        $body = $this->get('/index')->body;

        $this->assertMatchesRegularExpression('~<a class="ignis-kpi" href="/enotf/admin/list\?view=2" data-tone="ok" data-ignis-reveal>.*?fa-circle-check.*?alle freigegeben~s', $body);
        $this->assertStringNotContainsString('Fahrzeuge einsatzbereit', $body);
        $this->assertStringNotContainsString('role="meter"', $body);
        $this->assertStringContainsString('Nichts wartet auf dich', $body);
    }

    #[Test]
    public function ohne_recht_auf_aufgaben_steht_der_ruhige_hinweis(): void
    {
        $this->login(['vehicles.view']);
        $this->onlyTheseRows();
        $this->vehicle('RTW 1/83-1', '2');

        $body = $this->get('/index')->body;

        $this->assertStringContainsString('<section class="ignis-bezel ignis-hints"', $body);
        $this->assertStringContainsString('Nichts wartet auf dich', $body);
    }

    #[Test]
    public function eigene_antraege_stehen_als_tabellenkarte(): void
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
        $this->assertBodyContains('id="dashboard-documents-title">Eigene Dokumente', $page);
        $this->assertBodyContains('<section class="ignis-card ignis-card--table" data-ignis-reveal data-section="applications"', $page);
        $this->assertBodyContains('id="dashboard-applications-title">Eigene Anträge', $page);
        $this->assertBodyContains('<table class="ignis-table" id="dashboardApplications">', $page);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--dot ignis-chip--warn">Aufgeschoben</span>', $page);
        $this->assertBodyContains('href="/forms/view?antrag=D0000001"', $page);
        $this->assertBodyContains('Kein Mitarbeiterprofil verknüpft', $page);
        $this->assertBodyNotContains('table-striped', $page);
        $this->assertBodyNotContains('empty-state', $page);
    }

    #[Test]
    public function ohne_recht_entfallen_kacheln_schalen_und_hinweise(): void
    {
        $this->login(['calendar.view']);

        $page = $this->get('/index');

        $this->assertOk($page);
        $this->assertBodyNotContains('ignis-kpis', $page);
        $this->assertBodyNotContains('ignis-bezel', $page);
        $this->assertBodyNotContains('ignis-hints', $page);
        $this->assertBodyNotContains('assets/js/ui/spark.js', $page);
        $this->assertBodyNotContains('<div class="ignis-dash">', $page);
        $this->assertBodyContains('Noch keine Anträge', $page);
    }

    #[Test]
    public function schnellzugriffe_haben_seitenkopf_und_versteckten_hosting_hinweis(): void
    {
        $this->login();

        $page = $this->get('/dashboard');

        $this->assertOk($page);
        $this->assertBodyContains('<title>Schnellzugriffe', $page);
        $this->assertBodyContains('<span class="ignis-breadcrumb__item" aria-current="page">Schnellzugriffe</span>', $page);
        $this->assertBodyContains('href="/settings/dashboard/index" class="ignis-btn ignis-btn--secondary"', $page);
        $this->assertMatchesRegularExpression('~<div\s+id="hosting-self-test"\s+class="ignis-alert ignis-alert--warn mb-4"[^>]*\shidden~', $page->body);
        $this->assertBodyNotContains('alert-warning', $page);
        $this->assertBodyNotContains('d-none', $page);
    }

    /** @param array<string, string> $values */
    private function setConfig(array $values): void
    {
        foreach ($values as $key => $value) {
            Capsule::table('intra_config')->where('config_key', $key)->update(['config_value' => $value, 'is_editable' => 1]);
        }
        (new \ReflectionProperty(\App\Config\ConfigManager::class, 'configCache'))->setValue(null, null);
    }

    /** Der Schritt Systemdaten nennt die offenen Felder und führt in den Einrichtungsmodus. */
    #[Test]
    public function der_systemdaten_schritt_nennt_die_offenen_felder(): void
    {
        $this->setConfig(['SYSTEM_URL' => 'CHANGE_ME', 'SERVER_NAME' => '']);
        $this->login();

        $page = $this->get('/index');

        $this->assertBodyContains('<span>Systemdaten anpassen<span class="ignis-empty__step-note">Noch offen: System-URL, Servername</span></span>', $page);
        $this->assertBodyContains('href="/settings/system/config?setup=1">Jetzt einrichten</a>', $page);
    }

    #[Test]
    public function mit_url_und_servername_ist_der_schritt_erledigt(): void
    {
        $this->setConfig(['SYSTEM_URL' => 'intra.example.de', 'SERVER_NAME' => 'Rheinstadt RP']);
        $this->login();

        $page = $this->get('/index');

        $this->assertBodyNotContains('ignis-empty__step-note', $page);
        $this->assertBodyNotContains('href="/settings/system/config?setup=1"', $page);
    }
}
