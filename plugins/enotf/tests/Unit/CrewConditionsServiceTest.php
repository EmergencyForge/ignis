<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\Enotf\Crew\Support\ConditionsService;
use Tests\TestCase;

/**
 * Pflichtfeld-Regelwerk (Port aus v1 conditions.php/notify.php): Regelanzahl,
 * transportziel-Overrides/-Additions, zeroIsValid-Semantik, sectionStatus-
 * Mapping und die isReleasable-Grenzfälle. Alles über Arrays, kein DB-Zugriff.
 */
class CrewConditionsServiceTest extends TestCase
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
            // [1] Rettdaten: patsex 0 ist gültig (weiblich)
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
            'rea_status' => 1,
        ];
    }

    // ── Regelanzahl ──────────────────────────────────────────────────

    #[Test]
    public function basisregelwerk_umfasst_35_regeln(): void
    {
        $this->assertCount(35, $this->service->baseRequired());
    }

    #[Test]
    public function leeres_protokoll_verletzt_34_basisregeln(): void
    {
        // rea_details greift erst bei rea_status=2, alle anderen sind offen
        $open = $this->service->evaluate([]);

        $this->assertSame(34, array_sum(array_map('count', $open)));
    }

    // ── transportziel=4 (Fehleinsatz): Overrides ────────────────────

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

    // ── transportziel 2/21/22: Additions ────────────────────────────

    #[Test]
    public function transportziele_2_21_22_ergaenzen_ziel_adresse_und_zeiten(): void
    {
        foreach ([2, 21, 22] as $ziel) {
            $active = $this->service->activeRequired($ziel);

            $this->assertCount(38, $active, "transportziel=$ziel");
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

    // ── Reanimationssituation ────────────────────────────────────────

    #[Test]
    public function reanimation_durchgefuehrt_braucht_pflichtdetails(): void
    {
        $daten = $this->vollstaendigeDaten();
        $daten['rea_status'] = 2;
        $daten['rea_ursache'] = 1;

        $this->assertFalse($this->service->isReleasable($daten));
        $open = $this->service->evaluate($daten);
        $this->assertSame(['rea_details'], array_column($open['abschluss'] ?? [], 'key'));

        $daten += [
            'rea_kollaps' => 99, 'rea_hdm_durch' => 4, 'rea_hdm_zeit' => '12:03',
            'rea_rosc' => 2, 'rea_kh_aufnahme' => 1,
        ];
        $this->assertTrue($this->service->isReleasable($daten));
    }

    #[Test]
    public function reanimation_nicht_durchgefuehrt_schliesst_den_baum_ab(): void
    {
        foreach ([1, 3, 4, 5, 6] as $status) {
            $daten = $this->vollstaendigeDaten();
            $daten['rea_status'] = $status;

            $this->assertTrue($this->service->isReleasable($daten), "rea_status=$status");
        }
    }

    #[Test]
    public function ohne_reanimationssituation_nicht_freigebbar(): void
    {
        $daten = $this->vollstaendigeDaten();
        unset($daten['rea_status']);

        $open = $this->service->evaluate($daten);
        $this->assertSame(['rea_status'], array_column($open['abschluss'] ?? [], 'key'));
    }
}
