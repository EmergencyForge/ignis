<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Support;

use Plugin\EnotfV2\Models\Edivi;

/**
 * ConditionsService — Port des v1-Pflichtfeldsystems
 * (assets/functions/enotf/conditions.php).
 *
 * Regeln, Messages und die transportziel-Abhängigkeit sind IDENTISCH zu
 * v1 übernommen:
 *   - baseRequired()        = enotf_get_base_required()
 *   - conditionOverrides()  = enotf_get_condition_overrides()
 *                             (transportziel=4 „Fehleinsatz": Patientenregeln entfallen)
 *   - conditionAdditions()  = enotf_get_condition_additions()
 *                             (transportziel 2/21/22: ziel_adresse, s7, s8 werden Pflicht)
 *   - ZERO_IS_VALID         = zeroIsValid-Liste aus notify.php (Felder, bei
 *                             denen der Wert 0/'0' als „gefüllt" zählt)
 *
 * Zwei getrennte Prüf-Semantiken wie in v1:
 *   - evaluate()/isReleasable() nutzen die 'check'-Closures der Regeln
 *     (Semantik von assets/components/enotf/plausibility.php).
 *   - sectionStatus() nutzt die 'db'-Spalten der Regeln mit dem
 *     Füllstands-Check aus notify.php::updateNavFillStates (jede Spalte
 *     eine Gruppe; filled = alle, partfilled = einige, unfilled = keine,
 *     nocheck = Section ohne aktive Pflichtspalten — z. B. Verlauf).
 *
 * Section-Nummern aus v1 (1,2,3,4,6,7) werden auf die v2-Section-Keys
 * des Steppers gemappt (ProtokollController::SECTIONS).
 */
final class ConditionsService
{
    /**
     * Felder, bei denen 0/'0' ein gültiger (= gefüllter) Wert ist.
     * Quelle: assets/functions/enotf/notify.php (const zeroIsValid).
     */
    public const ZERO_IS_VALID = [
        'patsex', 'awsicherung_neu', 'b_symptome',
        'b_auskult', 'b_beatmung', 'c_kreislauf', 'c_ekg', 'c_zugang',
        'd_bewusstsein', 'd_ex_1', 'd_pupillenw_1', 'd_pupillenw_2',
        'd_lichtreakt_1', 'd_lichtreakt_2', 'd_gcs_1', 'd_gcs_2', 'd_gcs_3',
        'v_muster_k', 'v_muster_t',
        'v_muster_a', 'v_muster_al', 'v_muster_bl', 'v_muster_w', 'transportziel', 'medis',
        'naca_initial', 'naca_uebergabe',
    ];

    /** v1-Section-Nummer → v2-Section-Key (Stepper). */
    public const SECTION_KEYS = [
        1 => 'rettdaten',
        2 => 'erstbefund',
        3 => 'anamnese',
        4 => 'diagnose',
        5 => 'verlauf',
        6 => 'massnahmen',
        7 => 'abschluss',
    ];

    // ── Regelwerk (1:1-Port aus conditions.php) ──────────────────────

