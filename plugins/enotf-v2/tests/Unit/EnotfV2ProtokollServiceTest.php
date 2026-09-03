<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\EnotfV2\Support\ProtokollService;
use Tests\TestCase;

/**
 * DB-freie Anteile des ProtokollService: Feld-Whitelist, Datums-
 * konvertierung, c_zugang-Strukturvalidierung und die patname-Regel.
 *
 * writeField() wird nur über Pfade aufgerufen, die VOR dem DB-Write
 * abbrechen (unbekanntes Feld, ungültiges Datum, ungültige c_zugang-
 * Struktur). Die privaten Helfer convertDateValue/validateCZugang werden
 * per Reflection direkt getestet; combinePatname ist der für Tests
 * extrahierte pure Kern von syncPatname.
 */
class EnotfV2ProtokollServiceTest extends TestCase
{
    private ProtokollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProtokollService();
    }

    private function convertDate(string $value): string|false
    {
        $method = new \ReflectionMethod(ProtokollService::class, 'convertDateValue');

        return $method->invoke($this->service, $value);
    }

    private function validateZugang(mixed $value): ?string
    {
        $method = new \ReflectionMethod(ProtokollService::class, 'validateCZugang');

        return $method->invoke($this->service, $value);
    }

    // ── Feld-Whitelist ───────────────────────────────────────────────

    #[Test]
    public function unbekanntes_feld_wird_abgelehnt(): void
    {
        $this->assertSame('Unbekanntes Feld', $this->service->writeField('E1', 'nicht_existent', 'x'));
        $this->assertSame('Unbekanntes Feld', $this->service->writeField('E1', 'enr', 'E2'));
        $this->assertSame('Unbekanntes Feld', $this->service->writeField('E1', 'freigegeben', 1));
        $this->assertSame('Unbekanntes Feld', $this->service->writeField('E1', 'id', 5));
    }

    #[Test]
    public function whitelist_enthaelt_awsicherung_2(): void
    {
        // In v1 fehlte awsicherung_2 und der Autosave lief auf 400
        $this->assertContains('awsicherung_2', ProtokollService::ALLOWED_FIELDS);
    }

    #[Test]
    public function whitelist_ist_duplikatfrei(): void
    {
        $fields = ProtokollService::ALLOWED_FIELDS;

        $this->assertSame($fields, array_values(array_unique($fields)));
    }

    // ── Datumskonvertierung ──────────────────────────────────────────

    #[Test]
    public function deutsches_datum_wird_nach_iso_konvertiert(): void
    {
        $this->assertSame('2026-02-01', $this->convertDate('01.02.2026'));
        $this->assertSame('2026-02-01', $this->convertDate('1.2.2026'));
        $this->assertSame('2026-12-31', $this->convertDate('31.12.2026'));
    }

    #[Test]
    public function iso_datum_wird_unveraendert_durchgereicht(): void
    {
        $this->assertSame('2026-02-01', $this->convertDate('2026-02-01'));
    }

    #[Test]
    public function unplausible_und_unlesbare_daten_werden_abgelehnt(): void
    {
        $this->assertFalse($this->convertDate('31.02.2026')); // Tag existiert nicht
        $this->assertFalse($this->convertDate('2026-13-01')); // Monat existiert nicht
        $this->assertFalse($this->convertDate('01/02/2026'));
        $this->assertFalse($this->convertDate('kein Datum'));
    }

    #[Test]
    public function date_fields_umfasst_genau_die_drei_datumsspalten(): void
    {
        $this->assertSame(
            ['edatum', 'patgebdat', 'symptombeginn_datum'],
            ProtokollService::DATE_FIELDS,
        );
    }

    #[Test]
    public function write_field_lehnt_ungueltiges_datum_vor_dem_schreiben_ab(): void
    {
        // Bricht vor dem DB-Zugriff ab — deshalb hier ohne DB testbar
        $this->assertSame('Ungültiges Datumsformat', $this->service->writeField('E1', 'edatum', '31.02.2026'));
        $this->assertSame('Ungültiges Datumsformat', $this->service->writeField('E1', 'patgebdat', 'gestern'));
    }

    // ── c_zugang-Strukturvalidierung ─────────────────────────────────

    #[Test]
    public function kein_zugang_und_leere_werte_sind_gueltig(): void
    {
        $this->assertNull($this->validateZugang('0'));
        $this->assertNull($this->validateZugang(''));
        $this->assertNull($this->validateZugang(null));
    }

    #[Test]
    public function gueltige_zugangsliste_passiert_die_validierung(): void
    {
        $json = json_encode([
            ['art' => 'pvk', 'groesse' => '18G', 'ort' => 'Unterarm', 'seite' => 'links'],
            ['art' => 'io', 'groesse' => '25mm', 'ort' => 'Sternum', 'seite' => ''],
        ]);

        $this->assertNull($this->validateZugang($json));
    }

    #[Test]
    public function einzelobjekt_statt_liste_ist_erlaubt(): void
    {
        $json = json_encode(['art' => 'pvk', 'groesse' => '20G', 'ort' => 'Handrücken', 'seite' => 'rechts']);

        $this->assertNull($this->validateZugang($json));
    }

    #[Test]
    public function kaputtes_json_wird_abgelehnt(): void
    {
        $this->assertSame('Ungültiges JSON-Format', $this->validateZugang('{nope'));
        $this->assertSame('Ungültiges JSON-Format', $this->validateZugang(42));
    }

    #[Test]
    public function fehlende_pflichtangaben_werden_benannt(): void
    {
        $this->assertSame(
            'Pflichtfeld fehlt: groesse',
            $this->validateZugang(json_encode([['art' => 'pvk', 'ort' => 'Unterarm', 'seite' => 'links']])),
        );
        $this->assertSame(
            'Pflichtfeld fehlt: seite',
            $this->validateZugang(json_encode([['art' => 'pvk', 'groesse' => '18G', 'ort' => 'Unterarm']])),
        );
    }

    #[Test]
    public function doppelter_zugang_an_gleicher_position_wird_abgelehnt(): void
    {
        $json = json_encode([
            ['art' => 'pvk', 'groesse' => '18G', 'ort' => 'Unterarm', 'seite' => 'links'],
            ['art' => 'pvk', 'groesse' => '20G', 'ort' => 'Unterarm', 'seite' => 'links'],
        ]);

        $this->assertSame('Doppelter Zugang an gleicher Position nicht erlaubt', $this->validateZugang($json));
    }

    #[Test]
    public function ungueltige_art_groesse_und_seite_werden_abgelehnt(): void
    {
        $mk = fn (array $z) => json_encode([array_merge(
            ['art' => 'pvk', 'groesse' => '18G', 'ort' => 'Unterarm', 'seite' => 'links'],
            $z,
        )]);

        $this->assertSame('Ungültige Zugangsart: arteriell', $this->validateZugang($mk(['art' => 'arteriell'])));
        $this->assertSame('Ungültige Zugangsgröße: 12G', $this->validateZugang($mk(['groesse' => '12G'])));
        $this->assertSame('Ungültige Seite: mittig', $this->validateZugang($mk(['seite' => 'mittig'])));
    }

    #[Test]
    public function write_field_lehnt_ungueltige_zugangsstruktur_vor_dem_schreiben_ab(): void
    {
        $this->assertSame('Ungültiges JSON-Format', $this->service->writeField('E1', 'c_zugang', '{nope'));
    }

    // ── patname-Ableitung ────────────────────────────────────────────

    #[Test]
    public function patname_wird_als_nachname_komma_vorname_kombiniert(): void
    {
        $this->assertSame('Mustermann, Max', ProtokollService::combinePatname('Max', 'Mustermann'));
    }

    #[Test]
    public function patname_ohne_einen_namensteil_hat_kein_komma(): void
    {
        $this->assertSame('Mustermann', ProtokollService::combinePatname('', 'Mustermann'));
        $this->assertSame('Max', ProtokollService::combinePatname('Max', ''));
        $this->assertSame('', ProtokollService::combinePatname('', ''));
    }

    #[Test]
    public function patname_trimmt_whitespace(): void
    {
        $this->assertSame('Mustermann, Max', ProtokollService::combinePatname('  Max ', ' Mustermann  '));
        $this->assertSame('', ProtokollService::combinePatname('   ', "\t"));
    }
}
