<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Http\Controllers\LogbookController;
use App\Models\Poi;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die Stationierung haengt am Fahrzeug und wird im Fahrtenbuch nur noch
 * angezeigt. Hier die Teile, die ohne Datenbank pruefbar sind: wie ein POI
 * beschriftet wird, welche Typen als Wache gelten, und dass ein Eintrag ohne
 * Fahrzeug seinen bisherigen Text behaelt.
 */
final class PoiTest extends TestCase
{
    #[Test]
    public function label_haengt_den_ort_an(): void
    {
        $poi = new Poi();
        $poi->name = 'Rettungswache Nord';
        $poi->ort  = 'Duisburg';

        $this->assertSame('Rettungswache Nord (Duisburg)', $poi->label());
    }

    #[Test]
    public function label_ohne_ort_bleibt_der_name(): void
    {
        $poi = new Poi();
        $poi->name = 'Rettungswache Nord';
        $poi->ort  = '   ';

        $this->assertSame('Rettungswache Nord', $poi->label());
    }

    #[Test]
    public function als_wache_gelten_rettungs_und_feuerwache(): void
    {
        $this->assertSame(['Rettungswache', 'Feuerwache'], Poi::STATIONIERUNGS_TYPEN);
    }

    /**
     * Eintraege ohne Fahrzeug gibt es im Altbestand. Die duerfen ihren Text
     * nicht verlieren, nur weil er jetzt aus dem Fahrzeug kommen soll.
     */
    #[Test]
    public function ohne_fahrzeug_bleibt_der_bisherige_text_stehen(): void
    {
        $this->assertSame('Altes Gerätehaus', $this->stationierungFuer(null, 'Altes Gerätehaus'));
        $this->assertSame('Altes Gerätehaus', $this->stationierungFuer(0, 'Altes Gerätehaus'));
    }

    private function stationierungFuer(?int $vehicleId, string $bisher): string
    {
        $m = new \ReflectionMethod(LogbookController::class, 'stationierungFuer');

        return (string) $m->invoke(null, $vehicleId, $bisher);
    }
}
