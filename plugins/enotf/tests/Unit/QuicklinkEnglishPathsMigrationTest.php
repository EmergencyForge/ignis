<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QuicklinkEnglishPathsMigrationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/migrations/20261110400001_update_intra_enotf_quicklinks_10112026_english_paths.php';
    }

    #[Test]
    #[DataProvider('urls')]
    public function schreibt_alte_pfade_um(string $url, ?string $expected): void
    {
        $this->assertSame($expected, \UpdateIntraEnotfQuicklinks10112026EnglishPaths::translate($url));
    }

    /** @return array<string, array{0:string, 1:?string}> */
    public static function urls(): array
    {
        return [
            'Standard-Quicklink'          => ['../enotf/fahrzeuginfo', '../enotf/vehicle-info'],
            'relativ zur Übersicht'       => ['fahrzeuginfo.php', 'vehicle-info'],
            'absolut mit Query'           => ['/enotf/schnittstelle/index.php?klinik=7', '/enotf/monitor/index?klinik=7'],
            'oberstes Segment'            => ['../kalender', '../calendar'],
            'relativ aus dem eNOTF heraus' => ['fahrtenbuch', 'logbook'],
            'schon englisch'              => ['../enotf/vehicle-info', null],
            'Administration'              => ['../', null],
            'fremder Host'                => ['https://example.com/enotf/fahrzeuginfo', null],
            'ohne Schema'                 => ['//example.com/kalender', null],
            'leer'                        => ['', null],
        ];
    }
}
