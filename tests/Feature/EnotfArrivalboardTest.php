<?php

declare(strict_types=1);

namespace Tests\Feature;

use EmergencyForge\Http\Response;
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
    private string $enr = '';

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
        $vorher = Capsule::table('intra_edivi_prereg')->count();

        $response = $this->voranmelden($abweichung);

        // Abgewiesen heißt: kein Redirect, das Formular steht wieder da, kein Eintrag.
        $this->assertOk($response);
        $this->assertSame($vorher, Capsule::table('intra_edivi_prereg')->count());
    }

    /** @return array<string, array{array<string, mixed>, array<string, string>}> */
    public static function gueltigeVoranmeldung(): array
    {
        return [
            'wie aus dem Formular' => [[], ['arrival' => '2026-03-05 12:30:00', 'alter' => '54', 'gcs' => '15', 'priority' => '1']],
            'Datum als d.m.Y'      => [['arrival_date' => '5.3.2026'], ['arrival' => '2026-03-05 12:30:00']],
            'GCS leer, Alter 0'    => [['_GCS_' => '', '_AGE_' => '0'], ['gcs' => '', 'alter' => '0']],
            'Freitext leer'        => [['text' => ''], ['text' => '']],
            'Umlaute bis zur Höchstlänge' => [
                ['diagnose' => str_repeat('ü', 255), 'text' => str_repeat('ä', 1000)],
                ['diagnose' => str_repeat('ü', 255), 'text' => str_repeat('ä', 1000)],
            ],
            'Grenzwerte' => [
                ['arrival_time' => '00:00', '_GCS_' => '3', '_AGE_' => '150', 'priority' => '2', 'kreislauf' => '0', 'intubiert' => '1'],
                ['arrival' => '2026-03-05 00:00:00', 'gcs' => '3', 'alter' => '150', 'priority' => '2', 'kreislauf' => '0', 'intubiert' => '1'],
            ],
        ];
    }

    /**
     * @param array<string, mixed>  $abweichung
     * @param array<string, string> $erwartet
     */
    #[Test]
    #[DataProvider('gueltigeVoranmeldung')]
    public function gueltige_voranmeldung_wird_gespeichert(array $abweichung, array $erwartet): void
    {
        $vorher = Capsule::table('intra_edivi_prereg')->count();

        $response = $this->voranmelden($abweichung);

        $this->assertRedirect($response, '/enotf/p/' . $this->enr);
        $this->assertSame($vorher + 1, Capsule::table('intra_edivi_prereg')->count());
        $eintrag = (array) Capsule::table('intra_edivi_prereg')->orderByDesc('id')->first();
        foreach ($erwartet as $spalte => $wert) {
            $this->assertSame($wert, (string) $eintrag[$spalte], $spalte);
        }
    }

    /**
     * Schickt die Voranmeldung zu einem neuen Protokoll ab: mit den Werten,
     * die das Formular im Browser schickt, und den Abweichungen darüber.
     *
     * @param array<string, mixed> $abweichung
     */
    private function voranmelden(array $abweichung): Response
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        $this->enr = 'T-' . uniqid();
        Capsule::table('intra_edivi')->insert(['enr' => $this->enr, 'patname' => 'Max Muster', 'protokoll_status' => 0, 'hidden' => 0, 'freigegeben' => 0]);

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
            'arrival_date' => '2026-03-05',
            'arrival_time' => '12:30',
            'priority'     => '1',
        ], $abweichung);

        return $this->post('/enotf/schnittstelle/voranmeldung', $body, ['query' => ['enr' => $this->enr]]);
    }
}
