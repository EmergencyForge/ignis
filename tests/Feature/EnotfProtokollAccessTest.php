<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Wer ein Protokoll unter /enotf/p/{enr} sieht und wer es bearbeiten darf.
 */
final class EnotfProtokollAccessTest extends FeatureTestCase
{
    private string $enr;

    protected function setUp(): void
    {
        parent::setUp();
        // Unabhängig davon, ob die Datenbank ENOTF_USE_PIN eingeschaltet hat
        SessionManager::setPinVerified(true);

        $this->enr = '77' . random_int(1000000000, 9999999999);
        Capsule::table('intra_edivi')->insert([
            'enr'              => $this->enr,
            'fzg_transp'       => 'RTW-1',
            'protokoll_status' => 0,
            'hidden'           => 0,
            'freigegeben'      => 0,
        ]);
    }

    private function crew(string $fahrzeug): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'Erika Muster', 'quali' => 'NotSan']], $fahrzeug);
    }

    #[Test]
    public function die_crew_des_fahrzeugs_bearbeitet(): void
    {
        $this->crew('RTW-1');

        $page = $this->get('/enotf/p/' . $this->enr . '/abschluss');

        $this->assertOk($page);
        $this->assertBodyNotContains('Nur Ansicht', $page);
        $this->assertBodyContains('window.__ev2Locked = false', $page);
        $this->assertBodyContains('id="btn-klinikcode"', $page);
    }

    #[Test]
    public function qm_sieht_das_protokoll_ohne_fahrzeug_schreibgeschuetzt(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['edivi.view'], 'cirs_username' => $user->username]);

        $page = $this->get('/enotf/p/' . $this->enr . '/abschluss');

        $this->assertOk($page);
        $this->assertBodyContains('Nur Ansicht', $page);
        $this->assertBodyContains('window.__ev2Locked = true', $page);
        $this->assertBodyNotContains('id="btn-klinikcode"', $page);
        $this->assertBodyNotContains('id="share"', $page);
    }

    #[Test]
    public function wer_im_protokoll_steht_sieht_es_schreibgeschuetzt(): void
    {
        $person = FixtureFactory::personnel();
        $user = FixtureFactory::user(['aktenid' => $person->id]);
        Capsule::table('intra_edivi')->where('enr', $this->enr)->update(['fzg_transp_perso' => $person->fullname . ' (NotSan)']);
        $this->actingAs($user->id, ['permissions' => [], 'cirs_username' => $user->username]);

        $page = $this->get('/enotf/p/' . $this->enr);

        $this->assertOk($page);
        $this->assertBodyContains('Nur Ansicht', $page);
        $this->assertBodyContains('window.__ev2Locked = true', $page);
    }

    #[Test]
    public function ein_konto_ohne_bezug_sieht_das_protokoll_nicht(): void
    {
        $user = FixtureFactory::user(['aktenid' => FixtureFactory::personnel()->id]);
        $this->actingAs($user->id, ['permissions' => [], 'cirs_username' => $user->username]);

        $page = $this->get('/enotf/p/' . $this->enr);

        $this->assertBodyContains('Protokoll nicht gefunden', $page);
    }

    #[Test]
    public function eine_fremde_crew_sieht_das_protokoll_nicht(): void
    {
        $this->crew('RTW-2');

        // Status 404 setzt http_response_code(), das sieht der Test nicht
        $page = $this->get('/enotf/p/' . $this->enr);
        $this->assertBodyContains('Protokoll nicht gefunden', $page);
        $this->assertBodyNotContains('id="edivi__nidanav"', $page);
    }

    #[Test]
    public function ohne_crew_und_recht_geht_es_zur_anmeldung(): void
    {
        $this->assertRedirect($this->get('/enotf/p/' . $this->enr), '/enotf/login');
    }

    #[Test]
    public function alte_unterseiten_landen_auf_ihrem_abschnitt(): void
    {
        $base = '/enotf/p/' . $this->enr;

        $this->assertSame($base . '/abschluss?t=freigabe', $this->get($base . '/abschluss/freigabe')->headers['Location'] ?? null);
        $this->assertSame($base . '/erstbefund', $this->get($base . '/erstbefund/atemwege/1')->headers['Location'] ?? null);
        $this->assertSame($base . '/diagnose', $this->get('/enotf/protokoll/diagnose/1_10_3', ['query' => ['enr' => $this->enr]])->headers['Location'] ?? null);
        $this->assertSame($base, $this->get('/enotf/protokoll/protokollart', ['query' => ['enr' => $this->enr]])->headers['Location'] ?? null);
        $this->assertSame('/enotf/overview', $this->get('/enotf/protokoll/index')->headers['Location'] ?? null);
    }
}
