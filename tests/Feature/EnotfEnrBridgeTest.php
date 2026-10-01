<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die enrbridge von eNOTF v1 legt Protokolle nur noch mit einer ENR im
 * Format von eNOTF v2 an (Ziffern und Unterstrich, höchstens 40 Zeichen).
 * Sie bleibt ohne Login erreichbar.
 */
final class EnotfEnrBridgeTest extends FeatureTestCase
{
    private const URL = '/assets/functions/enotf/enrbridge';

    #[Test]
    public function gueltige_enr_legt_das_protokoll_an(): void
    {
        $fahrzeug = FixtureFactory::fahrzeug();
        $enr = '77' . random_int(1000000000, 9999999999) . '_1';

        $response = $this->post(
            self::URL,
            ['action' => 'openOrCreate', 'enr' => $enr, 'prot_by' => '0'],
            ['session' => ['protfzg' => $fahrzeug['identifier']]],
        );

        $this->assertRedirect($response, 'enr=' . $enr);
        $this->assertSame($fahrzeug['identifier'], Capsule::table('intra_edivi')->where('enr', $enr)->value('fzg_transp'));
    }

    /** @return array<string, array{mixed}> */
    public static function ungueltigeEnr(): array
    {
        return [
            'Markup'        => ['<script>alert(1)</script>'],
            'zu lang'       => [str_repeat('1', 41)],
            'leer'          => [''],
            'Zeilenumbruch' => ["123\n"],
            'Array'         => [['1']],
        ];
    }

    #[Test]
    #[DataProvider('ungueltigeEnr')]
    public function ungueltige_enr_wird_abgewiesen(mixed $enr): void
    {
        $vorher = Capsule::table('intra_edivi')->count();

        $response = $this->post(self::URL, ['action' => 'openOrCreate', 'enr' => $enr, 'prot_by' => '0']);

        $this->assertStatus(422, $response);
        $this->assertSame($vorher, Capsule::table('intra_edivi')->count());
    }
}
