<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Documents\Editor\SystemTemplates;
use App\Documents\Editor\VariableCatalog;
use EmergencyForge\Editor\Renderer;
use EmergencyForge\Editor\SectionGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die mitgelieferten Vorlagen müssen durch denselben Renderer gehen wie
 * jedes andere Dokument. Ein Tippfehler in der Struktur soll hier auffallen
 * und nicht erst, wenn jemand eine Urkunde ausstellen will.
 *
 * Geprüft wird dreierlei: der Renderer nimmt sie ohne Fehler an, jeder
 * benutzte Platzhalter steht auch im Katalog, und der SectionGuard hält die
 * Abschnitts-Kennungen für brauchbar — ohne das ließe sich aus der Vorlage
 * kein Dokument speichern.
 */
final class SystemTemplatesTest extends TestCase
{
    /** @return iterable<string,array{string}> */
    public static function templateProvider(): iterable
    {
        foreach (array_keys(SystemTemplates::all()) as $name) {
            yield $name => [$name];
        }
    }

    /** @return array<string,mixed> */
    private function doc(string $name): array
    {
        return ['type' => 'doc', 'content' => SystemTemplates::all()[$name]['sections']];
    }

    #[Test]
    public function es_sind_die_zehn_vorlagen_des_alten_systems(): void
    {
        $this->assertSame([
            'Beförderungsurkunde',
            'Ernennungsurkunde',
            'Entlassungsurkunde',
            'Ausbildungszertifikat',
            'Lehrgangszertifikat',
            'Lehrgangszertifikat Fachdienste',
            'Schriftliche Abmahnung',
            'Vorläufige Dienstenthebung',
            'Dienstentfernung',
            'Außerordentliche Kündigung',
        ], array_keys(SystemTemplates::all()));
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function der_renderer_nimmt_die_vorlage_an(string $name): void
    {
        // Alle Platzhalter mit einem Wert versorgen, damit die Warnungen
        // nur von der Struktur kommen können.
        $variables = array_map(static fn (string $label): string => 'X', VariableCatalog::catalog());

        $result = (new Renderer())->render($this->doc($name), $variables);

        $this->assertFalse($result->error, 'Renderer-Fehler in „' . $name . '"');
        $this->assertNotSame('', $result->html);

        $structural = array_filter(
            $result->warnings,
            static fn (string $w): bool => !str_starts_with($w, 'Pflichtfeld leer: '),
        );
        $this->assertSame([], array_values($structural), 'Warnungen in „' . $name . '"');
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function die_abschnitte_taugen_als_vorlage(string $name): void
    {
        (new SectionGuard())->assertUniqueSectionIds($this->doc($name));

        $this->addToAssertionCount(1);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function jeder_platzhalter_steht_im_katalog(string $name): void
    {
        $catalog = VariableCatalog::catalog();
        $unknown = [];
        $doc     = $this->doc($name);

        array_walk_recursive(
            $doc,
            static function (mixed $value, string|int $key) use (&$unknown, $catalog): void {
                // docVariable-Knoten tragen den Namen unter attrs.name.
                if ($key === 'name' && is_string($value) && !isset($catalog[$value]) && str_contains($value, '.')) {
                    $unknown[] = $value;
                }
            },
        );

        $this->assertSame([], array_values(array_unique($unknown)), 'Unbekannte Platzhalter in „' . $name . '"');
    }

    /**
     * Was der Aussteller je Vorlage beitraegt. Die Entlassungsurkunde
     * steht bewusst ohne Eingabe da — schon die Twig-Fassung kam ohne aus,
     * alles darin steht im Mitarbeiterdatensatz.
     *
     * @return iterable<string,array{string,string}>
     */
    public static function eingabeProvider(): iterable
    {
        $erwartet = [
            'Beförderungsurkunde'             => 'field',
            'Ernennungsurkunde'               => 'field',
            'Entlassungsurkunde'              => 'nichts',
            'Ausbildungszertifikat'           => 'field',
            'Lehrgangszertifikat'             => 'field',
            'Lehrgangszertifikat Fachdienste' => 'field',
            'Schriftliche Abmahnung'          => 'free',
            'Vorläufige Dienstenthebung'      => 'free',
            'Dienstentfernung'                => 'free',
            'Außerordentliche Kündigung'      => 'free',
        ];

        foreach ($erwartet as $name => $art) {
            yield $name => [$name, $art];
        }
    }

    #[Test]
    #[DataProvider('eingabeProvider')]
    public function der_aussteller_traegt_genau_das_bei_was_vorgesehen_ist(string $name, string $art): void
    {
        $json = (string) json_encode(SystemTemplates::all()[$name]['sections'], JSON_UNESCAPED_UNICODE);

        $hatFeld = str_contains($json, '"docField"');
        $hatFrei = str_contains($json, '"mode":"free"');

        match ($art) {
            'field'  => $this->assertTrue($hatFeld, 'In „' . $name . '" fehlt das ausfuellbare Feld.'),
            'free'   => $this->assertTrue($hatFrei, 'In „' . $name . '" fehlt der freie Abschnitt.'),
            'nichts' => $this->assertFalse(
                $hatFeld || $hatFrei,
                '„' . $name . '" soll vollstaendig aus dem Datenbestand kommen.',
            ),
            default  => $this->fail('Unbekannte Erwartung: ' . $art),
        };
    }
}
