<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\EnotfV2\Catalogs\BefundCatalog;
use Plugin\EnotfV2\Catalogs\DiagnoseCatalog;
use Plugin\EnotfV2\Catalogs\VitalparameterCatalog;
use Plugin\EnotfV2\Catalogs\ZugangCatalog;
use Plugin\EnotfV2\Support\ProtokollService;
use Tests\TestCase;

/**
 * Konsistenz der v2-Kataloge untereinander und gegen die Feld-Whitelist
 * des ProtokollService. Reine Konstanten-/Mapping-Prüfungen, kein DB-Zugriff.
 */
class EnotfV2CatalogsTest extends TestCase
{
    // ── DiagnoseCatalog ──────────────────────────────────────────────

    #[Test]
    public function diagnose_katalog_umfasst_113_labels(): void
    {
        $this->assertCount(113, DiagnoseCatalog::LABELS);
    }

    #[Test]
    public function kategorien_decken_alle_diagnose_codes_ohne_waisen_ab(): void
    {
        $categoryCodes = [];
        foreach (DiagnoseCatalog::CATEGORIES as $key => $category) {
            foreach ($category['codes'] as $code) {
                $this->assertArrayNotHasKey($code, $categoryCodes, "Code $code ist doppelt kategorisiert");
                $categoryCodes[$code] = $key;
            }
        }

        $labelCodes = array_keys(DiagnoseCatalog::LABELS);
        sort($labelCodes);
        $catCodes = array_keys($categoryCodes);
        sort($catCodes);

        $this->assertSame($labelCodes, $catCodes);
    }

    #[Test]
    public function trauma_subkategorien_partitionieren_die_trauma_codes(): void
    {
        $trauma   = DiagnoseCatalog::CATEGORIES['trauma'];
        $subCodes = [];
        foreach ($trauma['subcategories'] as $sub) {
            foreach ($sub['codes'] as $code) {
                $this->assertNotContains($code, $subCodes, "Trauma-Code $code in mehreren Subkategorien");
                $subCodes[] = $code;
            }
        }
        sort($subCodes);

        $traumaCodes = $trauma['codes'];
        sort($traumaCodes);

        $this->assertSame($traumaCodes, $subCodes);
    }

    #[Test]
    public function diagnose_helfer_liefern_label_und_kategorie(): void
    {
        $this->assertSame('Synkope', DiagnoseCatalog::label(22));
        $this->assertSame('herz_kreislauf', DiagnoseCatalog::categoryKey(22));
        $this->assertSame('trauma', DiagnoseCatalog::categoryKey(209));
        $this->assertNull(DiagnoseCatalog::label(7));
        $this->assertNull(DiagnoseCatalog::categoryKey(999));
    }

    // ── VitalparameterCatalog ────────────────────────────────────────

    #[Test]
    public function jeder_vitalcode_uebersteht_den_legacy_roundtrip(): void
    {
        foreach (array_keys(VitalparameterCatalog::PARAMETER) as $code) {
            $legacy = VitalparameterCatalog::legacyName($code);

            $this->assertNotNull($legacy, "Kein Legacy-Name für $code");
            $this->assertSame($code, VitalparameterCatalog::fromLegacyName($legacy), "Roundtrip $code");
        }
    }

    #[Test]
    public function legacy_namen_mit_unicode_subskript_werden_aufgeloest(): void
    {
        $this->assertSame('spo2', VitalparameterCatalog::fromLegacyName('SpO₂'));
        $this->assertSame('etco2', VitalparameterCatalog::fromLegacyName('etCO₂'));
        // ASCII-Schreibweise existiert in der Alt-Tabelle nicht → kein Match
        $this->assertNull(VitalparameterCatalog::fromLegacyName('SpO2'));
        $this->assertNull(VitalparameterCatalog::fromLegacyName('etCO2'));
    }

    #[Test]
    public function unbekannte_codes_und_namen_liefern_null(): void
    {
        $this->assertNull(VitalparameterCatalog::legacyName('puls'));
        $this->assertNull(VitalparameterCatalog::einheit('puls'));
        $this->assertNull(VitalparameterCatalog::fromLegacyName('Puls'));
    }