    /**
     * Basis-Pflichtregeln. Jede Regel: section (v1-Nummer), message,
     * check (Closure($daten) → true wenn Feld FEHLT), db (Spalten für
     * den Füllstands-Check des Steppers).
     *
     * @return array<string, array{section:int, message:string, check:\Closure, db:list<string>}>
     */
    public function baseRequired(): array
    {
        return [
            // ──── [1] Rettdaten ────
            'patsex' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Patienten-Geschlecht ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['patsex'] ?? null) === null; },
                'db'      => ['patsex'],
            ],
            'edatum' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Einsatzdatum ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['edatum']); },
                'db'      => ['edatum'],
            ],
            'ezeit' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Einsatzzeit ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['ezeit']); },
                'db'      => ['ezeit'],
            ],
            'transportziel' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Versorgung ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['transportziel'] ?? null) === null; },
                'db'      => ['transportziel'],
            ],
            'salarm' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Alarmzeit ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['salarm']); },
                'db'      => ['salarm'],
            ],
            'spat' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Patientenankunft ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['spat']); },
                'db'      => ['spat'],
            ],
            'sende' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Endzeit ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['sende']); },
                'db'      => ['sende'],
            ],
            'eart' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Einsatzart ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['eart'] ?? null) === null; },
                'db'      => ['eart'],
            ],
            'transp_adresse' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Von Adresse ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['transp_adresse']); },
                'db'      => ['transp_adresse'],
            ],

            // ──── [2] Erstbefund ────
            'atemwege' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Atemwege ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['awfrei_1']) && empty($d['awfrei_2']) && empty($d['awfrei_3']); },
                'db'      => ['awfrei_1'],
            ],
            'zyanose' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Zyanose ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['zyanose_1']) && empty($d['zyanose_2']); },
                'db'      => ['zyanose_1'],
            ],
            'b_symptome' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Beurteilung Atmung ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['b_symptome'] ?? null) === null; },
                'db'      => ['b_symptome'],
            ],
            'b_auskult' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Auskultation ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['b_auskult'] ?? null) === null; },
                'db'      => ['b_auskult'],
            ],
            'c_kreislauf' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Patientenzustand ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['c_kreislauf'] ?? null) === null; },
                'db'      => ['c_kreislauf'],
            ],
            'c_ekg' => [
                'section' => 2,
                'message' => '[2] Erstbefund: EKG ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['c_ekg'] ?? null) === null; },
                'db'      => ['c_ekg'],
            ],
            'c_puls' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Puls ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['c_puls_reg'] ?? null) === null || ($d['c_puls_rad'] ?? null) === null; },
                'db'      => ['c_puls_rad', 'c_puls_reg'],
            ],
            'd_bewusstsein' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Bewusstseinslage ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['d_bewusstsein'] ?? null) === null; },
                'db'      => ['d_bewusstsein'],
            ],
            'd_extremitaeten' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Extremitätenbewegung ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['d_ex_1'] ?? null) === null; },
                'db'      => ['d_ex_1'],
            ],
            'd_pupillen' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Pupillen sind nicht gesetzt.',
                'check'   => function ($d) { return ($d['d_pupillenw_1'] ?? null) === null || ($d['d_lichtreakt_1'] ?? null) === null || ($d['d_pupillenw_2'] ?? null) === null || ($d['d_lichtreakt_2'] ?? null) === null; },
                'db'      => ['d_pupillenw_1', 'd_pupillenw_2', 'd_lichtreakt_1', 'd_lichtreakt_2'],
            ],
            'd_gcs' => [
                'section' => 2,
                'message' => '[2] Erstbefund: GCS ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['d_gcs_1'] ?? null) === null || ($d['d_gcs_2'] ?? null) === null || ($d['d_gcs_3'] ?? null) === null; },
                'db'      => ['d_gcs_1', 'd_gcs_2', 'd_gcs_3'],
            ],
            'psych' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Psychischer Zustand ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['psych'] ?? null) === null; },
                'db'      => ['psych'],
            ],
            'messwerte' => [
                'section' => 2,
                'message' => '[2] Erstbefund: Messwerte sind nicht gesetzt.',
                'check'   => function ($d) { return empty($d['spo2']) || empty($d['atemfreq']) || empty($d['rrsys']) || empty($d['herzfreq']) || empty($d['bz']); },
                'db'      => ['spo2', 'atemfreq', 'rrsys', 'herzfreq', 'bz'],
            ],

            // ──── [3] Anamnese ────
            'naca_initial' => [
                'section' => 3,
                'message' => '[3] Anamnese: NACA-Score (initial) ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['naca_initial'] ?? null) === null; },
                'db'      => ['naca_initial'],
            ],
            'elokation' => [
                'section' => 3,
                'message' => '[3] Anamnese: Einsatzort ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['elokation'] ?? null) === null; },
                'db'      => ['elokation'],
            ],

            // ──── [4] Diagnose ────
            'diagnose_haupt' => [
                'section' => 4,
                'message' => '[4] Diagnose: Führende Diagnose ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['diagnose_haupt'] ?? null) === null; },
                'db'      => ['diagnose_haupt'],
            ],

            // ──── [6] Massnahmen ────
            'awsicherung_neu' => [
                'section' => 6,
                'message' => '[6] Maßnahmen: Atemwegssicherung ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['awsicherung_neu'] ?? null) === null; },
                'db'      => ['awsicherung_neu'],
            ],
            'b_beatmung' => [
                'section' => 6,
                'message' => '[6] Maßnahmen: Beatmung ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['b_beatmung'] ?? null) === null; },
                'db'      => ['b_beatmung'],
            ],
            'c_zugang' => [
                'section' => 6,
                'message' => '[6] Maßnahmen: Zugang ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['c_zugang'] ?? null) === null; },
                'db'      => ['c_zugang'],
            ],
            'medis' => [
                'section' => 6,
                'message' => '[6] Maßnahmen: Medikamente sind nicht gesetzt.',
                'check'   => function ($d) { return ($d['medis'] ?? null) === null; },
                'db'      => ['medis'],
            ],

            // ──── [7] Abschluss ────
            'ebesonderheiten' => [
                'section' => 7,
                'message' => '[7] Abschluss: Einsatzverlauf Besonderheiten sind nicht gesetzt.',
                'check'   => function ($d) { return empty($d['ebesonderheiten']); },
                'db'      => ['ebesonderheiten'],
            ],
            'na_nachf' => [
                'section' => 7,
                'message' => '[7] Abschluss: NA-Nachforderung ist nicht gesetzt.',
                'check'   => function ($d) { return ($d['prot_by'] ?? null) != 1 && ($d['na_nachf'] ?? null) === null; },
                'db'      => ['na_nachf'],
            ],
            'pfname' => [
                'section' => 7,
                'message' => '[7] Abschluss: Kein Protokollant gesetzt.',
                'check'   => function ($d) { return empty($d['pfname']); },
                'db'      => ['pfname'],
            ],
            'prot_by' => [
                'section' => 7,
                'message' => '[7] Abschluss: Keine Protokollart gesetzt.',
                'check'   => function ($d) { return ($d['prot_by'] ?? null) === null; },
                'db'      => ['prot_by'],
            ],
        ];
    }

    /**
     * Overrides: welche Basis-Pflichtregeln bei einer Versorgungsart
     * OPTIONAL werden (v1 enotf_get_condition_overrides()).
     *
     * @return array<int, list<string>>
     */
    public function conditionOverrides(): array
    {
        return [
            // Fehleinsatz - kein Patient
            4 => [
                'patsex', 'spat',
                'atemwege', 'zyanose', 'b_symptome', 'b_auskult',
                'c_kreislauf', 'c_ekg', 'c_puls',
                'd_bewusstsein', 'd_extremitaeten', 'd_pupillen', 'd_gcs',
                'psych', 'messwerte',
                'naca_initial',
                'diagnose_haupt',
                'awsicherung_neu', 'b_beatmung', 'c_zugang', 'medis',
                'na_nachf',
            ],
        ];
    }

    /**
     * Additions: welche ZUSÄTZLICHEN Regeln bei einer Versorgungsart
     * Pflicht werden (v1 enotf_get_condition_additions()).
     *
     * @return array<int, array<string, array{section:int, message:string, check:\Closure, db:list<string>}>>
     */
    public function conditionAdditions(): array
    {
        $zielAdresse = [
            'ziel_adresse' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Transportziel (Ziel Adresse) ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['ziel_adresse']); },
                'db'      => ['ziel_adresse'],
            ],
        ];

        $transportZeiten = [
            's7' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: E.-ab (7) ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['s7']); },
                'db'      => ['s7'],
            ],
            's8' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: KH an (8) ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['s8']); },
                'db'      => ['s8'],
            ],
        ];

        $transportAll = array_merge($zielAdresse, $transportZeiten);

        return [
            // Transport ohne NA (oder mit TNA)
            2  => $transportAll,
            // Transport mit NA (bodengebunden)
            21 => $transportAll,
            // Transport mit NA (RTH)
            22 => $transportAll,
        ];
    }

    /**
     * Aktive Pflichtregeln: Basis − Overrides + Additions
     * (v1 enotf_get_active_required()).
     *
     * @return array<string, array{section:int, message:string, check:\Closure, db:list<string>}>
     */
    public function activeRequired(?int $transportziel): array
    {
        $base      = $this->baseRequired();
        $overrides = $this->conditionOverrides();
        $additions = $this->conditionAdditions();

        if ($transportziel !== null) {
            if (isset($overrides[$transportziel])) {
                foreach ($overrides[$transportziel] as $key) {
                    unset($base[$key]);
                }
            }
            if (isset($additions[$transportziel])) {
                $base = array_merge($base, $additions[$transportziel]);
            }
        }

        return $base;
    }

    // ── Öffentliche API ──────────────────────────────────────────────

    /**
     * Offene (= verletzte) Pflichtregeln, gruppiert nach v2-Section-Key.
     * Semantik identisch zur v1-Plausibilitätsprüfung (check-Closures).
     *
     * @param Edivi|array<string,mixed> $p
     * @return array<string, list<array{key:string, message:string}>>
     */
    public function evaluate(Edivi|array $p): array
    {
        $daten = $this->toDaten($p);
        $open  = [];

        foreach ($this->activeRequired($this->transportziel($daten)) as $key => $rule) {
            if (($rule['check'])($daten)) {
                $sectionKey = self::SECTION_KEYS[$rule['section']] ?? (string) $rule['section'];
                $open[$sectionKey][] = ['key' => $key, 'message' => $rule['message']];
            }
        }

        return $open;
    }

    /**
     * Füllstand je v2-Section für den Stepper. Semantik identisch zu
     * notify.php::updateNavFillStates: jede aktive DB-Pflichtspalte der
     * Section ist eine Gruppe; 0/'0' zählt nur bei ZERO_IS_VALID-Feldern
     * als gefüllt.
     *
     * @param Edivi|array<string,mixed> $p
     * @return array<string, array{status:string, filled:int, total:int}>
     *         status ∈ {filled, partfilled, unfilled, nocheck}
     */
    public function sectionStatus(Edivi|array $p): array
    {
        $daten  = $this->toDaten($p);
        $active = $this->activeRequired($this->transportziel($daten));

        // Aktive DB-Spalten je v1-Section einsammeln (unique, in Regel-Reihenfolge)
        $sectionCols = [];
        foreach ($active as $rule) {
            foreach ($rule['db'] as $col) {
                if (!in_array($col, $sectionCols[$rule['section']] ?? [], true)) {
                    $sectionCols[$rule['section']][] = $col;
                }
            }
        }

        $result = [];
        foreach (self::SECTION_KEYS as $sectionNr => $sectionKey) {
            $cols   = $sectionCols[$sectionNr] ?? [];
            $total  = count($cols);
            $filled = 0;
            foreach ($cols as $col) {
                if ($this->isDbValueFilled($col, $daten[$col] ?? null)) {
                    $filled++;
                }
            }

            if ($total === 0) {
                $status = 'nocheck';
            } elseif ($filled === $total) {
                $status = 'filled';
            } elseif ($filled > 0) {
                $status = 'partfilled';
            } else {
                $status = 'unfilled';
            }

            $result[$sectionKey] = ['status' => $status, 'filled' => $filled, 'total' => $total];
        }

        return $result;
    }

    /**
     * Freigebbar = keine offene Pflichtregel (v1 #final: leeres
     * #plausibility; pfname ist selbst eine Basisregel und damit enthalten).
     *
     * @param Edivi|array<string,mixed> $p
     */
    public function isReleasable(Edivi|array $p): bool
    {
        return $this->evaluate($p) === [];
    }

    // ── Interna ──────────────────────────────────────────────────────

    /** @param Edivi|array<string,mixed> $p
     *  @return array<string,mixed> */
    private function toDaten(Edivi|array $p): array
    {
        return $p instanceof Edivi ? $p->getAttributes() : $p;
    }

    /**
     * transportziel als int oder null — exakt wie v1 plausibility.php
     * ((int)-Cast auf den Rohwert, NULL bleibt NULL).
     *
     * @param array<string,mixed> $daten
     */
    private function transportziel(array $daten): ?int
    {
        $raw = $daten['transportziel'] ?? null;

        return $raw === null ? null : (int) $raw;
    }

    /**
     * Füllstands-Check einer DB-Spalte (notify.php-Semantik):
     * gefüllt = nicht NULL, nicht '', und 0/'0' nur bei ZERO_IS_VALID.
     */
    private function isDbValueFilled(string $field, mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $stringValue = (string) $value;
        if ($stringValue === '') {
            return false;
        }
        if ($stringValue === '0' && !in_array($field, self::ZERO_IS_VALID, true)) {
            return false;
        }

        return true;
    }
}
