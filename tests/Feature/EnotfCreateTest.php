<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Crew legt Protokolle nur mit einer ENR aus Ziffern und Unterstrich
 * an, höchstens 40 Zeichen. Ohne Crew-Sitzung geht es zur Anmeldung.
 */
final class EnotfCreateTest extends FeatureTestCase
{
    private const URL = '/enotf/create';

    protected function setUp(): void
    {
        parent::setUp();
        // Unabhängig davon, ob die Datenbank ENOTF_USE_PIN eingeschaltet hat
        SessionManager::setPinVerified(true);
    }

    private function crew(string $fahrzeug): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'Erika Muster', 'quali' => 'NotSan']], $fahrzeug);
    }

    #[Test]
    public function gueltige_enr_legt_das_protokoll_an(): void
    {
        $fahrzeug = FixtureFactory::fahrzeug();
        $this->crew($fahrzeug['identifier']);
        $enr = '77' . random_int(1000000000, 9999999999) . '_1';

        $response = $this->post(self::URL, ['enr' => $enr, 'prot_by' => '0']);

        $this->assertRedirect($response, '/enotf/p/' . $enr);
        $this->assertSame($fahrzeug['identifier'], Capsule::table('intra_edivi')->where('enr', $enr)->value('fzg_transp'));
    }

    /** @return array<string, array{mixed}> */
    public static function ungueltigeEnr(): array
    {
        return [
            'Markup'        => ['<script>alert(1)</script>'],
            'zu lang'       => [str_repeat('1', 41)],
            'leer'          => [''],
            'Zeilenumbruch' => ["12\n3"],
            'Array'         => [['1']],
        ];
    }

    #[Test]
    #[DataProvider('ungueltigeEnr')]
    public function ungueltige_enr_wird_abgewiesen(mixed $enr): void
    {
        $this->crew(FixtureFactory::fahrzeug()['identifier']);
        $vorher = Capsule::table('intra_edivi')->count();

        $response = $this->post(self::URL, ['enr' => $enr, 'prot_by' => '0']);

        $this->assertRedirect($response, 'error=invalid_enr');
        $this->assertSame($vorher, Capsule::table('intra_edivi')->count());
    }

    #[Test]
    public function ohne_crew_geht_es_zur_anmeldung(): void
    {
        $response = $this->post(self::URL, ['enr' => '771234567890', 'prot_by' => '0']);

        $this->assertRedirect($response, '/enotf/login');
    }
}
