<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die eNOTF-Voranmeldung schreibt keine Formulardaten mehr in eine
 * Logdatei im Template-Ordner.
 *
 * Vorher landete jeder POST per print_r($_POST) samt Diagnose und
 * Freitext in `schnittstelle/php_errors.log` neben dem Template.
 */
final class EnotfVoranmeldungLogTest extends FeatureTestCase
{
    private const LOG = __DIR__ . '/../../plugins/enotf/templates/enotf/schnittstelle/php_errors.log';

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(self::LOG);
    }

    protected function tearDown(): void
    {
        @unlink(self::LOG);
        parent::tearDown();
    }

    #[Test]
    public function post_schreibt_keine_formulardaten_ins_log(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        $enr = 'T-' . uniqid();
        Capsule::table('intra_edivi')->insert(['enr' => $enr, 'patname' => 'Max Muster', 'protokoll_status' => 0, 'hidden' => 0, 'freigegeben' => 0]);

        // Pflichtfelder fehlen: die Seite rendert den Fehler statt umzuleiten.
        $body = ['new' => '1', 'text' => 'Patient Max Muster, Sturz'];

        // request() statt post(): post() legt den Token bei.
        $this->assertStatus(403, $this->request('POST', '/enotf/schnittstelle/voranmeldung', ['query' => ['enr' => $enr], 'post' => $body]));

        $response = $this->post('/enotf/schnittstelle/voranmeldung', $body, ['query' => ['enr' => $enr]]);

        $this->assertOk($response);
        $this->assertFileDoesNotExist(self::LOG);
    }
}