    #[Test]
    public function blutzucker_wird_in_mg_dl_gespeichert(): void
    {
        // Speichereinheit ist fix (BloodSugarHelper), unabhängig von ENOTF_BZ_UNIT
        $this->assertSame('mg/dl', VitalparameterCatalog::einheit('bz'));
    }

    // ── ZugangCatalog ────────────────────────────────────────────────

    #[Test]
    public function jede_zugangs_lokation_hat_gueltige_seiten(): void
    {
        foreach ([ZugangCatalog::ORTE_PVK, ZugangCatalog::ORTE_IO] as $orte) {
            foreach ($orte as $ort => $info) {
                $this->assertNotEmpty($info['seiten'], "Lokation $ort ohne Seiten");
                foreach ($info['seiten'] as $seite) {
                    $this->assertContains($seite, ZugangCatalog::SEITEN, "Lokation $ort: Seite '$seite'");
                }
            }
        }
    }

    #[Test]
    public function pvk_und_io_groessen_partitionieren_den_groessenkatalog(): void
    {
        $combined = array_merge(ZugangCatalog::GROESSEN_PVK, ZugangCatalog::GROESSEN_IO);
        sort($combined);

        $all = array_keys(ZugangCatalog::GROESSEN);
        sort($all);

        $this->assertSame($all, $combined);
        $this->assertEmpty(array_intersect(ZugangCatalog::GROESSEN_PVK, ZugangCatalog::GROESSEN_IO));
    }

    #[Test]
    public function zugangs_blattnummern_sind_eindeutig(): void
    {
        $sheets = array_merge(
            array_column(ZugangCatalog::ORTE_PVK, 'sheet'),
            array_column(ZugangCatalog::ORTE_IO, 'sheet'),
        );

        $this->assertSame($sheets, array_values(array_unique($sheets)));
    }

    #[Test]
    public function katalogwerte_passieren_die_c_zugang_validierung_des_service(): void
    {
        // Kreuzcheck: alles, was der Katalog anbietet, muss der Server-Write
        // (ProtokollService::validateCZugang) auch akzeptieren.
        $validate = new \ReflectionMethod(ProtokollService::class, 'validateCZugang');
        $service  = new ProtokollService();

        foreach (ZugangCatalog::ORTE_PVK as $ort => $info) {
            foreach ($info['seiten'] as $seite) {
                foreach (ZugangCatalog::GROESSEN_PVK as $groesse) {
                    $json = json_encode([['art' => 'pvk', 'groesse' => $groesse, 'ort' => $ort, 'seite' => $seite]]);
                    $this->assertNull($validate->invoke($service, $json), "pvk/$ort/$seite/$groesse");
                }
            }
        }

        foreach (ZugangCatalog::ORTE_IO as $ort => $info) {
            foreach ($info['seiten'] as $seite) {
                foreach (ZugangCatalog::GROESSEN_IO as $groesse) {
                    $json = json_encode([['art' => 'io', 'groesse' => $groesse, 'ort' => $ort, 'seite' => $seite]]);
                    $this->assertNull($validate->invoke($service, $json), "io/$ort/$seite/$groesse");
                }
            }
        }
    }

    // ── BefundCatalog ────────────────────────────────────────────────

    #[Test]
    public function massnahmen_flags_sind_in_der_feld_whitelist(): void
    {
        foreach (array_keys(BefundCatalog::MASSNAHMEN_FLAGS) as $spalte) {
            $this->assertContains($spalte, ProtokollService::ALLOWED_FIELDS, "Flag $spalte fehlt in ALLOWED_FIELDS");
        }
    }

    #[Test]
    public function bodymap_regionen_und_artfelder_sind_in_der_feld_whitelist(): void
    {
        foreach (array_keys(BefundCatalog::V_MUSTER_REGIONEN) as $feld) {
            $this->assertContains($feld, ProtokollService::ALLOWED_FIELDS, "Region $feld");
            $this->assertContains($feld . '1', ProtokollService::ALLOWED_FIELDS, "Verletzungsart {$feld}1");
        }
    }

    #[Test]
    public function psych_exklusivcodes_existieren_im_psych_katalog(): void
    {
        foreach (BefundCatalog::PSYCH_EXKLUSIV as $code) {
            $this->assertArrayHasKey($code, BefundCatalog::PSYCH);
        }
    }
}
