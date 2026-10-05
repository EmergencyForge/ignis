<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
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
    public function arrival_board_polls_without_login(): void
    {
        $ziel = 'poi_test_' . uniqid();
        Capsule::table('intra_edivi_prereg')->insert([
            'fahrzeug' => 'RTW 1',
            'ziel'     => $ziel,
            'arrival'  => date('Y-m-d H:i:s', time() + 600),
        ]);

        $response = $this->get('/api/enotf/prereg', ['query' => ['klinik' => $ziel]]);

        $this->assertStatus(200, $response);
        $this->assertSame(['RTW 1'], array_column($this->assertJsonResponse($response)['data'], 'fahrzeug'));
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }
}
