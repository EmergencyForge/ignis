<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Mit `X-Requested-With: fragment` liefert Layout::render() nur den Inhalt
 * einer Ansicht, verpackt in .ignis-fragment mit dem Seitentitel; der
 * Drawer (assets/js/ui/drawer-form.js) lädt so die Anlage-Formulare für
 * Fahrzeug, Mangel, Termin und Mitarbeiter. Ohne den Header kommt die
 * ganze Seite mit Hülle. Ein Formular-Post mit ungültiger Eingabe leitet
 * zurück aufs Formular: für Fragment-Aufrufer als 200 mit X-Ignis-Location
 * (RouterFactory), sonst als 302. Listen und Neu-Menü tragen die Links
 * mit data-ignis-drawer.
 */
final class FragmentRenderTest extends FeatureTestCase
{
    private const FRAGMENT = ['headers' => ['X-Requested-With' => 'fragment']];

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions = ['full_admin']): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    #[Test]
    public function fragment_laesst_die_huelle_weg_und_traegt_den_titel(): void
    {
        $this->login();

        $response = $this->get('/settings/vehicles/vehicles/create', self::FRAGMENT);

        $this->assertOk($response);
        $this->assertStringStartsWith('<div class="ignis-fragment" data-title="Fahrzeug anlegen">', $response->body);
        $this->assertBodyNotContains('<html', $response);
        $this->assertBodyNotContains('ignis-topbar', $response);
        $this->assertBodyContains('name="identifier"', $response);
        $this->assertBodyContains('action="/settings/vehicles/vehicles/create"', $response);
    }

    #[Test]
    public function ohne_header_kommt_die_ganze_seite(): void
    {
        $this->login();

        $response = $this->get('/settings/vehicles/vehicles/create');

        $this->assertOk($response);
        $this->assertBodyContains('<html lang="de"', $response);
        $this->assertBodyContains('class="ignis-topbar"', $response);
        $this->assertBodyContains('<title>Fahrzeug anlegen &rsaquo;', $response);
        $this->assertBodyNotContains('class="ignis-fragment"', $response);
    }

    #[Test]
    public function alle_vier_formulare_kommen_als_fragment(): void
    {
        $this->login();

        foreach ([
            '/settings/vehicles/defects/create' => ['Mangel melden', 'name="vehicle_id"'],
            '/calendar/create'                  => ['Termin anlegen', 'data-calendar-event-form'],
            '/personnel/create'                 => ['Mitarbeiter anlegen', 'name="dienstnr"'],
        ] as $path => [$title, $field]) {
            $response = $this->get($path, self::FRAGMENT);
            $this->assertOk($response);
            $this->assertStringStartsWith('<div class="ignis-fragment" data-title="' . $title . '">', $response->body, $path);
            $this->assertBodyNotContains('<html', $response);
            $this->assertBodyContains($field, $response);
        }
    }

    #[Test]
    public function fragment_post_mit_ungueltiger_eingabe_bekommt_das_ziel_als_header(): void
    {
        $this->login();

        $response = $this->post('/settings/vehicles/vehicles/create', ['name' => '', 'identifier' => ''], self::FRAGMENT);

        $this->assertOk($response);
        $this->assertSame('/settings/vehicles/vehicles/create', $response->headers['X-Ignis-Location'] ?? null);
        $this->assertArrayNotHasKey('Location', $response->headers);
        $this->assertSame('', $response->body);
    }

    #[Test]
    public function ohne_header_bleibt_der_ungueltige_post_ein_redirect(): void
    {
        $this->login();

        $response = $this->post('/settings/vehicles/vehicles/create', ['name' => '', 'identifier' => '']);

        $this->assertRedirect($response, '/settings/vehicles/vehicles/create');
        $this->assertArrayNotHasKey('X-Ignis-Location', $response->headers);
    }

    #[Test]
    public function das_formular_kommt_mit_eingabe_und_meldung_zurueck(): void
    {
        $this->login();

        $this->post('/settings/vehicles/vehicles/create', ['name' => 'Florian Test 1', 'kennzeichen' => 'LS-T 1', 'identifier' => ''], self::FRAGMENT);
        $again = $this->get('/settings/vehicles/vehicles/create', self::FRAGMENT);

        $this->assertBodyContains('value="Florian Test 1"', $again);
        $this->assertBodyContains('data-ignis-flash data-variant="danger"', $again);
        // Der Bag ist verbraucht: beim nächsten Aufruf ist das Formular leer.
        $this->assertBodyNotContains('Florian Test 1', $this->get('/settings/vehicles/vehicles/create', self::FRAGMENT));
    }

    #[Test]
    public function termin_und_mitarbeiter_und_mangel_leiten_bei_fehlern_auf_ihr_formular(): void
    {
        $this->login();

        $this->assertRedirect($this->post('/calendar/create', ['title' => '']), '/calendar/create');
        $this->assertRedirect($this->post('/personnel/create', ['fullname' => '']), '/personnel/create');
        $this->assertRedirect($this->post('/settings/vehicles/defects/create', ['title' => '']), '/settings/vehicles/defects/create');

        $calendar = $this->post('/calendar/create', ['title' => ''], self::FRAGMENT);
        $this->assertOk($calendar);
        $this->assertSame('/calendar/create', $calendar->headers['X-Ignis-Location'] ?? null);
    }

    #[Test]
    public function erfolg_leitet_zur_zielseite_und_der_drawer_bekommt_das_ziel(): void
    {
        $this->login();
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Florian Mangel 1']);

        $response = $this->post('/settings/vehicles/defects/create', [
            'vehicle_id'       => (string) $vehicle['id'],
            'title'            => 'Bremsen quietschen',
            'category'         => 'bremsen',
            'vehicle_operable' => '1',
        ], self::FRAGMENT);

        $this->assertOk($response);
        $this->assertSame('/settings/vehicles/defects/index?vehicle=' . $vehicle['id'], $response->headers['X-Ignis-Location'] ?? null);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM intra_fahrzeuge_defects WHERE vehicle_id = ' . $vehicle['id'])->fetchColumn());
        // Die Zielseite zeigt die Meldung als Toast.
        $this->assertBodyContains('data-ignis-flash data-variant="success"', $this->get('/settings/vehicles/defects/index', ['query' => ['vehicle' => (string) $vehicle['id']]]));
    }

    #[Test]
    public function listen_und_neu_menue_oeffnen_die_formulare_im_drawer(): void
    {
        $this->login();

        $this->assertMatchesRegularExpression('~<a href="/settings/vehicles/vehicles/create"[^>]*data-ignis-drawer~', $this->get('/settings/vehicles/vehicles/index')->body);
        $this->assertMatchesRegularExpression('~<a href="/settings/vehicles/defects/create"[^>]*data-ignis-drawer~', $this->get('/settings/vehicles/defects/index')->body);
        $this->assertMatchesRegularExpression('~<a href="/personnel/create"[^>]*data-ignis-drawer~', $this->get('/personnel/list')->body);
        $this->assertMatchesRegularExpression('~<a href="/calendar/create"[^>]*data-ignis-drawer~', $this->get('/calendar')->body);

        $dashboard = $this->get('/index')->body;
        foreach (['/calendar/create', '/personnel/create', '/settings/vehicles/vehicles/create', '/settings/vehicles/defects/create'] as $href) {
            $this->assertMatchesRegularExpression('~<a href="' . preg_quote($href, '~') . '"[^>]*role="menuitem" data-ignis-drawer~', $dashboard, 'Neu-Menü: ' . $href);
            $this->assertMatchesRegularExpression('~<a\s+href="' . preg_quote($href, '~') . '"\s+class="ignis-sidebar__quick"\s+data-ignis-drawer~', $dashboard, 'Sidebar-Plus: ' . $href);
        }
        // Ohne Formularseite bleibt die Schnellaktion ein Modal.
        $this->assertStringContainsString('data-quick-action-type="modal"', $dashboard);
        $this->assertStringContainsString('data-quick-action-target="role-create"', $dashboard);
    }

    #[Test]
    public function ohne_recht_gibt_es_weder_link_noch_formular(): void
    {
        // Kalender sehen reicht nicht, um Termine anzulegen: der Eintrag
        // bleibt, das Plus und der Menüpunkt verschwinden.
        $this->login(['calendar.view']);

        $dashboard = $this->get('/index')->body;
        $this->assertStringContainsString('href="/calendar"', $dashboard);
        $this->assertStringNotContainsString('href="/calendar/create"', $dashboard);
        $this->assertStringNotContainsString('href="/personnel/create"', $dashboard);

        $this->assertRedirect($this->get('/personnel/create'));
        $this->assertRedirect($this->get('/calendar/create'));
    }
}
