<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * eNOTF v1: Vitalwerte im Verlauf löschen nur per POST mit CSRF-Token.
 *
 * Vorher löschte `verlauf/list?enr=…&action=delete&id=…` per GET-Link, und
 * die ENR stand unescaped im href. Gelöscht wird weiter soft (`geloescht`),
 * hartes Löschen blockiert ein Trigger auf der Tabelle.
 */
final class EnotfVitalDeleteTest extends FeatureTestCase
{
    private const PATH = '/enotf/protokoll/verlauf/list';

    private function login(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username, 'username' => $user->username]);
    }

    private function protocol(string $enr): void
    {
        Capsule::table('intra_edivi')->insert([
            'enr'              => $enr,
            'patname'          => 'Max Muster',
            'protokoll_status' => 0,
            'hidden'           => 0,
            'freigegeben'      => 0,
        ]);
    }

    private function vital(string $enr): int
    {
        return (int) Capsule::table('intra_edivi_vitalparameter_einzelwerte')->insertGetId([
            'enr'               => $enr,
            'zeitpunkt'         => date('Y-m-d H:i:s'),
            'parameter_name'    => 'Herzfrequenz',
            'parameter_wert'    => "80'",
            'parameter_einheit' => '/min',
            'erstellt_von'      => 'Test',
        ]);
    }

    private function deleted(int $id): int
    {
        return (int) Capsule::table('intra_edivi_vitalparameter_einzelwerte')->where('id', $id)->value('geloescht');
    }

    #[Test]
    public function vitalwert_loescht_nur_per_post_mit_token(): void
    {
        $this->login();
        $enr = 'T-' . uniqid();
        $this->protocol($enr);
        $id = $this->vital($enr);

        $this->get(self::PATH, ['query' => ['enr' => $enr, 'action' => 'delete', 'id' => (string) $id]]);
        $this->assertSame(0, $this->deleted($id));

        // request() statt post(): post() legt den Token bei.
        $body = ['action' => 'delete', 'id' => (string) $id];
        $this->assertStatus(403, $this->request('POST', self::PATH, ['query' => ['enr' => $enr], 'post' => $body]));
        $this->assertSame(0, $this->deleted($id));

        $response = $this->post(self::PATH, $body, ['query' => ['enr' => $enr]]);
        $this->assertOk($response);
        $this->assertSame(1, $this->deleted($id));
        $this->assertBodyContains('Vitalparameter erfolgreich gelöscht.', $response);
    }

    #[Test]
    public function loeschformular_escaped_enr_und_bestaetigungstext(): void
    {
        $this->login();
        $enr = 'T"><b>x</b>' . uniqid();
        $this->protocol($enr);
        $this->vital($enr);

        $response = $this->get(self::PATH, ['query' => ['enr' => $enr]]);

        $this->assertOk($response);
        $this->assertBodyContains('<form method="POST" action="?enr=' . urlencode($enr) . '"', $response);
        $this->assertBodyNotContains('action=delete&', $response);
        // Der Bestätigungstext steht als JSON-String im onsubmit: ein Apostroph
        // im Wert (&#039; wird vor dem JS wieder zu ') beendet ihn nicht mehr.
        $this->assertBodyContains('showConfirm(&quot;Parameter', $response);
    }
}
