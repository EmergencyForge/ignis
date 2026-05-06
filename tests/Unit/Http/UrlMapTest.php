<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\UrlMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UrlMap deckt das deutsch->englisch URL-Mapping ab. Die Tests
 * sichern Top-Segment-, API- und Settings-Cases einschliesslich
 * Trailing-Slash-Erhalt und Identitaet (kanonische Pfade aendern sich nicht).
 */
class UrlMapTest extends TestCase
{
    #[Test]
    #[DataProvider('topLevelTranslations')]
    public function translates_top_level_segments(string $input, ?string $expected): void
    {
        $this->assertSame($expected, UrlMap::translatePath($input));
    }

    /** @return array<string, array{0:string, 1:?string}> */
    public static function topLevelTranslations(): array
    {
        return [
            'kalender → calendar'                 => ['/kalender', '/calendar'],
            'kalender mit Trailing-Slash'         => ['/kalender/', '/calendar/'],
            'kalender/view bewahrt Sub-Pfad'      => ['/kalender/view', '/calendar/view'],
            'manv → mci'                          => ['/manv/', '/mci/'],
            'manv/board sub-pfad'                 => ['/manv/board', '/mci/board'],
            'einsatz → firetab'                   => ['/einsatz/', '/firetab/'],
            'einsatz/login-fahrzeug Sub-Pfad bleibt deutsch (nicht im Mapping)' => ['/einsatz/login-fahrzeug', '/firetab/login-fahrzeug'],
            'benutzer → users'                    => ['/benutzer/list', '/users/list'],
            'antrag → forms'                      => ['/antrag/create', '/forms/create'],
            'antraege Plural → forms'             => ['/antraege/admin/list', '/forms/admin/list'],
            'mitarbeiter → personnel'             => ['/mitarbeiter/profile', '/personnel/profile'],
            'fahrtenbuch → logbook'               => ['/fahrtenbuch', '/logbook'],
            'benachrichtigungen → notifications'  => ['/benachrichtigungen', '/notifications'],
            'wissensdb → lexicon'                 => ['/wissensdb/foo', '/lexicon/foo'],
            'dokumente → documents'               => ['/dokumente/foo.pdf', '/documents/foo.pdf'],
        ];
    }

    #[Test]
    #[DataProvider('apiTranslations')]
    public function translates_api_segments(string $input, ?string $expected): void
    {
        $this->assertSame($expected, UrlMap::translatePath($input));
    }

    /** @return array<string, array{0:string, 1:?string}> */
    public static function apiTranslations(): array
    {
        return [
            'api/kalender/events'    => ['/api/kalender/events', '/api/calendar/events'],
            'api/v1/kalender/events' => ['/api/v1/kalender/events', '/api/v1/calendar/events'],
            'api/v1/kalender/ical'   => ['/api/v1/kalender/ical/abc123', '/api/v1/calendar/ical/abc123'],
            'api ohne sub-segment'   => ['/api', null],
        ];
    }

    #[Test]
    #[DataProvider('settingsTranslations')]
    public function translates_settings_sub_segments(string $input, ?string $expected): void
    {
        $this->assertSame($expected, UrlMap::translatePath($input));
    }

    /** @return array<string, array{0:string, 1:?string}> */
    public static function settingsTranslations(): array
    {
        return [
            'fahrzeuge → vehicles'                          => ['/settings/fahrzeuge/index', '/settings/vehicles/index'],
            'fahrzeuge + defekte (zwei Sub-Segmente)'       => ['/settings/fahrzeuge/defekte/index', '/settings/vehicles/defects/index'],
            'fahrzeuge + beladelisten → vehicles + vehload' => ['/settings/fahrzeuge/beladelisten/index', '/settings/vehicles/vehload/index'],
            'personal → personnel'                          => ['/settings/personal/dienstgrade/index', '/settings/personnel/ranks/index'],
            'personal + qualifw → personnel + fdskills'     => ['/settings/personal/qualifw/index', '/settings/personnel/fdskills/index'],
            'personal + qualird → personnel + ambskills'    => ['/settings/personal/qualird/index', '/settings/personnel/ambskills/index'],
            'personal + qualifd → personnel + specialties'  => ['/settings/personal/qualifd/index', '/settings/personnel/specialties/index'],
            'medikamente → medications'                     => ['/settings/medikamente/index', '/settings/medications/index'],
            'antrag-Sub → forms-Sub'                        => ['/settings/antrag/list', '/settings/forms/list'],
        ];
    }

    #[Test]
    #[DataProvider('noOpInputs')]
    public function returns_null_for_canonical_or_unknown_paths(string $input): void
    {
        $this->assertNull(UrlMap::translatePath($input));
    }

    /** @return array<string, array{0:string}> */
    public static function noOpInputs(): array
    {
        return [
            'root'                              => ['/'],
            'leerstring'                        => [''],
            'kanonisch englisch'                => ['/calendar'],
            'kanonisch mit Slash'               => ['/calendar/'],
            'enotf bleibt unangetastet'         => ['/enotf/overview'],
            'unbekanntes Modul'                 => ['/something-else/foo'],
            'kanonisches API'                   => ['/api/calendar/events'],
            'settings ohne deutsches Sub-Segment' => ['/settings/dashboard/index'],
        ];
    }

    #[Test]
    public function translate_relative_strips_leading_slash(): void
    {
        $this->assertSame('calendar/view?id=42', UrlMap::translateRelative('kalender/view?id=42'));
        $this->assertSame('mci/board?id=7', UrlMap::translateRelative('manv/board?id=7'));
    }

    #[Test]
    public function translate_relative_returns_null_for_canonical_or_empty(): void
    {
        $this->assertNull(UrlMap::translateRelative(''));
        $this->assertNull(UrlMap::translateRelative('calendar'));
        $this->assertNull(UrlMap::translateRelative('something-else/foo?bar=1'));
    }

    #[Test]
    public function translate_relative_preserves_query_string(): void
    {
        $result = UrlMap::translateRelative('kalender/view?id=42&foo=bar');
        $this->assertSame('calendar/view?id=42&foo=bar', $result);
    }
}
