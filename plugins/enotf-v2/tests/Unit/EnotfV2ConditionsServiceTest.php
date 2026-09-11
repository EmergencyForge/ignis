<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\EnotfV2\Support\ConditionsService;
use Tests\TestCase;

/**
 * Pflichtfeld-Regelwerk (Port aus v1 conditions.php/notify.php): Regelanzahl,
 * transportziel-Overrides/-Additions, zeroIsValid-Semantik, sectionStatus-
 * Mapping und die isReleasable-Grenzfälle. Alles über Arrays — kein DB-Zugriff.
 */
class EnotfV2ConditionsServiceTest extends TestCase
{
    private ConditionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConditionsService();
    }

    /**
     * Datensatz, der alle Basisregeln erfüllt (transportziel=1, keine
     * Overrides/Additions). Basis für die Releasable-Grenzfälle.
     *
     * @return array<string,mixed>
     */
    private function vollstaendigeDaten(): array
    {
        return [
            // [1] Rettdaten — patsex 0 ist gültig (weiblich)
            'patsex' => 0, 'edatum' => '2026-01-01', 'ezeit' => '12:00',
            'transportziel' => 1, 'salarm' => '11:50', 'spat' => '12:05',
            'sende' => '13:00', 'eart' => 1, 'transp_adresse' => 'Musterstr. 1',
            // [2] Erstbefund
            'awfrei_1' => 1, 'zyanose_1' => 1, 'b_symptome' => 0, 'b_auskult' => 0,
            'c_kreislauf' => 1, 'c_ekg' => 1, 'c_puls_reg' => 1, 'c_puls_rad' => 1,
            'd_bewusstsein' => 1, 'd_ex_1' => 1,
            'd_pupillenw_1' => 1, 'd_pupillenw_2' => 1,
            'd_lichtreakt_1' => 1, 'd_lichtreakt_2' => 1,
            'd_gcs_1' => 0, 'd_gcs_2' => 0, 'd_gcs_3' => 0,
            'psych' => '[1]',
            'spo2' => 98, 'atemfreq' => 15, 'rrsys' => 120, 'herzfreq' => 80, 'bz' => 90,
            // [3] Anamnese
            'naca_initial' => 2, 'elokation' => 1,
            // [4] Diagnose
            'diagnose_haupt' => 22,
            // [6] Massnahmen
            'awsicherung_neu' => 0, 'b_beatmung' => 1, 'c_zugang' => '0', 'medis' => '[]',
            // [7] Abschluss
            'ebesonderheiten' => 'keine', 'na_nachf' => 1,
            'pfname' => 'Mustermann', 'prot_by' => 2,
        ];
    }

    // ── Regelanzahl ──────────────────────────────────────────────────

    #[Test]
    public function basisregelwerk_umfasst_33_regeln(): void
    {
        $this->assertCount(33, $this->service->baseRequired());
    }

    #[Test]
    public function leeres_protokoll_verletzt_alle_33_basisregeln(): void
    {
        $open = $this->service->evaluate([]);

        $this->assertSame(33, array_sum(array_map('count', $open)));
    }

    // ── transportziel=4 (Fehleinsatz) — Overrides ────────────────────

    #[Test]
    public function fehleinsatz_reduziert_auf_11_aktive_regeln(): void
    {
        $active = $this->service->activeRequired(4);

        $this->assertCount(11, $active);
        // Patientenregeln sind raus …
        $this->assertArrayNotHasKey('patsex', $active);
        $this->assertArrayNotHasKey('diagnose_haupt', $active);
        $this->assertArrayNotHasKey('na_nachf', $active);
        // … Rahmendaten bleiben Pflicht
        $this->assertArrayHasKey('edatum', $active);
        $this->assertArrayHasKey('pfname', $active);
        $this->assertArrayHasKey('prot_by', $active);
    }

    #[Test]
    public function fehleinsatz_ohne_weitere_daten_hat_10_offene_regeln(): void
    {
        // transportziel selbst ist gesetzt, die übrigen 10 aktiven Regeln offen
        $open = $this->service->evaluate(['transportziel' => 4]);

        $this->assertSame(10, array_sum(array_map('count', $open)));
    }

    #[Test]
    public function jeder_override_verweist_auf_eine_existierende_basisregel(): void
    {
        $base = $this->service->baseRequired();

        foreach ($this->service->conditionOverrides() as $ziel => $keys) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $base, "Override [$ziel] $key fehlt im Basisregelwerk");
            }
        }
    }

    // ── transportziel 2/21/22 — Additions ────────────────────────────

    #[Test]
    public function transportziele_2_21_22_ergaenzen_ziel_adresse_und_zeiten(): void
    {
        foreach ([2, 21, 22] as $ziel) {
            $active = $this->service->activeRequired($ziel);

            $this->assertCount(36, $active, "transportziel=$ziel");
            $this->assertArrayHasKey('ziel_adresse', $active);
            $this->assertArrayHasKey('s7', $active);
            $this->assertArrayHasKey('s8', $active);
        }
    }

    #[Test]
    public function transport_ohne_zieladresse_meldet_die_addition_als_offen(): void
    {
        $daten = $this->vollstaendigeDaten();
        $daten['transportziel'] = 2;
        $daten['s7'] = '12:30';
        $daten['s8'] = '12:45';
        // ziel_adresse fehlt

        $open = $this->service->evaluate($daten);

        $this->assertArrayHasKey('rettdaten', $open);
        $this->assertSame(['ziel_adresse'], array_column($open['rettdaten'], 'key'));
    }

    // ── zeroIsValid-Semantik (sectionStatus) ─────────────────────────

    #[Test]
    public function patsex_0_zaehlt_als_gefuellt(): void
    {
        $status = $this->service->sectionStatus(['patsex' => 0]);

        $this->assertSame('partfilled', $status['rettdaten']['status']);
        $this->assertSame(1, $status['rettdaten']['filled']);
    }

    #[Test]
    public function ezeit_string_0_zaehlt_als_leer(): void
    {
        $status = $this->service->sectionStatus(['ezeit' => '0']);

        $this->assertSame('unfilled', $status['rettdaten']['status']);
        $this->assertSame(0, $status['rettdaten']['filled']);
    }

    // ── sectionStatus-Mapping ────────────────────────────────────────

    #[Test]
    public function section_status_liefert_alle_stepper_sections_in_reihenfolge(): void
    {
        $status = $this->service->sectionStatus([]);

        $this->assertSame(
            ['rettdaten', 'erstbefund', 'anamnese', 'diagnose', 'verlauf', 'massnahmen', 'abschluss'],
            array_keys($status),
        );
    }

    #[Test]
    public function verlauf_hat_keine_pflichtspalten_und_ist_nocheck(): void
    {
        $status = $this->service->sectionStatus([]);

        $this->assertSame(['status' => 'nocheck', 'filled' => 0, 'total' => 0], $status['verlauf']);
    }

    #[Test]
    public function volles_protokoll_meldet_alle_gecheckten_sections_als_filled(): void
    {
        $status = $this->service->sectionStatus($this->vollstaendigeDaten());

        foreach (['rettdaten', 'erstbefund', 'anamnese', 'diagnose', 'massnahmen', 'abschluss'] as $key) {
            $this->assertSame('filled', $status[$key]['status'], "Section $key");
        }
    }

    #[Test]
    public function fehleinsatz_laesst_erstbefund_ohne_checks(): void
    {
        // transportziel=4 entfernt alle Erstbefund-Regeln → Section ohne Pflichtspalten
        $status = $this->service->sectionStatus(['transportziel' => 4]);

        $this->assertSame('nocheck', $status['erstbefund']['status']);
    }

    // ── isReleasable ─────────────────────────────────────────────────

    #[Test]
    public function vollstaendiges_protokoll_ist_freigebbar(): void
    {
        $this->assertTrue($this->service->isReleasable($this->vollstaendigeDaten()));
    }

    #[Test]
    public function ohne_protokollant_nicht_freigebbar(): void
    {
        $daten = $this->vollstaendigeDaten();
        unset($daten['pfname']);

        $this->assertFalse($this->service->isReleasable($daten));

        $open = $this->service->evaluate($daten);
        $this->assertSame(['pfname'], array_column($open['abschluss'] ?? [], 'key'));
    }

    #[Test]
    public function leerer_protokollant_zaehlt_als_fehlend(): void
    {
        $daten = $this->vollstaendigeDaten();
        $daten['pfname'] = '';

        $this->assertFalse($this->service->isReleasable($daten));
    }

    #[Test]
    public function leeres_protokoll_ist_nicht_freigebbar(): void
    {
        $this->assertFalse($this->service->isReleasable([]));
    }

    #[Test]
    public function na_protokoll_braucht_keine_na_nachforderung(): void
    {
        // prot_by=1 (NA) schaltet die na_nachf-Regel ab
        $daten = $this->vollstaendigeDaten();
        $daten['prot_by'] = 1;
        unset($daten['na_nachf']);

        $this->assertTrue($this->service->isReleasable($daten));
    }
}
