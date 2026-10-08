<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Logbook\Models\LogbookEntry;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * POST /logbook/actions liest das Rücksprungziel über ReturnToRequest und
 * die Kennung beim Löschen über DeleteFahrtRequest. Das Ziel gilt auch,
 * wenn das Formular abgewiesen wird.
 */
final class LogbookActionsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['logbook.manage', 'logbook.view'], 'cirs_username' => $user->username]);
    }

    /**
     * Meldung des letzten Requests, danach leer, damit die nächste nicht die alte sieht.
     *
     * @phpstan-impure
     */
    private function flash(): ?string
    {
        $text = $_SESSION['flash']['text'] ?? null;
        unset($_SESSION['flash']);

        return is_string($text) ? $text : null;
    }

    private function fahrt(): int
    {
        $fahrzeug = FixtureFactory::fahrzeug();

        return (int) LogbookEntry::query()->create([
            'vehicle_id'         => $fahrzeug['id'],
            'vehicle_identifier' => $fahrzeug['identifier'],
            'datum'              => '2026-02-10',
            'abfahrt'            => '08:15:00',
            'fahrttyp'           => 'dienstfahrt',
            'fahrer_name'        => 'Erika Muster',
            'source'             => 'admin',
        ])->id;
    }

    #[Test]
    public function fahrt_anlegen_fuehrt_zurueck_zum_firetab(): void
    {
        $fahrzeug = FixtureFactory::fahrzeug();

        $response = $this->post('/logbook/actions', [
            'action'             => 'create',
            'return_to'          => 'firetab',
            'datum'              => '09.10.2026',
            'abfahrt'            => '10:00',
            'vehicle_id'         => (string) $fahrzeug['id'],
            'vehicle_identifier' => $fahrzeug['identifier'],
            'fahrer_name'        => 'Erika Muster',
            'fahrttyp'           => 'dienstfahrt',
        ]);

        $this->assertRedirect($response, '/firetab/logbook');
        $this->assertTrue(Capsule::table('intra_fahrtenbuch')->where('vehicle_id', $fahrzeug['id'])->where('datum', '2026-10-09')->exists());
    }

    #[Test]
    public function abgewiesene_fahrt_fuehrt_trotzdem_zum_enotf_zurueck(): void
    {
        $response = $this->post('/logbook/actions', [
            'action'             => 'create',
            'return_to'          => 'enotf',
            'datum'              => 'gestern',
            'abfahrt'            => '10:00',
            'vehicle_identifier' => 'x',
            'fahrer_name'        => 'Erika Muster',
            'fahrttyp'           => 'dienstfahrt',
        ]);

        $this->assertRedirect($response, '/enotf/fahrtenbuch');
        // Das Rücksprungziel wird vor dem Formular gelesen und räumt die
        // alte Eingabe deshalb nicht weg
        $this->assertSame('Erika Muster', $_SESSION['old_input']['fahrer_name'] ?? null);
    }

    #[Test]
    public function fahrt_loeschen(): void
    {
        $id = $this->fahrt();

        $response = $this->post('/logbook/actions', ['action' => 'delete', 'id' => (string) $id, 'return_to' => 'admin']);

        $this->assertRedirect($response, '/logbook/index');
        $this->assertFalse(LogbookEntry::query()->where('id', $id)->exists());
    }

    #[Test]
    public function loeschen_ohne_gueltige_kennung_aendert_nichts(): void
    {
        $id = $this->fahrt();

        $this->post('/logbook/actions', ['action' => 'delete', 'id' => 'abc']);
        $meldungen = [$this->flash()];

        $this->post('/logbook/actions', ['action' => 'delete', 'id' => (string) $id, 'alle' => '1']);
        $meldungen[] = $this->flash();

        $this->assertSame(['Ungültige ID.', 'Ungültige ID.'], $meldungen);

        $this->assertTrue(LogbookEntry::query()->where('id', $id)->exists());
    }
}
