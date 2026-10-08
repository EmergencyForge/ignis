<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Crew-Seiten des eNOTF lesen ihre Formulare über die Requests in
 * plugins/enotf/src/Crew/Requests: Anmelden, Beitreten, Abmelden, „alle
 * löschen" und der PIN-Lockscreen. Das Anlegen prüft EnotfCreateTest.
 *
 * Die Formulare schicken `_csrf` statt `csrf_token`, deshalb steht es in
 * jedem Post.
 */
final class EnotfCrewFormsTest extends FeatureTestCase
{
    private string $fahrzeug;

    protected function setUp(): void
    {
        parent::setUp();
        // Unabhängig davon, ob die Datenbank ENOTF_USE_PIN eingeschaltet hat
        SessionManager::setPinVerified(true);
        $this->fahrzeug = FixtureFactory::fahrzeug()['identifier'];
    }

    #[Test]
    public function anmelden_beitreten_und_abmelden(): void
    {
        $response = $this->post('/enotf/login', [
            '_csrf'           => 'x',
            'login_mode'      => 'new',
            'fahrername'      => 'Erika Muster',
            'fahrerquali'     => 'NotSan',
            'beifahrername'   => '',
            'beifahrerquali'  => '',
            'praktikantname'  => '',
            'praktikantquali' => '',
            'protfzg'         => $this->fahrzeug,
            'data__set'       => '',
        ]);

        $this->assertRedirect($response, '/enotf/overview');
        $this->assertSame('Erika Muster', $_SESSION['fahrername'] ?? null);
        $this->assertSame($this->fahrzeug, $_SESSION['protfzg'] ?? null);
        $sitzung = Capsule::table('intra_enotf_sessions')->where('vehicle_identifier', $this->fahrzeug)->where('active', 1)->first();
        $this->assertNotNull($sitzung);

        // Zweites Gerät tritt derselben Besatzung bei
        SessionManager::logoutEnotfCrew();
        $response = $this->post('/enotf/login', [
            '_csrf'         => 'x',
            'login_mode'    => 'join',
            'protfzg'       => $this->fahrzeug,
            'join_position' => 'beifahrer',
            'join_name'     => 'Bernd Bei',
            'join_quali'    => 'RS',
        ]);

        $this->assertRedirect($response, '/enotf/overview');
        $this->assertSame('Bernd Bei', $_SESSION['beifahrername'] ?? null);
        $this->assertSame('Erika Muster', $_SESSION['fahrername'] ?? null);
        $this->assertSame('Bernd Bei', Capsule::table('intra_enotf_sessions')->where('id', $sitzung->id)->value('beifahrername'));

        $response = $this->post('/enotf/loggedout', ['_csrf' => 'x', 'mode' => 'all']);

        $this->assertRedirect($response, '/enotf/loggedout');
        $this->assertArrayNotHasKey('fahrername', $_SESSION);
        $this->assertSame(0, (int) Capsule::table('intra_enotf_sessions')->where('id', $sitzung->id)->value('active'));
    }

    #[Test]
    public function anmeldung_mit_fremdem_feld_meldet_niemanden_an(): void
    {
        $response = $this->post('/enotf/login', [
            'login_mode' => 'new',
            'fahrername' => 'Erika Muster',
            'protfzg'    => $this->fahrzeug,
            'admin'      => '1',
        ]);

        $this->assertRedirect($response, '/enotf/login');
        $this->assertArrayNotHasKey('fahrername', $_SESSION);
        $this->assertFalse(Capsule::table('intra_enotf_sessions')->where('vehicle_identifier', $this->fahrzeug)->exists());
    }

    #[Test]
    public function alle_loeschen_blendet_die_offenen_protokolle_aus(): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'Erika Muster', 'quali' => 'NotSan']], $this->fahrzeug);
        $offen = (int) Capsule::table('intra_edivi')->insertGetId(['enr' => 'T-' . uniqid(), 'fzg_transp' => $this->fahrzeug, 'freigegeben' => 0, 'hidden' => 0, 'hidden_user' => 0]);

        // Ohne delete_all passiert nichts
        $this->assertRedirect($this->post('/enotf/overview', ['_csrf' => 'x']), '/enotf/overview');
        $this->assertSame(0, (int) Capsule::table('intra_edivi')->where('id', $offen)->value('hidden_user'));

        $this->assertRedirect($this->post('/enotf/overview', ['_csrf' => 'x', 'delete_all' => '1']), '/enotf/overview');
        $zeile = Capsule::table('intra_edivi')->where('id', $offen)->first();
        $this->assertSame(1, (int) $zeile->hidden_user);
        $this->assertSame('Erika Muster', $zeile->freigeber_name);
    }

    #[Test]
    public function pin_entsperrt_den_lockscreen(): void
    {
        if (!CrewPolicy::pinEnabled() || !defined('ENOTF_PIN')) {
            $this->markTestSkipped('In dieser Datenbank ist die eNOTF-PIN aus.');
        }
        // Ohne edivi.view, sonst ist der Lockscreen übersprungen
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => []]);

        $falsch = $this->post('/enotf/lockscreen', ['_csrf' => 'x', 'pin' => ENOTF_PIN . '9']);
        $this->assertOk($falsch);
        $this->assertBodyContains('pin-display error', $falsch);

        $liste = $this->post('/enotf/lockscreen', ['_csrf' => 'x', 'pin' => [ENOTF_PIN]]);
        $this->assertOk($liste);
        $this->assertBodyContains('pin-display error', $liste);
        $this->assertEmpty($_SESSION['pin_verified'] ?? null);

        $richtig = $this->post('/enotf/lockscreen', ['_csrf' => 'x', 'pin' => (string) ENOTF_PIN]);
        $this->assertStatus(302, $richtig);
        $this->assertTrue($_SESSION['pin_verified'] ?? false);
    }
}
