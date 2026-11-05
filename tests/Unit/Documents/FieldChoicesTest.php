<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Documents\Editor\FieldChoices;
use EmergencyForge\Editor\SectionGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FieldChoicesTest extends TestCase
{
    private const RANKS = [
        ['label' => 'Brandmeister/-in', 'male' => 'Brandmeister', 'female' => 'Brandmeisterin'],
        ['label' => 'Oberbrandmeister/-in', 'male' => 'Oberbrandmeister', 'female' => 'Oberbrandmeisterin'],
    ];

    /** @return array<string,mixed> */
    private function doc(string $value = '', ?string $source = FieldChoices::RANKS): array
    {
        $attrs = ['fieldId' => 'dienstgrad', 'label' => 'Dienstgrad', 'value' => $value];
        if ($source !== null) {
            $attrs['optionsSource'] = $source;
        }

        return ['type' => 'doc', 'content' => [[
            'type'    => 'docSection',
            'attrs'   => ['mode' => 'locked', 'sectionId' => 's1'],
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'docField', 'attrs' => $attrs]]]],
        ]]];
    }

    /**
     * @param  array<string,mixed>  $document
     * @return array<string,mixed>
     */
    private function fieldAttrs(array $document): array
    {
        return $document['content'][0]['content'][0]['content'][0]['attrs'];
    }

    /** @return callable(string): list<array<string,string>> */
    private function lists(): callable
    {
        return static fn (string $source): array => $source === FieldChoices::RANKS ? self::RANKS : [];
    }

    #[Test]
    public function setzt_die_liste_der_quelle_ein(): void
    {
        $filled = FieldChoices::apply($this->doc(), $this->lists());

        self::assertSame(self::RANKS, $this->fieldAttrs($filled)['options']);
    }

    #[Test]
    public function ersetzt_eine_veraltete_liste(): void
    {
        $doc = $this->doc();
        $doc['content'][0]['content'][0]['content'][0]['attrs']['options'] = [['label' => 'Gibt es nicht mehr']];

        $filled = FieldChoices::apply($doc, $this->lists());

        self::assertSame(self::RANKS, $this->fieldAttrs($filled)['options']);
    }

    #[Test]
    public function felder_ohne_oder_mit_unbekannter_quelle_bleiben_wie_sie_sind(): void
    {
        $free    = $this->doc(source: null);
        $unknown = $this->doc(source: 'gibt_es_nicht');

        self::assertSame($free, FieldChoices::apply($free, $this->lists()));
        self::assertSame($unknown, FieldChoices::apply($unknown, $this->lists()));
    }

    #[Test]
    public function ohne_stammdaten_bleibt_das_feld_freitext(): void
    {
        $filled = FieldChoices::apply($this->doc(), static fn (string $source): array => []);

        self::assertNull($this->fieldAttrs($filled)['options']);
    }

    #[Test]
    public function fragt_die_stammdaten_je_quelle_nur_einmal_ab(): void
    {
        $doc = $this->doc();
        $doc['content'][] = $doc['content'][0];
        $calls = 0;

        FieldChoices::apply($doc, function (string $source) use (&$calls): array {
            $calls++;

            return self::RANKS;
        });

        self::assertSame(1, $calls);
    }

    #[Test]
    public function ein_gespeicherter_wert_ausserhalb_der_liste_ueberlebt_das_speichern(): void
    {
        $stored   = $this->doc('Von Hand getippt');
        $template = FieldChoices::apply($this->doc(), $this->lists(), $stored);

        $result = (new SectionGuard())->restoreLockedSections($template, $stored);

        self::assertSame('Von Hand getippt', $this->fieldAttrs($result->document)['value']);
    }

    #[Test]
    public function ein_fremder_wert_wird_weiter_abgelehnt(): void
    {
        $template = FieldChoices::apply($this->doc(), $this->lists(), $this->doc('Brandmeister'));

        $result = (new SectionGuard())->restoreLockedSections($template, $this->doc('Gefaelscht'));

        self::assertSame('', $this->fieldAttrs($result->document)['value']);
    }

    #[Test]
    public function die_form_zum_geschlecht_wird_angenommen(): void
    {
        $template = FieldChoices::apply($this->doc(), $this->lists());

        $result = (new SectionGuard())->restoreLockedSections($template, $this->doc('Oberbrandmeisterin'));

        self::assertSame('Oberbrandmeisterin', $this->fieldAttrs($result->document)['value']);
    }
}
