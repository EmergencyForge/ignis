<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * eNOTF: Vitalwerte im Verlauf löschen nur per POST mit CSRF-Token und nur
 * die Crew des Fahrzeugs. Gelöscht wird soft (`geloescht`), hartes Löschen
 * blockiert ein Trigger auf der Tabelle.
 */
final class EnotfVitalDeleteTest extends FeatureTestCase
{
    private const PATH = '/api/enotf/vitals/delete';

    private string $enr;
    private int $id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enr = 'T-' . uniqid();
        Capsule::table('intra_edivi')->insert([
            'enr'              => $this->enr,
            'fzg_transp'       => 'RTW-1',
            'patname'          => 'Max Muster',
            'protokoll_status' => 0,
            'hidden'           => 0,
            'freigegeben'      => 0,
        ]);
        $this->id = (int) Capsule::table('intra_edivi_vitalparameter_einzelwerte')->insertGetId([
            'enr'               => $this->enr,
            'zeitpunkt'         => date('Y-m-d H:i:s'),
            'parameter_name'    => 'Herzfrequenz',
            'parameter_wert'    => "80'",
            'parameter_einheit' => '/min',
            'erstellt_von'      => 'Test',
        ]);
    }

    private function crew(string $fahrzeug): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'X', 'quali' => 'NotSan']], $fahrzeug);
    }

    /** @param array<string, string> $server */
    private function delete(array $server = []): Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            self::PATH,
            server: $server + ['CONTENT_TYPE' => 'application/json'],
            rawBody: json_encode(['enr' => $this->enr, 'id' => $this->id], JSON_THROW_ON_ERROR),
        ));
    }

    private function deleted(): int
    {
        return (int) Capsule::table('intra_edivi_vitalparameter_einzelwerte')->where('id', $this->id)->value('geloescht');
    }

    #[Test]
    public function ohne_token_wird_nichts_geloescht(): void
    {
        $this->crew('RTW-1');

        $this->assertStatus(403, $this->delete());
        $this->assertSame(0, $this->deleted());
    }

    #[Test]
    public function die_crew_des_fahrzeugs_loescht_soft(): void
    {
        $this->crew('RTW-1');

        $this->assertOk($this->delete(['HTTP_X_CSRF_TOKEN' => $this->csrfToken()]));
        $this->assertSame(1, $this->deleted());
    }

    #[Test]
    public function eine_fremde_crew_loescht_nicht(): void
    {
        $this->crew('RTW-2');

        $this->assertStatus(403, $this->delete(['HTTP_X_CSRF_TOKEN' => $this->csrfToken()]));
        $this->assertSame(0, $this->deleted());
    }
}
