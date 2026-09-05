<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VehicleDefect;
use App\Security\CsrfProtection;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Arbeitsbereich der Fahrzeugliste (assets/js/ui/workbench.js): die
 * Vorschau einer Zeile kommt ohne Hülle hinter demselben Recht wie die
 * Liste, mit Mängeln, Beladung und taktischem Zeichen; die Sammelaktionen
 * Status setzen und Löschen folgen den Regeln der Einzelaktion (Recht,
 * CSRF, Audit je Fahrzeug); die Liste trägt Kästchen und Leiste nur mit
 * vehicle.manage.
 */
final class VehicleWorkbenchTest extends FeatureTestCase
{
    private const LIST = '/settings/vehicles/vehicles/index';

    private int $userId = 0;

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions): int
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);

        return $this->userId = $user->id;
    }

    /**
     * @param array<string,mixed> $post
     */
    private function postWithToken(string $path, array $post): \App\Http\Response
    {
        return $this->post($path, $post + ['csrf_token' => CsrfProtection::getToken()]);
    }

    private function auditCount(string $action): int
    {
        return Capsule::table('intra_audit_log')->where('user', $this->userId)->where('module', 'Fahrzeuge')->where('action', $action)->count();
    }

    #[Test]
    public function vorschau_ohne_huelle_mit_maengeln_beladung_und_zeichen(): void
    {
        $this->login(['vehicles.view']);
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Florian Vorschau 1/83/2', 'veh_type' => 'RTW-V', 'rd_type' => 2]);
        Capsule::table('intra_fahrzeuge')->where('id', $vehicle['id'])->update([
            'kennzeichen'  => 'LS-FW 832',
            'grundzeichen' => 'fahrzeug',
            'organisation' => 'feuerwehr',
            'fachaufgabe'  => 'rettungswesen',
            'text'         => 'RTW 2',
            'tz_name'      => 'RD Standard',
        ]);
        foreach (['Bremsen quietschen', 'Blaulicht hinten', 'Kühlbox defekt', 'Uralt, erledigt'] as $i => $title) {
            VehicleDefect::query()->create([
                'vehicle_id'       => $vehicle['id'],
                'title'            => $title,
                'category'         => 'mechanik',
                'vehicle_operable' => $i === 0 ? 0 : 1,
                'status'           => $i === 3 ? 'resolved' : 'open',
                'reported_by'      => $this->userId,
                'created_at'       => date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' hours')),
            ]);
        }
        $categoryId = (int) Capsule::table('intra_fahrzeuge_beladung_categories')->insertGetId(['title' => 'Fach 1', 'veh_type' => 'RTW-V']);
        Capsule::table('intra_fahrzeuge_beladung_tiles')->insert([
            ['category' => $categoryId, 'title' => 'Verbandkasten', 'amount' => 2],
            ['category' => $categoryId, 'title' => 'Halskrause', 'amount' => 3],
        ]);

        $response = $this->get('/settings/vehicles/vehicles/' . $vehicle['id'] . '/preview');

        $this->assertOk($response);
        $this->assertBodyNotContains('<html', $response);
        $this->assertBodyNotContains('ignis-topbar', $response);
        $this->assertBodyContains('ignis-preview__title', $response);
        $this->assertBodyContains('Florian Vorschau 1/83/2', $response);
        $this->assertBodyContains('<span class="ignis-mono">LS-FW 832</span>', $response);
        $this->assertBodyContains('Einsatzbereit', $response);
        $this->assertBodyContains('Nicht einsatzfähig: Bremsen quietschen', $response);
        $this->assertBodyContains('3 offene Mängel', $response);
        $this->assertBodyContains('Bremsen quietschen', $response);
        $this->assertBodyContains('Kühlbox defekt', $response);
        $this->assertBodyNotContains('Uralt, erledigt', $response);
        $this->assertBodyContains('href="/settings/vehicles/defects/index?vehicle=' . $vehicle['id'] . '"', $response);
        $this->assertBodyContains('2 Positionen', $response);
        $this->assertBodyContains('in 1 Kategorien, 5 Stück', $response);
        $this->assertBodyContains('RD Standard', $response);
        $this->assertSame(1, preg_match('~data-ignis-tz="([^"]+)"~', $response->body, $m));
        $this->assertSame(
            ['grundzeichen' => 'fahrzeug', 'organisation' => 'feuerwehr', 'fachaufgabe' => 'rettungswesen', 'text' => 'RTW 2', 'name' => 'RD Standard'],
            json_decode(html_entity_decode($m[1], ENT_QUOTES), true),
        );
        // vehicles.view darf melden, aber nicht bearbeiten.
        $this->assertBodyContains('href="/settings/vehicles/defects/create?vehicle=' . $vehicle['id'] . '" class="ignis-btn ignis-btn--sm ignis-btn--secondary" data-ignis-drawer', $response);
        $this->assertBodyNotContains('/edit', $response);

        $this->login(['vehicles.view', 'vehicles.manage']);
        $response = $this->get('/settings/vehicles/vehicles/' . $vehicle['id'] . '/preview');
        $this->assertBodyContains('href="/settings/vehicles/vehicles/' . $vehicle['id'] . '/edit" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-ignis-drawer', $response);
    }

    #[Test]
    public function vorschau_braucht_das_listenrecht_und_kennt_fehlende_zeilen(): void
    {
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Geheim 1']);

        $this->login(['personnel.view']);
        $denied = $this->get('/settings/vehicles/vehicles/' . $vehicle['id'] . '/preview');
        $this->assertRedirect($denied);
        $this->assertBodyNotContains('Geheim 1', $denied);

        $this->login(['vehicles.view']);
        $this->assertNotFound($this->get('/settings/vehicles/vehicles/999999999/preview'));
        $this->assertBodyContains('Kein Zeichen hinterlegt.', $this->get('/settings/vehicles/vehicles/' . $vehicle['id'] . '/preview'));
    }

    #[Test]
    public function sammel_status_setzt_nur_erlaubte_werte_und_schreibt_je_fahrzeug_ins_audit(): void
    {
        $this->login(['vehicles.manage']);
        $a = FixtureFactory::fahrzeug(['name' => 'Status A']);
        $b = FixtureFactory::fahrzeug(['name' => 'Status B']);
        $c = FixtureFactory::fahrzeug(['name' => 'Status C']);
        Capsule::table('intra_fahrzeuge')->whereIn('id', [$a['id'], $b['id'], $c['id']])->update(['active' => 1]);

        $response = $this->postWithToken('/settings/vehicles/vehicles/status', ['ids' => [(string) $a['id'], (string) $b['id'], '999999999'], 'status' => 'inactive']);

        $this->assertRedirect($response, self::LIST);
        $this->assertSame(0, (int) Capsule::table('intra_fahrzeuge')->where('id', $a['id'])->value('active'));
        $this->assertSame(0, (int) Capsule::table('intra_fahrzeuge')->where('id', $b['id'])->value('active'));
        $this->assertSame(1, (int) Capsule::table('intra_fahrzeuge')->where('id', $c['id'])->value('active'));
        $this->assertSame(1, $this->auditCount('Fahrzeug aktualisiert [ID: ' . $a['id'] . ']'));
        $this->assertSame(1, $this->auditCount('Fahrzeug aktualisiert [ID: ' . $b['id'] . ']'));
        $this->assertSame(0, $this->auditCount('Fahrzeug aktualisiert [ID: ' . $c['id'] . ']'));
        $this->assertStringContainsString('2 Fahrzeuge auf „inaktiv" gesetzt.', (string) ($_SESSION['flash']['text'] ?? ''));

        // Ein unbekannter Wert ändert nichts.
        $this->postWithToken('/settings/vehicles/vehicles/status', ['ids' => [(string) $c['id']], 'status' => 'kaputt']);
        $this->assertSame(1, (int) Capsule::table('intra_fahrzeuge')->where('id', $c['id'])->value('active'));

        // Zurück auf aktiv.
        $this->postWithToken('/settings/vehicles/vehicles/status', ['ids' => [(string) $a['id']], 'status' => 'active']);
        $this->assertSame(1, (int) Capsule::table('intra_fahrzeuge')->where('id', $a['id'])->value('active'));
    }

    #[Test]
    public function sammel_emd_status_kennt_nur_die_fms_werte_und_schreibt_je_fahrzeug_ins_audit(): void
    {
        $this->login(['vehicles.manage']);
        $a = FixtureFactory::fahrzeug(['name' => 'FMS A']);
        $b = FixtureFactory::fahrzeug(['name' => 'FMS B']);
        $c = FixtureFactory::fahrzeug(['name' => 'FMS C']);
        Capsule::table('intra_fahrzeuge')->whereIn('id', [$a['id'], $b['id'], $c['id']])->update(['current_status' => '2', 'status_source' => 'incident']);

        $response = $this->postWithToken('/settings/vehicles/vehicles/emd-status', ['ids' => [(string) $a['id'], (string) $b['id'], '999999999'], 'emd_status' => '6']);

        $this->assertRedirect($response, self::LIST);
        foreach ([$a, $b] as $vehicle) {
            $row = Capsule::table('intra_fahrzeuge')->where('id', $vehicle['id'])->first(['current_status', 'status_source', 'status_updated_at']);
            $this->assertSame('6', (string) $row->current_status);
            $this->assertSame('manual', (string) $row->status_source);
            $this->assertNotEmpty($row->status_updated_at);
            $this->assertSame(1, $this->auditCount('Fahrzeug aktualisiert [ID: ' . $vehicle['id'] . ']'));
        }
        $this->assertSame('2', (string) Capsule::table('intra_fahrzeuge')->where('id', $c['id'])->value('current_status'));
        $this->assertSame(0, $this->auditCount('Fahrzeug aktualisiert [ID: ' . $c['id'] . ']'));
        $this->assertSame('EMD-Status: 6 (Nicht einsatzbereit) (Sammelaktion)', (string) Capsule::table('intra_audit_log')->where('user', $this->userId)->where('action', 'Fahrzeug aktualisiert [ID: ' . $a['id'] . ']')->value('details'));
        $this->assertStringContainsString('2 Fahrzeuge auf Status 6 (Nicht einsatzbereit) gesetzt.', (string) ($_SESSION['flash']['text'] ?? ''));

        // Werte außerhalb 0–6 ändern nichts, auch nicht der aktiv/inaktiv-Wert der anderen Sammelaktion.
        foreach (['7', 'inactive', '', '2x'] as $bad) {
            $this->postWithToken('/settings/vehicles/vehicles/emd-status', ['ids' => [(string) $c['id']], 'emd_status' => $bad]);
        }
        $this->assertSame('2', (string) Capsule::table('intra_fahrzeuge')->where('id', $c['id'])->value('current_status'));
        $this->assertSame('incident', (string) Capsule::table('intra_fahrzeuge')->where('id', $c['id'])->value('status_source'));
        $this->assertSame(0, $this->auditCount('Fahrzeug aktualisiert [ID: ' . $c['id'] . ']'));

        // Ohne Recht bleibt alles stehen.
        $this->login(['vehicles.view']);
        $this->assertRedirect($this->postWithToken('/settings/vehicles/vehicles/emd-status', ['ids' => [(string) $c['id']], 'emd_status' => '1']));
        $this->assertSame('2', (string) Capsule::table('intra_fahrzeuge')->where('id', $c['id'])->value('current_status'));
    }

    #[Test]
    public function sammelaktionen_brauchen_recht_und_csrf(): void
    {
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Bleibt']);

        $this->login(['vehicles.view']);
        $denied = $this->postWithToken('/settings/vehicles/vehicles/status', ['ids' => [(string) $vehicle['id']], 'status' => 'inactive']);
        $this->assertRedirect($denied);
        $this->assertSame(1, (int) Capsule::table('intra_fahrzeuge')->where('id', $vehicle['id'])->value('active'));

        $this->login(['vehicles.manage']);
        $noToken = $this->post('/settings/vehicles/vehicles/delete', ['ids' => [(string) $vehicle['id']], 'csrf_token' => 'falsch']);
        $this->assertSame(403, $noToken->status);
        $this->assertNotNull(Capsule::table('intra_fahrzeuge')->where('id', $vehicle['id'])->first());
    }

    #[Test]
    public function sammel_loeschen_entfernt_die_auswahl_mit_audit_je_fahrzeug(): void
    {
        $this->login(['vehicles.manage']);
        $a = FixtureFactory::fahrzeug(['name' => 'Weg A']);
        $b = FixtureFactory::fahrzeug(['name' => 'Weg B']);
        $stays = FixtureFactory::fahrzeug(['name' => 'Bleibt C']);

        $response = $this->postWithToken('/settings/vehicles/vehicles/delete', ['ids' => [(string) $a['id'], (string) $b['id'], '999999999']]);

        $this->assertRedirect($response, self::LIST);
        $this->assertNull(Capsule::table('intra_fahrzeuge')->where('id', $a['id'])->first());
        $this->assertNull(Capsule::table('intra_fahrzeuge')->where('id', $b['id'])->first());
        $this->assertNotNull(Capsule::table('intra_fahrzeuge')->where('id', $stays['id'])->first());
        $this->assertSame(1, $this->auditCount('Fahrzeug gelöscht [ID: ' . $a['id'] . ']'));
        $this->assertSame(1, $this->auditCount('Fahrzeug gelöscht [ID: ' . $b['id'] . ']'));
        $this->assertStringContainsString('2 Fahrzeuge gelöscht.', (string) ($_SESSION['flash']['text'] ?? ''));

        // Das einzelne `id` geht weiterhin.
        $this->postWithToken('/settings/vehicles/vehicles/delete', ['id' => (string) $stays['id']]);
        $this->assertNull(Capsule::table('intra_fahrzeuge')->where('id', $stays['id'])->first());
    }

    #[Test]
    public function liste_traegt_die_haken_nur_mit_recht(): void
    {
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Haken 1']);

        $this->login(['vehicles.view']);
        $list = $this->get(self::LIST, ['query' => ['q' => 'Haken']]);
        $this->assertOk($list);
        $this->assertBodyContains('data-ignis-workbench data-ignis-preview-url="/settings/vehicles/vehicles/{id}/preview"', $list);
        $this->assertBodyContains('<tr data-ignis-row="' . $vehicle['id'] . '" data-href="/settings/vehicles/vehicles/' . $vehicle['id'] . '" tabindex="0">', $list);
        $this->assertBodyContains('class="ignis-preview" data-ignis-preview', $list);
        $this->assertBodyContains('assets/js/ui/workbench.js', $list);
        $this->assertBodyNotContains('data-ignis-bulkbar', $list);
        $this->assertBodyNotContains('data-ignis-select', $list);
        $this->assertBodyNotContains('/edit', $list);

        $this->login(['vehicles.view', 'vehicles.manage']);
        $list = $this->get(self::LIST, ['query' => ['q' => 'Haken']]);
        $this->assertBodyContains('action="/settings/vehicles/vehicles/status" class="ignis-bulkbar" data-ignis-bulkbar hidden', $list);
        $this->assertBodyContains('formaction="/settings/vehicles/vehicles/delete" data-ignis-bulk-confirm=', $list);
        $this->assertBodyContains('<select name="emd_status" id="bulk-emd-status" class="ignis-input ignis-input--sm">', $list);
        $this->assertBodyContains('<option value="6">6 · Nicht einsatzbereit</option>', $list);
        $this->assertBodyContains('formaction="/settings/vehicles/vehicles/emd-status"', $list);
        $this->assertBodyContains('data-ignis-select-all', $list);
        $this->assertBodyContains('data-ignis-select value="' . $vehicle['id'] . '"', $list);
        $this->assertBodyContains('href="/settings/vehicles/vehicles/' . $vehicle['id'] . '/edit" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-drawer', $list);
    }

    #[Test]
    public function bearbeiten_formular_traegt_die_werte_und_laeuft_im_drawer(): void
    {
        $this->login(['vehicles.manage']);
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Edit Florian 1/46/1', 'veh_type' => 'HLF', 'rd_type' => 3]);
        Capsule::table('intra_fahrzeuge')->where('id', $vehicle['id'])->update(['kennzeichen' => 'LS-FW 461', 'active' => 0, 'grundzeichen' => 'fahrzeug', 'tz_name' => 'HLF Vorlage']);

        $page = $this->get('/settings/vehicles/vehicles/' . $vehicle['id'] . '/edit');
        $this->assertOk($page);
        $this->assertBodyContains('<h1>Fahrzeug bearbeiten</h1>', $page);
        $this->assertBodyContains('action="/settings/vehicles/vehicles/update"', $page);
        $this->assertBodyContains('<input type="hidden" name="id" value="' . $vehicle['id'] . '">', $page);
        $this->assertBodyContains('value="Edit Florian 1/46/1"', $page);
        $this->assertBodyContains('value="LS-FW 461"', $page);
        $this->assertBodyContains('<option value="3" selected>', $page);
        $this->assertBodyContains('id="edit-fahrzeug-active"><span>', $page);
        $this->assertBodyNotContains('id="edit-fahrzeug-active" checked', $page);
        $this->assertBodyContains('"grundzeichen":"fahrzeug"', $page);
        $this->assertBodyContains('"tz_name":"HLF Vorlage"', $page);

        $fragment = $this->get('/settings/vehicles/vehicles/' . $vehicle['id'] . '/edit', ['headers' => ['X-Requested-With' => 'fragment']]);
        $this->assertStringStartsWith('<div class="ignis-fragment" data-title="Fahrzeug bearbeiten">', $fragment->body);
        $this->assertBodyNotContains('<html', $fragment);

        $this->login(['vehicles.view']);
        $this->assertRedirect($this->get('/settings/vehicles/vehicles/' . $vehicle['id'] . '/edit'), self::LIST);
    }
}
