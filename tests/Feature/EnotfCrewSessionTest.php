<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Crew-Anmeldung gilt, solange ihre Zeile in intra_enotf_sessions
 * aktiv ist. Jede Anfrage jedes Tablets prüft das, nicht erst der
 * 10-Sekunden-Abgleich. Mehrere Tablets spielt der Test durch, indem er
 * $_SESSION je Gerät austauscht.
 */
final class EnotfCrewSessionTest extends FeatureTestCase
{
    private string $fahrzeug;
    private string $enr;
    private int $sessionId;

    /** @var array<string,mixed> */
    private array $fahrerTablet;

    /** @var array<string,mixed> */
    private array $beifahrerTablet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fahrzeug = FixtureFactory::fahrzeug(['rd_type' => 2])['identifier'];
        $crew = [
            'fahrer'    => ['name' => 'Erika Muster', 'quali' => 'NotSan'],
            'beifahrer' => ['name' => 'Bernd Bei', 'quali' => 'RS'],
        ];

        $this->sessionId = FixtureFactory::crewLogin($this->fahrzeug, $crew)['session_id'];
        SessionManager::setPinVerified(true);
        $this->fahrerTablet = $_SESSION;

        $_SESSION = [];
        FixtureFactory::crewJoin($this->sessionId, $this->fahrzeug, $crew, 'beifahrer');
        SessionManager::setPinVerified(true);
        $this->beifahrerTablet = $_SESSION;

