<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Documents\Editor\SystemTemplates;
use App\Documents\Editor\VariableCatalog;
use EmergencyForge\Editor\PackageGuard;
use EmergencyForge\Editor\PageShell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die mitgelieferten Vorlagen müssen durch denselben Guard und dieselbe
 * Seitenhülle gehen wie jedes andere Dokumentpaket. Ein Tippfehler in der
 * Struktur soll hier auffallen und nicht erst, wenn jemand eine Urkunde
 * ausstellen will.
 */
final class SystemTemplatesTest extends TestCase
{
    private const URKUNDEN = [
        'Beförderungsurkunde', 'Ernennungsurkunde', 'Entlassungsurkunde',
        'Ausbildungszertifikat', 'Lehrgangszertifikat', 'Lehrgangszertifikat Fachdienste',
        SystemTemplates::TRAINING_QUALIFICATION, SystemTemplates::TRAINING_COURSE,
    ];

    /** Alles darin steht im Mitarbeiterdatensatz oder kommt aus dem Lehrgangsabschluss. */
    private const OHNE_EINGABE = ['Entlassungsurkunde', SystemTemplates::TRAINING_QUALIFICATION, SystemTemplates::TRAINING_COURSE];

    /** @return iterable<string,array{string}> */
    public static function templateProvider(): iterable
    {
        foreach (array_keys(SystemTemplates::all()) as $name) {
            yield $name => [$name];
        }
    }

    /** @return array<string,mixed> */
    private function package(string $name): array
    {
        return SystemTemplates::all()[$name]['content'];
    }

    #[Test]
    public function es_sind_die_vorlagen_des_alten_systems_und_die_fuer_lehrgaenge(): void
    {
        $this->assertSame([
            'Beförderungsurkunde',
            'Ernennungsurkunde',
            'Entlassungsurkunde',
            'Ausbildungszertifikat',
            'Lehrgangszertifikat',
            'Lehrgangszertifikat Fachdienste',
            'Ausbildungszertifikat (Lehrgang)',
            'Lehrgangszertifikat (Lehrgang)',
            'Schriftliche Abmahnung',
            'Vorläufige Dienstenthebung',
            'Dienstentfernung',
            'Außerordentliche Kündigung',
        ], array_keys(SystemTemplates::all()));
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function der_guard_nimmt_die_vorlage_an(string $name): void
    {
        (new PackageGuard())->assertValidTemplate($this->package($name));

        $this->addToAssertionCount(1);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function die_seitenhuelle_setzt_die_vorlage_ohne_warnung(string $name): void
    {
        // Alle Platzhalter mit einem Wert versorgen, damit die Warnungen
        // nur von der Struktur kommen können.
        $variables = array_map(static fn (string $label): string => 'X', VariableCatalog::catalog());

        $shell = PageShell::html($this->package($name), $variables);

        $this->assertFalse($shell->error, 'Fehler in „' . $name . '"');
        $structural = array_filter(
            $shell->warnings,
            static fn (string $w): bool => !str_contains($w, 'Pflichtfeld leer: '),
        );
        $this->assertSame([], array_values($structural), 'Warnungen in „' . $name . '"');
        $this->assertSame(in_array($name, self::URKUNDEN, true) ? 'landscape' : 'portrait', $shell->orientation);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function jeder_platzhalter_steht_im_katalog(string $name): void
    {
        $catalog = VariableCatalog::catalog();
        $unknown = [];
        $package = $this->package($name);

        array_walk_recursive(
            $package,
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
     * Urkunden haben keinen Textbereich, nur Felder im festen Text; die
     * Entlassungsurkunde und die Lehrgangsfassungen nicht einmal das.
     * Schreiben haben einen Pflicht-Textbereich.
     */
    #[Test]
    #[DataProvider('templateProvider')]
    public function der_aussteller_traegt_genau_das_bei_was_vorgesehen_ist(string $name): void
    {
        $json    = (string) json_encode($this->package($name), JSON_UNESCAPED_UNICODE);
        $hatFeld = str_contains($json, '"docField"');
        $hatText = str_contains($json, '"docInput"');

        if (in_array($name, self::OHNE_EINGABE, true)) {
            $this->assertFalse($hatFeld || $hatText);
        } elseif (in_array($name, self::URKUNDEN, true)) {
            $this->assertTrue($hatFeld);
            $this->assertFalse($hatText);
        } else {
            $this->assertTrue($hatText);
            $this->assertStringContainsString('"required":true', $json);
        }
    }

    /** @return iterable<string,array{string,list<array<string,string>>}> */
    public static function auswahlProvider(): iterable
    {
        yield 'Lehrgangszertifikat' => ['Lehrgangszertifikat', SystemTemplates::LEHRGAENGE];
        yield 'Lehrgangszertifikat Fachdienste' => ['Lehrgangszertifikat Fachdienste', SystemTemplates::FACHDIENSTE];
    }

    /**
     * Der Lehrgang ist eine Auswahl wie im alten System, und gespeichert
     * wird die weibliche Form genauso wie die allgemeine.
     *
     * @param list<array<string,string>> $liste
     */
    #[Test]
    #[DataProvider('auswahlProvider')]
    public function der_lehrgang_kommt_aus_der_liste(string $name, array $liste): void
    {
        $vorlage = $this->package($name);
        $this->assertSame($liste, $this->qualifikationsfeld($vorlage['body'])['attrs']['options'] ?? null);

        $wert   = $liste[0]['female'] ?? $liste[0]['label'];
        $result = (new PackageGuard())->restore($vorlage, $this->mitWert($vorlage, $wert), $vorlage);
        $this->assertSame($wert, $this->qualifikationsfeld($result->package['body'])['attrs']['value']);

        $result = (new PackageGuard())->restore($vorlage, $this->mitWert($vorlage, 'Ehrenkommandant'), $vorlage);
        $this->assertSame('', $this->qualifikationsfeld($result->package['body'])['attrs']['value'] ?? '');
    }

    /**
     * @param  array<string,mixed> $node
     * @return array<string,mixed>
     */
    private function qualifikationsfeld(array $node): array
    {
        if (($node['type'] ?? null) === 'docField' && ($node['attrs']['fieldId'] ?? null) === 'qualifikation') {
            return $node;
        }
        foreach ($node['content'] ?? [] as $child) {
            $found = is_array($child) ? $this->qualifikationsfeld($child) : [];
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    /**
     * @param  array<string,mixed> $package
     * @return array<string,mixed>
     */
    private function mitWert(array $package, string $wert): array
    {
        $package['body'] = $this->setze($package['body'], $wert);

        return $package;
    }

    /**
     * @param  array<string,mixed> $node
     * @return array<string,mixed>
     */
    private function setze(array $node, string $wert): array
    {
        if (($node['type'] ?? null) === 'docField' && ($node['attrs']['fieldId'] ?? null) === 'qualifikation') {
            $node['attrs']['value'] = $wert;

            return $node;
        }
        foreach ($node['content'] ?? [] as $i => $child) {
            if (is_array($child)) {
                $node['content'][$i] = $this->setze($child, $wert);
            }
        }

        return $node;
    }
}
