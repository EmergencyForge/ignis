<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Crew-Anmeldung im eNOTF: Die Seite bringt je Mitarbeiter die hinterlegte
 * RD-Quali mit, damit das Quali-Feld nach der Namenswahl vorbelegt wird.
 */
final class EnotfCrewQualiTest extends FeatureTestCase
{
    private string $suffix;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin']]);
        $this->suffix = substr(uniqid(), -6);
    }

    private function quali(string $abkuerzung, bool $none = false): int
    {
        return (int) Capsule::table('intra_mitarbeiter_rdquali')->insertGetId([
            'priority'   => 900,
            'name'       => 'Testquali ' . $abkuerzung,
            'name_m'     => 'Testquali ' . $abkuerzung,
            'name_w'     => 'Testquali ' . $abkuerzung,
            'abkuerzung' => $abkuerzung,
            'none'       => $none ? 1 : 0,
            'trainable'  => 0,
        ]);
    }

    // Nur v1: eNOTF v2 ist ab Werk aus und hat in der Test-DB keine Routen.
    // Controller und JSON-Ausgabe sind in v2 gleich gebaut.
    #[Test]
    public function login_liefert_die_rd_quali_je_mitarbeiter(): void
    {
        $nfs = 'N' . $this->suffix;
        $other = 'O' . $this->suffix;
        $keine = 'K' . $this->suffix;
        $nfsId = $this->quali($nfs);
        $otherId = $this->quali($other);
        $keineId = $this->quali($keine, true);

        FixtureFactory::personnel(['fullname' => 'Erika ' . $this->suffix, 'qualird' => $nfsId]);
        FixtureFactory::personnel(['fullname' => 'Ohne ' . $this->suffix, 'qualird' => $keineId]);
        // Gleicher Name, verschiedene Quali: nicht eindeutig, keine Vorauswahl
        FixtureFactory::personnel(['fullname' => 'Doppelt ' . $this->suffix, 'qualird' => $nfsId]);
        FixtureFactory::personnel(['fullname' => 'Doppelt ' . $this->suffix, 'qualird' => $otherId]);

        $response = $this->get('/enotf/login');

        $this->assertOk($response);
        $this->assertBodyContains('"Erika ' . $this->suffix . '":"' . $nfs . '"', $response);
        $this->assertBodyContains('<option value="' . $nfs . '"', $response);
        $this->assertBodyNotContains('"Ohne ' . $this->suffix . '":', $response);
        $this->assertBodyNotContains('"Doppelt ' . $this->suffix . '":', $response);
    }
}