        $this->enr = 'T-' . uniqid();
        Capsule::table('intra_edivi')->insert([
            'enr'              => $this->enr,
            'fzg_transp'       => $this->fahrzeug,
            'protokoll_status' => 0,
            'hidden'           => 0,
            'hidden_user'      => 0,
            'freigegeben'      => 0,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, string> $fields */
    private function save(array $fields): Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            '/api/enotf/save-fields',
            server: ['HTTP_X_CSRF_TOKEN' => $this->csrfToken(), 'CONTENT_TYPE' => 'application/json'],
            rawBody: json_encode(['enr' => $this->enr, 'fields' => $fields], JSON_THROW_ON_ERROR),
        ));
    }

    private function pollStatus(): Response
    {
        return $this->get('/api/enotf/session-status', ['headers' => ['X-Enotf-Session-Token' => (string) ($_SESSION['enotf_session_token'] ?? '')]]);
    }

    private function sessionValue(string $column): mixed
    {
        return Capsule::table('intra_enotf_sessions')->where('id', $this->sessionId)->value($column);
    }

    #[Test]
    public function alle_abmelden_beendet_auch_das_andere_tablet_sofort(): void
    {
        $_SESSION = $this->fahrerTablet;
        $this->post('/enotf/loggedout', ['_csrf' => 'x', 'mode' => 'all']);

        $_SESSION = $this->beifahrerTablet;
        $response = $this->save(['rea_status' => '2']);

        $this->assertStatus(401, $response);
        $this->assertSame('session_ended', $this->assertJsonResponse($response)['code']);
        $this->assertNull(Capsule::table('intra_edivi')->where('enr', $this->enr)->value('rea_status'));
        $this->assertFalse(SessionManager::isEnotfActive());
    }

    #[Test]
    public function eine_beendete_anmeldung_landet_mit_grund_auf_der_abmelde_seite(): void
    {
        (new \Plugin\Enotf\EnotfSession())->deactivateAllForVehicle($this->fahrzeug);

        $_SESSION = $this->beifahrerTablet;
        $this->assertRedirect($this->get('/enotf/overview'), '/enotf/loggedout?grund=beendet');

        // Keine Schleife: die Seite fragt nicht mehr, wie abgemeldet werden soll
        $page = $this->get('/enotf/loggedout', ['query' => ['grund' => 'beendet']]);
        $this->assertOk($page);
        $this->assertBodyContains('Die Anmeldung auf dem Fahrzeug wurde beendet.', $page);
        $this->assertBodyNotContains('Wie möchten Sie sich abmelden?', $page);
    }

    #[Test]
    public function der_beifahrer_bleibt_angemeldet_wenn_der_fahrer_geht(): void
    {
        $_SESSION = $this->fahrerTablet;
        $this->post('/enotf/loggedout', ['_csrf' => 'x', 'mode' => 'self']);
        $this->assertSame(1, (int) $this->sessionValue('active'));
        $this->assertNull($this->sessionValue('fahrername'));

        $_SESSION = $this->beifahrerTablet;
        $this->assertOk($this->get('/enotf/overview'));
        $this->assertStatus(200, $this->save(['rea_status' => '2']));
        $this->assertSame('', (string) ($_SESSION['fahrername'] ?? ''));
        $this->assertSame('Bernd Bei', $_SESSION['beifahrername']);
    }

    #[Test]
    public function eine_neue_besatzung_meldet_die_alte_mit_grund_ab(): void
    {
        (new \Plugin\Enotf\EnotfSession())->createSession($this->fahrzeug, [
            'fahrername' => 'Neue Crew', 'fahrerquali' => 'NotSan',
            'beifahrername' => null, 'beifahrerquali' => null, 'praktikantname' => null, 'praktikantquali' => null,
        ]);

        $_SESSION = $this->fahrerTablet;
        $this->assertRedirect($this->get('/enotf/overview'), '/enotf/loggedout?grund=abgeloest');
        $this->assertSame('replaced', $this->sessionValue('ended_reason'));
    }

    #[Test]
    public function ein_defekt_beendet_die_anmeldung_bei_der_naechsten_anfrage(): void
    {
        Capsule::table('intra_fahrzeuge')->where('identifier', $this->fahrzeug)->update(['active' => 0]);

        $_SESSION = $this->fahrerTablet;
        $this->assertRedirect($this->get('/enotf/overview'), '/enotf/loggedout?grund=ausser-dienst');
        $this->assertSame(0, (int) $this->sessionValue('active'));
        $this->assertSame('out_of_service', $this->sessionValue('ended_reason'));
    }

    #[Test]
    public function eine_lange_ungenutzte_anmeldung_laeuft_ab(): void
    {
        Capsule::table('intra_enotf_sessions')->where('id', $this->sessionId)
            ->update(['last_activity' => Capsule::raw('NOW() - INTERVAL 13 HOUR')]);

        $_SESSION = $this->fahrerTablet;
        $this->assertRedirect($this->get('/enotf/overview'), '/enotf/loggedout?grund=abgelaufen');
        $this->assertSame('expired', $this->sessionValue('ended_reason'));
    }

    #[Test]
    public function der_abgleich_verlaengert_die_anmeldung_nicht_eine_seite_schon(): void
    {
        Capsule::table('intra_enotf_sessions')->where('id', $this->sessionId)
            ->update(['last_activity' => Capsule::raw('NOW() - INTERVAL 2 HOUR')]);
        $before = (string) $this->sessionValue('last_activity');

        $_SESSION = $this->fahrerTablet;
        $status = $this->assertJsonResponse($this->pollStatus());
        $this->assertTrue($status['active']);
        $this->assertSame($before, (string) $this->sessionValue('last_activity'));

        $this->assertOk($this->get('/enotf/overview'));
        $this->assertNotSame($before, (string) $this->sessionValue('last_activity'));
    }

    #[Test]
    public function der_abgleich_nennt_den_grund_und_zieht_die_besatzung_nach(): void
    {
        Capsule::table('intra_enotf_sessions')->where('id', $this->sessionId)->update(['praktikantname' => 'Paul Prak']);

        $_SESSION = $this->beifahrerTablet;
        $status = $this->assertJsonResponse($this->pollStatus());
        $this->assertSame('Paul Prak', $status['crew']['praktikantname']);
        $this->assertSame('Paul Prak', $_SESSION['praktikantname']);

        (new \Plugin\Enotf\EnotfSession())->deactivateAllForVehicle($this->fahrzeug, \Plugin\Enotf\EnotfSession::ENDED_DELETED);
        $status = $this->assertJsonResponse($this->pollStatus());
        $this->assertFalse($status['active']);
        $this->assertSame('beendet', $status['grund']);
        $this->assertFalse(SessionManager::isEnotfActive());
    }

    #[Test]
    public function ein_fremdes_token_im_abgleich_zaehlt_nicht(): void
    {
        $fremd = (string) $this->fahrerTablet['enotf_session_token'];

        $_SESSION = [];
        $status = $this->assertJsonResponse($this->get('/api/enotf/session-status', ['headers' => ['X-Enotf-Session-Token' => $fremd]]));
        $this->assertFalse($status['active']);
        $this->assertArrayNotHasKey('crew', $status);

        $update = $this->post('/api/enotf/session-update', ['token' => $fremd]);
        $this->assertStatus(404, $update);
        $this->assertArrayNotHasKey('fahrername', $_SESSION);
    }

    #[Test]
    public function wer_das_fahrzeug_wechselt_gibt_die_alte_position_frei(): void
    {
        $anderes = FixtureFactory::fahrzeug(['rd_type' => 2])['identifier'];

        $_SESSION = $this->beifahrerTablet;
        $response = $this->post('/enotf/login', [
            '_csrf'           => 'x',
            'login_mode'      => 'new',
            'fahrername'      => 'Bernd Bei',
            'fahrerquali'     => 'RS',
            'beifahrername'   => '',
            'beifahrerquali'  => '',
            'praktikantname'  => '',
            'praktikantquali' => '',
            'protfzg'         => $anderes,
        ]);

        $this->assertRedirect($response, '/enotf/overview');
        $this->assertSame($anderes, $_SESSION['protfzg']);
        $this->assertNull($this->sessionValue('beifahrername'));
        $this->assertSame(1, (int) $this->sessionValue('active'));
        $this->assertSame(1, Capsule::table('intra_enotf_session_members')->where('session_id', $this->sessionId)->count());
    }

    #[Test]
    public function anmelden_geht_nur_auf_waehlbaren_fahrzeugen(): void
    {
        $inaktiv = FixtureFactory::fahrzeug(['rd_type' => 2]);
        Capsule::table('intra_fahrzeuge')->where('id', $inaktiv['id'])->update(['active' => 0]);
        $feuerwehr = FixtureFactory::fahrzeug(['rd_type' => 3])['identifier'];

        foreach ([$inaktiv['identifier'], $feuerwehr, 'gibt-es-nicht'] as $fahrzeug) {
            $_SESSION = [];
            $response = $this->post('/enotf/login', [
                '_csrf'       => 'x',
                'login_mode'  => 'new',
                'fahrername'  => 'Erika Muster',
                'fahrerquali' => 'NotSan',
                'protfzg'     => $fahrzeug,
            ]);

            $this->assertRedirect($response, '/enotf/login?error=vehicle');
            $this->assertFalse(SessionManager::isEnotfActive());
            $this->assertFalse(Capsule::table('intra_enotf_sessions')->where('vehicle_identifier', $fahrzeug)->exists());
        }
    }

    #[Test]
    public function eine_besetzte_position_laesst_sich_nicht_uebernehmen(): void
    {
        $_SESSION = [];
        $response = $this->post('/enotf/login', [
            '_csrf'         => 'x',
            'login_mode'    => 'join',
            'protfzg'       => $this->fahrzeug,
            'join_position' => 'fahrer',
            'join_name'     => 'Eindringling',
            'join_quali'    => 'RS',
        ]);

        $this->assertRedirect($response, '/enotf/login?error=position_taken');
        $this->assertSame('Erika Muster', $this->sessionValue('fahrername'));
        $this->assertSame(1, (int) $this->sessionValue('active'));
        $this->assertFalse(SessionManager::isEnotfActive());
    }
}
