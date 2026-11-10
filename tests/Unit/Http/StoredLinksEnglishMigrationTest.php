<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StoredLinksEnglishMigrationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/database/migrations/20261110500003_rewrite_stored_links_english.php';
    }

    #[Test]
    #[DataProvider('links')]
    public function schreibt_alte_links_um(string $link, ?string $expected, string $base = '/'): void
    {
        $this->assertSame($expected, \RewriteStoredLinksEnglish::translate($link, $base));
    }

    /** @return array<string, array{0:string, 1:?string, 2?:string}> */
    public static function links(): array
    {
        return [
            'Antrag'                  => ['/forms/view?antrag=D0000007', '/forms/view?form=D0000007'],
            'Antrag unter altem Pfad' => ['/antrag/view?antrag=7', '/forms/view?form=7'],
            'Antragstyp'              => ['/forms/create?typ=3', '/forms/create?type=3'],
            'Postfach'                => ['/mail/inbox/12?postfach=4', '/mail/inbox/12?mailbox=4'],
            'Archiv mit Filter'       => ['/personnel/list?q=a&archiv=1', '/personnel/list?q=a&archived=1'],
            'MANV-Lage'               => ['/mci/resources?lage_id=5', '/mci/resources?situation_id=5'],
            'alter Pfad, Anker'       => ['/enotf/fahrtenbuch#heute', '/enotf/logbook#heute'],
            'typ an anderer Seite'    => ['/settings/enotf/pois?typ=Krankenhaus', null],
            'schon englisch'          => ['/forms/view?form=7', null],
            'fremder Host'            => ['https://example.org/forms/view?antrag=7', null],
            'Unterordner'             => ['/intra/forms/view?antrag=7', '/intra/forms/view?form=7', '/intra/'],
            'außerhalb des Ordners'   => ['/forms/view?antrag=7', null, '/intra/'],
        ];
    }
}
