<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Die Ankunftstafel der Klinik ist öffentlich und pollt prereg. Hing die
 * Route hinter dem Konto, blieb die Tafel ohne Anmeldung auf dem Stand vom
 * Seitenaufruf stehen.
 */
final class EnotfPreregApiTest extends FeatureTestCase
{
    #[Test]
    #[DataProvider('hospitalParameter')]
    public function arrival_board_polls_without_login(string $parameter): void
    {
        $ziel = 'poi_test_' . uniqid();
        Capsule::table('intra_edivi_prereg')->insert([
            'fahrzeug' => 'RTW 1',
            'ziel'     => $ziel,
            'arrival'  => date('Y-m-d H:i:s', time() + 600),
        ]);

        $response = $this->get('/api/enotf/prereg', ['query' => [$parameter => $ziel]]);

        $this->assertStatus(200, $response);
        $this->assertSame(['RTW 1'], array_column($this->assertJsonResponse($response)['data'], 'fahrzeug'));
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    /** @return array<string, array{string}> */
    public static function hospitalParameter(): array
    {
        // klinik ist der alte Name, ef_klinikmonitor schickt ihn noch
        return ['hospital' => ['hospital'], 'klinik' => ['klinik']];
    }
}
