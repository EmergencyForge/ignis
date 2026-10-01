<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Das Arrivalboard bleibt öffentlich und gibt Voranmeldungen escaped aus;
 * die Voranmeldung nimmt nur Werte an, die ihr Formular auch schicken kann.
 */
final class EnotfArrivalboardTest extends FeatureTestCase
{
    #[Test]
    public function arrivalboard_ist_oeffentlich_und_escaped_die_voranmeldung(): void
    {
        Capsule::table('intra_edivi_prereg')->insert([
            'priority'   => 1,
            'arrival'    => date('Y-m-d H:i:s', time() + 600),
            'fahrzeug'   => '"><svg onload=alert(1)>',
            'diagnose'   => '<script>alert(1)</script>',
            'geschlecht' => 0,
            'alter'      => '<b>50</b>',
            'text'       => '<img src=x onerror=alert(1)>',
            'kreislauf'  => 0,
            'gcs'        => '<i>15</i>',
            'intubiert'  => 1,
            'ziel'       => 'poi_1',
            'active'     => 1,
        ]);

        $response = $this->get('/enotf/schnittstelle');

        $this->assertOk($response);
        foreach (['<script>alert(1)', '<img src=x', '<svg onload', '<b>50</b>', '<i>15</i>'] as $payload) {
            $this->assertBodyNotContains($payload, $response);
        }
        $this->assertBodyContains('&lt;script&gt;alert(1)&lt;/script&gt;', $response);
        $this->assertBodyContains('&lt;img src=x onerror=alert(1)&gt;', $response);
        // Die Symbole bleiben Markup.
        $this->assertBodyContains('<span style="color:red">instabil</span>', $response);
    }

    #[Test]
    public function klinik_parameter_bleibt_im_script_ein_string(): void
    {
        $response = $this->get('/enotf/schnittstelle', ['query' => ['klinik' => '</script><script>alert(1)</script>']]);

        $this->assertOk($response);
        $this->assertBodyNotContains('<script>alert(1)', $response);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function ungueltigeVoranmeldung(): array
    {
        return [
            'Priorität'        => [['priority' => '7']],
            'Kreislauf'        => [['kreislauf' => 'stabil']],
            'GCS als Markup'   => [['_GCS_' => '<b>15</b>']],
            'GCS zu klein'     => [['_GCS_' => '2']],
            'Alter negativ'    => [['_AGE_' => '-1']],
            'Alter mit Zeilenumbruch' => [['_AGE_' => "54\n"]],
            'Priorität mit Zeilenumbruch' => [['priority' => "1\n"]],
            'Uhrzeit'          => [['arrival_time' => '25:00']],
            'Datum'            => [['arrival_date' => '2026-02-30']],
            'Diagnose zu lang' => [['diagnose' => str_repeat('x', 256)]],
            'Text zu lang'     => [['text' => str_repeat('x', 1001)]],
            'Fahrzeug als Array' => [['fahrzeug' => ['RTW 1']]],
        ];
    }

    /** @param array<string, mixed> $abweichung */
    #[Test]
    #[DataProvider('ungueltigeVoranmeldung')]
    public function ungueltige_voranmeldung_wird_abgewiesen(array $abweichung): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        $enr = 'T-' . uniqid();
        Capsule::table('intra_edivi')->insert(['enr' => $enr, 'patname' => 'Max Muster', 'protokoll_status' => 0, 'hidden' => 0, 'freigegeben' => 0]);
        $vorher = Capsule::table('intra_edivi_prereg')->count();

        $body = array_merge([
            'new'          => '1',
            'ziel'         => 'poi_1',
            'fahrzeug'     => 'RTW 1',
            'diagnose'     => 'ACS / STEMI',
            'text'         => 'Patient wach, Schmerz seit 1 h',
            '_AGE_'        => '54',
            '_GCS_'        => '15',
            'kreislauf'    => '1',
            'intubiert'    => '0',
            'arrival_date' => date('Y-m-d'),
            'arrival_time' => '12:30',
            'priority'     => '1',
        ], $abweichung);

        $response = $this->post('/enotf/schnittstelle/voranmeldung', $body, ['query' => ['enr' => $enr]]);

        // Abgewiesen heißt: kein Redirect, das Formular steht wieder da, kein Eintrag.
        $this->assertOk($response);
        $this->assertSame($vorher, Capsule::table('intra_edivi_prereg')->count());
    }
}
