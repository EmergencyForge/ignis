<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Eigene Dokumente, Anträge und Protokolle: das Dashboard zeigt die fünf
 * neuesten und „Alle anzeigen“, die Seiten unter /me/ die ganze Liste.
 */
final class OwnListsTest extends FeatureTestCase
{
    private \App\Models\Personnel $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->person = FixtureFactory::personnel();
        $user = FixtureFactory::user(['aktenid' => $this->person->id]);
        $this->actingAs($user->id, ['permissions' => [], 'cirs_username' => $user->username]);
    }

    private function documents(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Capsule::table('intra_mitarbeiter_dokumente')->insert([
                'docid'             => 'OWN' . $i . '-' . substr(uniqid(), -6),
                'type'              => 1,
                'anrede'            => 0,
                'ausstellerid'      => 'test',
                'aussteller_name'   => 'Ausstellerin ' . $i,
                'ausstellungsdatum' => sprintf('2026-01-%02d', $i),
                'profileid'         => $this->person->id,
            ]);
        }
    }

    private function application(int $status): string
    {
        $typ = (int) Capsule::table('intra_antrag_typen')->insertGetId(['name' => 'Urlaub', 'aktiv' => 1, 'sortierung' => 1]);
        $nr = substr(uniqid(), -10);
        Capsule::table('intra_antraege')->insert([
            'uniqueid'      => $nr,
            'antragstyp_id' => $typ,
            'name_dn'       => $this->person->fullname,
            'mitarbeiter_id' => $this->person->id,
            'cirs_status'   => $status,
            'time_added'    => date('Y-m-d H:i:s'),
        ]);

        return $nr;
    }

    #[Test]
    public function das_dashboard_zeigt_fuenf_dokumente_und_den_link(): void
    {
        $this->documents(7);

        $page = $this->get('/index');

        $this->assertOk($page);
        $this->assertBodyContains('Eigene Dokumente <span class="ignis-count">7</span>', $page);
        $this->assertBodyContains('href="/me/documents">Alle anzeigen</a>', $page);
        $this->assertBodyContains('OWN7-', $page);
        $this->assertBodyNotContains('OWN2-', $page);
    }

    #[Test]
    public function die_dokumentenliste_zeigt_alle_und_sucht(): void
    {
        $this->documents(7);

        $all = $this->get('/me/documents');
        $this->assertOk($all);
        $this->assertBodyContains('OWN1-', $all);
        $this->assertBodyContains('OWN7-', $all);

        $found = $this->get('/me/documents', ['query' => ['q' => 'Ausstellerin 3']]);
        $this->assertBodyContains('OWN3-', $found);
        $this->assertBodyNotContains('OWN4-', $found);
    }

    #[Test]
    public function fremde_dokumente_tauchen_nicht_auf(): void
    {
        Capsule::table('intra_mitarbeiter_dokumente')->insert([
            'docid' => 'FREMD-' . substr(uniqid(), -6), 'type' => 1, 'anrede' => 0, 'ausstellerid' => 'test',
            'profileid' => FixtureFactory::personnel()->id,
        ]);

        $this->assertBodyNotContains('FREMD-', $this->get('/me/documents'));
    }

    #[Test]
    public function die_antragsliste_filtert_nach_status(): void
    {
        $offen = $this->application(\Plugin\Forms\Models\Form::STATUS_IN_PROGRESS);
        $angenommen = $this->application(\Plugin\Forms\Models\Form::STATUS_ACCEPTED);

        $all = $this->get('/me/applications');
        $this->assertOk($all);
        $this->assertBodyContains($offen, $all);
        $this->assertBodyContains($angenommen, $all);

        $filtered = $this->get('/me/applications', ['query' => ['status' => (string) \Plugin\Forms\Models\Form::STATUS_ACCEPTED]]);
        $this->assertBodyContains($angenommen, $filtered);
        $this->assertBodyNotContains($offen, $filtered);
    }

    #[Test]
    public function die_protokollliste_zeigt_eigene_enotf_protokolle(): void
    {
        $enr = '77' . random_int(1000000000, 9999999999);
        Capsule::table('intra_edivi')->insert([
            'enr' => $enr, 'fzg_transp_perso' => $this->person->fullname . ' (NotSan)',
            'protokoll_status' => 0, 'hidden' => 0, 'hidden_user' => 0, 'freigegeben' => 1,
            'sendezeit' => date('Y-m-d H:i:s'),
        ]);
        $fremd = '78' . random_int(1000000000, 9999999999);
        Capsule::table('intra_edivi')->insert([
            'enr' => $fremd, 'fzg_transp_perso' => 'Jemand Anderes', 'protokoll_status' => 0, 'hidden' => 0, 'hidden_user' => 0, 'freigegeben' => 1,
        ]);

        $page = $this->get('/me/protocols', ['query' => ['source' => 'enotf']]);

        $this->assertOk($page);
        $this->assertBodyContains('/enotf/p/' . $enr, $page);
        $this->assertBodyNotContains($fremd, $page);
    }

    #[Test]
    public function ohne_anmeldung_geht_es_zum_login(): void
    {
        unset($_SESSION['userid'], $_SESSION['permissions']);

        $this->assertRedirect($this->get('/me/documents'), '/login');
    }
}
