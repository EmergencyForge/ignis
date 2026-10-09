<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Maßnahmen › Zugang: die Art öffnet erst ihre Ortsliste, die Größen eines
 * Orts erst der Klick auf den Ort (v1: zugang/1_1 vor 1_1_1).
 */
final class EnotfZugangStepsTest extends FeatureTestCase
{
    private string $enr;

    protected function setUp(): void
    {
        parent::setUp();
        SessionManager::setPinVerified(true);

        $this->enr = '78' . random_int(1000000000, 9999999999);
        Capsule::table('intra_edivi')->insert([
            'enr'              => $this->enr,
            'fzg_transp'       => 'RTW-1',
            'protokoll_status' => 0,
            'hidden'           => 0,
            'freigegeben'      => 0,
        ]);
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'Erika Muster', 'quali' => 'NotSan']], 'RTW-1');
    }

    /** @return array<string, array{string, string|null}> */
    public static function schritte(): array
    {
        return [
            'PVK zeigt nur die Orte'        => ['1', null],
            'Handrücken zeigt die Größen'   => ['2', 'Handrücken'],
            'intraossär zeigt nur die Orte' => ['11', null],
            'Tibia proximal zeigt Größen'   => ['12', 'Tibia proximal'],
        ];
    }

    #[Test]
    #[DataProvider('schritte')]
    public function q_oeffnet_den_passenden_schritt(string $q, ?string $ort): void
    {
        $page = $this->get('/enotf/p/' . $this->enr . '/massnahmen', ['query' => ['t' => 'zugang', 'q' => $q]]);
        $this->assertOk($page);

        $offen = array_values(array_filter(
            explode('<div class="ev2-stepwrap', $page->body),
            static fn (string $teil): bool => str_starts_with($teil, '"')
        ));
        $this->assertCount(1, $offen, 'genau ein Schritt offen');

        if ($ort === null) {
            $this->assertStringNotContainsString('zugang-checkbox', $offen[0]);
        } else {
            $this->assertStringContainsString('&quot;ort&quot;:&quot;' . $ort . '&quot;', $offen[0]);
        }
    }
}
