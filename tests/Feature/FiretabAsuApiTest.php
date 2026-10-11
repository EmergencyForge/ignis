<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Atemschutzüberwachung im Einsatz: Trupps, Ereignisse, Zugriff.
 */
final class FiretabAsuApiTest extends FeatureTestCase
{
    /** @var array{id:int,name:string,identifier:string,rd_type:int,veh_type:string} */
    private array $vehicle;
    private int $incidentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vehicle    = FixtureFactory::fahrzeug(['rd_type' => 3]);
        $this->incidentId = $this->incident();
        Capsule::table('intra_fire_incident_vehicles')->insert(['incident_id' => $this->incidentId, 'vehicle_id' => $this->vehicle['id']]);

        $_SESSION['einsatz_vehicle_id']    = $this->vehicle['id'];
        $_SESSION['einsatz_vehicle_name']  = $this->vehicle['name'];
        $_SESSION['einsatz_operator_id']   = FixtureFactory::personnel()->id;
        $_SESSION['einsatz_operator_name'] = 'Fiona Feuer';
    }

    private function incident(int $finalized = 0): int
    {
        return (int) Capsule::table('intra_fire_incidents')->insertGetId([
            'incident_number' => 'F-' . uniqid(),
            'location'        => 'Musterstraße 1',
            'keyword'         => 'B3 Lagerhalle',
            'started_at'      => '2026-10-11 10:00:00',
            'status'          => 0,
            'finalized'       => $finalized,
        ]);
    }

    private function trupp(?int $incidentId = null): int
    {
        $response = $this->post('/api/fire/asu/trupp', [
            'incident_id'      => (string) ($incidentId ?? $this->incidentId),
            'name'             => 'AT 1/42-1',
            'role'             => 'angriff',
            'task'             => 'Innenangriff 1. OG',
            'expected_minutes' => '30',
            'members'          => [['position' => 'tf', 'name' => 'Weber, M'], ['position' => 'tm1', 'name' => 'Krause, J']],
        ]);
        $this->assertSame(200, $response->status, $response->body);

        return (int) json_decode($response->body, true)['trupp_id'];
    }

    /** @param array<string,mixed> $extra */
    private function event(int $truppId, string $type, array $extra = []): \EmergencyForge\Http\Response
    {
        return $this->post('/api/fire/asu/event', [
            'incident_id' => (string) $this->incidentId,
            'trupp_id'    => (string) $truppId,
            'type'        => $type,
            'client_uuid' => $extra['client_uuid'] ?? sprintf('%08x-0000-4000-8000-%012x', random_int(0, 0xffffffff), random_int(0, 0xffffffffffff)),
        ] + $extra);
    }

    #[Test]
    public function die_crew_legt_einen_trupp_an_und_sieht_ihn_im_stand(): void
    {
        $truppId = $this->trupp();
        $this->assertSame(200, $this->event($truppId, 'angeschlossen', ['bar' => '300'])->status);

        $state = json_decode($this->get('/api/fire/asu/state', ['query' => ['incident_id' => (string) $this->incidentId]])->body, true);

        $this->assertTrue($state['success']);
        $this->assertSame('AT 1/42-1', $state['trupps'][0]['name']);
        $this->assertSame(['tf', 'tm1'], array_column($state['trupps'][0]['members'], 'position'));
        $this->assertSame('angeschlossen', $state['events'][0]['type']);
        $this->assertSame(300, $state['events'][0]['bar']);
        $this->assertSame($this->vehicle['name'], $state['events'][0]['device']);
        $this->assertIsInt($state['now']);
    }

    #[Test]
    public function dieselbe_meldung_zweimal_bleibt_ein_eintrag(): void
    {
        $truppId = $this->trupp();
        $uuid    = '0b6d7c1e-5a4f-4c2b-9e8d-1f2a3b4c5d6e';

        $first  = json_decode($this->event($truppId, 'ziel', ['client_uuid' => $uuid, 'bar' => '230'])->body, true);
        $second = json_decode($this->event($truppId, 'ziel', ['client_uuid' => $uuid, 'bar' => '230'])->body, true);

        $this->assertSame($first['event_id'], $second['event_id']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, Capsule::table('intra_fire_asu_events')->where('client_uuid', $uuid)->count());
    }

    #[Test]
    public function druck_einer_fremden_person_wird_abgewiesen(): void
    {
        $truppId = $this->trupp();
        $other   = $this->trupp();
        $member  = (int) Capsule::table('intra_fire_asu_members')->where('trupp_id', $other)->value('id');

        $this->assertSame(422, $this->event($truppId, 'druck', ['member_id' => (string) $member, 'bar' => '250'])->status);
        $this->assertSame(422, $this->event($truppId, 'druck', ['bar' => '500'])->status);
    }

    #[Test]
    public function ein_zurueckgenommener_eintrag_bleibt_markiert(): void
    {
        $truppId = $this->trupp();
        $eventId = (int) json_decode($this->event($truppId, 'rueckzug')->body, true)['event_id'];
        $delete  = fn () => $this->post('/api/fire/asu/trupp/delete', ['incident_id' => (string) $this->incidentId, 'trupp_id' => (string) $truppId]);

        $this->assertSame(409, $delete()->status, 'Trupp mit gültigem Eintrag bleibt');
        $this->assertSame(200, $this->post('/api/fire/asu/event/void', ['incident_id' => (string) $this->incidentId, 'event_id' => (string) $eventId])->status);

        $state = json_decode($this->get('/api/fire/asu/state', ['query' => ['incident_id' => (string) $this->incidentId]])->body, true);
        $this->assertTrue($state['events'][0]['voided']);
        $this->assertSame(200, $delete()->status, 'nur zurückgenommene Einträge: Fehleingabe, darf weg');
    }

    #[Test]
    public function mayday_landet_im_einsatzprotokoll(): void
    {
        $truppId = $this->trupp();
        $this->event($truppId, 'mayday', ['note' => json_encode(['member' => 'Krause, J', 'standort' => '1. OG', 'lage' => 'eingeklemmt'])]);

        $this->assertTrue(Capsule::table('intra_fire_incident_log')
            ->where('incident_id', $this->incidentId)
            ->where('action_type', 'asu_mayday')
            ->where('action_description', 'Atemschutz: AT 1/42-1 MAYDAY')
            ->exists());
    }

    #[Test]
    public function ein_fremdes_fahrzeug_sieht_den_einsatz_nicht(): void
    {
        $other = $this->incident();

        $this->assertSame(403, $this->get('/api/fire/asu/state', ['query' => ['incident_id' => (string) $other]])->status);
        $this->assertSame(403, $this->post('/api/fire/asu/trupp', [
            'incident_id' => (string) $other, 'name' => 'X', 'role' => 'angriff', 'expected_minutes' => '30',
            'members' => [['position' => 'tf', 'name' => 'A']],
        ])->status);
    }

    #[Test]
    public function ein_abgeschlossener_einsatz_ist_nur_lesbar(): void
    {
        $truppId = $this->trupp();
        Capsule::table('intra_fire_incidents')->where('id', $this->incidentId)->update(['finalized' => 1]);

        $this->assertSame(409, $this->event($truppId, 'draussen')->status);
        $this->assertSame(200, $this->get('/api/fire/asu/state', ['query' => ['incident_id' => (string) $this->incidentId]])->status);
    }

    #[Test]
    public function der_bericht_zeigt_die_registrierung(): void
    {
        $truppId = $this->trupp();
        $this->event($truppId, 'angeschlossen', ['bar' => '300']);
        $this->event($truppId, 'hinweis_13');

        $page = $this->get('/firetab/view', ['query' => ['id' => (string) $this->incidentId, 'tab' => 'bericht']]);

        $this->assertOk($page);
        $this->assertBodyContains('id="table-asu-registration"', $page);
        $this->assertBodyContains('Weber, M, Krause, J', $page);
        $this->assertBodyContains(' · 300 bar', $page);
    }
}
