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
 * eNOTF v1 gibt ENR und Patientendaten escaped aus. Eine ENR mit Markup
 * kommt nicht mehr über die enrbridge, wohl aber über den EMD-Sync und aus
 * Altbeständen.
 */
final class EnotfEscapingTest extends FeatureTestCase
{
    private const ENR = '7"><img src=x onerror=alert(1)>';

    private string $fahrzeug;

    private int $protokollId;

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

        $this->protokollId = (int) Capsule::table('intra_edivi')->insertGetId([
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
    public function alte_protokoll_adresse_leitet_kodiert_um(): void
    {
        $response = $this->get('/enotf/protokoll/rettdaten/index', ['query' => ['enr' => self::ENR]]);

        $this->assertStatus(301, $response);
        $this->assertSame('/enotf/p/' . rawurlencode(self::ENR) . '/rettdaten', $response->headers['Location'] ?? null);
    }

    /** @return array<string, array{string, bool}> */
    public static function qmStatus(): array
    {
        $style = ' style="line-height: var(--bs-body-line-height); border-radius: 0;"';

        return [
            'Chip'                => ['<span class="ignis-chip ignis-chip--warn">in Prüfung</span>', true],
            'Badge bis 04/2026'   => ['<span class="badge text-bg-warning"' . $style . '>in Prüfung</span>', true],
            'Badge bis 04/2025'   => ['<span class="badge bg-success"' . $style . '>Freigegeben</span>', true],
            'Badge ohne Farbe'    => ['<span class="badge"' . $style . '>Ungesehen</span>', true],
            'Badge ohne Style'    => ['<span class="badge text-bg-dark">Ausgeblendet</span>', true],
            'Markup'              => ['<img src=x onerror=alert(1)>', false],
            'Badge mit Attribut'  => ['<span class="badge" onmouseover="alert(1)">Ungesehen</span>', false],
        ];
    }

    #[Test]
    #[DataProvider('qmStatus')]
    public function qm_log_laesst_nur_status_chips_und_alte_badges_als_markup(string $kommentar, bool $markup): void
    {
        Capsule::table('intra_edivi_qmlog')->insert([
            'protokoll_id' => $this->protokollId,
            'kommentar'    => $kommentar,
            'bearbeiter'   => 'QM',
            'log_aktion'   => 1,
        ]);

        $response = $this->get('/enotf/admin/qm-log-modal', ['query' => ['id' => (string) $this->protokollId]]);

        $this->assertOk($response);
        $this->assertBodyContains("<p class='mb-0'>" . ($markup ? $kommentar : e($kommentar)) . '</p>', $response);
    }
}
