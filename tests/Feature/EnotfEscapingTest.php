<?php

declare(strict_types=1);

namespace Tests\Feature;

use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * eNOTF v1 gibt ENR und Patientendaten escaped aus. Eine ENR mit Markup
 * kommt nicht mehr über die enrbridge, wohl aber über den EMD-Sync und aus
 * Altbeständen.
 */
final class EnotfEscapingTest extends FeatureTestCase
{
    private const ENR = '7"><img src=x onerror=alert(1)>';

    private string $fahrzeug;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->fahrzeug = FixtureFactory::fahrzeug()['identifier'];
        $this->actingAs($user->id, [
            'permissions'   => ['full_admin'],
            'cirs_username' => $user->username,
            'username'      => $user->username,
            'fahrername'    => 'Erika Muster',
            'protfzg'       => $this->fahrzeug,
        ]);

        Capsule::table('intra_edivi')->insert([
            'enr'              => self::ENR,
            'patname'          => '<script>alert("patname")</script>',
            'anmerkungen'      => '</textarea><script>alert("anmerkungen")</script>',
            'fzg_transp'       => $this->fahrzeug,
            'fzg_transp_perso' => '"><script>alert("perso")</script>',
            'protokoll_status' => 0,
            'hidden'           => 0,
            'hidden_user'      => 0,
            'freigegeben'      => 0,
            'prot_by'          => 0,
        ]);
    }

    private function assertNothingRaw(Response $response): void
    {
        $this->assertOk($response);
        $this->assertBodyNotContains('<img src=x onerror', $response);
        $this->assertBodyNotContains('<script>alert(', $response);
    }

    #[Test]
    public function uebersicht_escaped_enr_und_patientenname(): void
    {
        $response = $this->get('/enotf/overview');

        $this->assertNothingRaw($response);
        $this->assertBodyContains('#7&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', $response);
        $this->assertBodyContains('&lt;script&gt;alert(&quot;patname&quot;)&lt;/script&gt;', $response);
    }

    #[Test]
    public function druckansicht_escaped_felder_und_textarea(): void
    {
        $response = $this->get('/enotf/print', ['query' => ['enr' => self::ENR]]);

        $this->assertNothingRaw($response);
        $this->assertBodyContains('&lt;/textarea&gt;&lt;script&gt;alert(&quot;anmerkungen&quot;)', $response);
        $this->assertBodyContains('value="&quot;&gt;&lt;script&gt;alert(&quot;perso&quot;)', $response);
    }

    #[Test]
    public function rettdaten_spiegelt_die_enr_nicht_roh(): void
    {
        $response = $this->get('/enotf/protokoll/rettdaten/index.php', ['query' => ['enr' => self::ENR]]);

        $this->assertNothingRaw($response);
        $this->assertBodyContains('value="7&quot;&gt;&lt;img src=x onerror=alert(1)&gt;" readonly', $response);
    }
}
