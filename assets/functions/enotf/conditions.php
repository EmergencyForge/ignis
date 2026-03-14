<?php
/**
 * eNOTF Konditions-System
 * =======================
 * Zentrale Steuerung aller Pflichtfelder im Protokoll.
 * Single Source of Truth für:
 *   - Server-seitige Plausibilitätsprüfung (plausibility.php)
 *   - Client-seitige Farbindikatoren (field_checks.php → html)
 *   - Navigations-Validierung (nav.php / notify.php → db)
 *   - Gruppen-Checks (automatisch aus Kindern berechnet)
 *
 * Jedes Feld hat:
 *   - 'section'  => Protokoll-Abschnitt (1=Rettdaten, 2=Erstbefund, 3=Anamnese, 4=Diagnose, 6=Massnahmen, 7=Abschluss)
 *   - 'message'  => Fehlermeldung für Plausibilitätsprüfung
 *   - 'check'    => Closure($daten) → true wenn Feld FEHLT
 *   - 'html'     => HTML name-Attribute auf Übersichtsseiten (für edivi__input-check Steuerung)
 *   - 'db'       => DB-Spaltennamen (für data-requires in der Navigation)
 *
 * Neue Versorgungsart:
 *   - Override: Eintrag in enotf_get_condition_overrides() → Keys werden optional
 *   - Addition: Eintrag in enotf_get_condition_additions() → neue Pflichtfelder
 */

function enotf_get_base_required(): array
{
    return [
        // ──── [1] Rettdaten ────
        'patsex' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Patienten-Geschlecht ist nicht gesetzt.',
            'check'   => function ($d) { return $d['patsex'] === null; },
            'html'    => ['patsex'],
            'db'      => ['patsex'],
        ],
        'edatum' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Einsatzdatum ist nicht gesetzt.',
            'check'   => function ($d) { return empty($d['edatum']); },
            'html'    => ['edatum'],
            'db'      => ['edatum'],
        ],
        'ezeit' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Einsatzzeit ist nicht gesetzt.',
            'check'   => function ($d) { return empty($d['ezeit']); },
            'html'    => ['ezeit'],
            'db'      => ['ezeit'],
        ],
        'transportziel' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Versorgung ist nicht gesetzt.',
            'check'   => function ($d) { return $d['transportziel'] === null; },
            'html'    => ['transportziel'],
            'db'      => ['transportziel'],
        ],
        'salarm' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Alarmzeit ist nicht gesetzt.',
            'check'   => function ($d) { return empty($d['salarm']); },
            'html'    => ['salarm', 'salarm_datum'],
            'db'      => ['salarm'],
        ],
        'spat' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Patientenankunft ist nicht gesetzt.',
            'check'   => function ($d) { return empty($d['spat']); },
            'html'    => ['spat', 'spat_datum'],
            'db'      => ['spat'],
        ],
        'sende' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Endzeit ist nicht gesetzt.',
            'check'   => function ($d) { return empty($d['sende']); },
            'html'    => ['sende', 'sende_datum'],
            'db'      => ['sende'],
        ],
        'eart' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Einsatzart ist nicht gesetzt.',
            'check'   => function ($d) { return $d['eart'] === null; },
            'html'    => ['eart'],
            'db'      => ['eart'],
        ],
        'transp_adresse' => [
            'section' => 1,
            'message' => '[1] Rett. Daten: Von Adresse ist nicht gesetzt.',
            'check'   => function ($d) { return empty($d['transp_adresse']); },
            'html'    => ['transp_display'],
            'db'      => ['transp_adresse'],
        ],

        // ──── [2] Erstbefund ────
        'atemwege' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Atemwege ist nicht gesetzt.',
            'check'   => function ($d) { return empty($d['awfrei_1']) && empty($d['awfrei_2']) && empty($d['awfrei_3']); },
            'html'    => ['atemwegszustand'],
            'db'      => ['awfrei_1'],
        ],
        'zyanose' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Zyanose ist nicht gesetzt.',
            'check'   => function ($d) { return empty($d['zyanose_1']) && empty($d['zyanose_2']); },
            'html'    => ['zyanose'],
            'db'      => ['zyanose_1'],
        ],
        'b_symptome' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Beurteilung Atmung ist nicht gesetzt.',
            'check'   => function ($d) { return $d['b_symptome'] === null; },
            'html'    => ['beurteilung_atmung'],
            'db'      => ['b_symptome'],
        ],
        'b_auskult' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Auskultation ist nicht gesetzt.',
            'check'   => function ($d) { return $d['b_auskult'] === null; },
            'html'    => ['auskultation'],
            'db'      => ['b_auskult'],
        ],
        'c_kreislauf' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Patientenzustand ist nicht gesetzt.',
            'check'   => function ($d) { return $d['c_kreislauf'] === null; },
            'html'    => ['patientenzustand', 'rekap', 'blutung'],
            'db'      => ['c_kreislauf'],
        ],
        'c_ekg' => [
            'section' => 2,
            'message' => '[2] Erstbefund: EKG ist nicht gesetzt.',
            'check'   => function ($d) { return $d['c_ekg'] === null; },
            'html'    => ['ekgbefund'],
            'db'      => ['c_ekg'],
        ],
        'c_puls' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Puls ist nicht gesetzt.',
            'check'   => function ($d) { return $d['c_puls_reg'] === null || $d['c_puls_rad'] === null; },
            'html'    => ['pulsregelmaessig', 'radialispuls'],
            'db'      => ['c_puls_rad', 'c_puls_reg'],
        ],
        'd_bewusstsein' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Bewusstseinslage ist nicht gesetzt.',
            'check'   => function ($d) { return $d['d_bewusstsein'] === null; },
            'html'    => ['bewusstseinslage'],
            'db'      => ['d_bewusstsein'],
        ],
        'd_extremitaeten' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Extremitätenbewegung ist nicht gesetzt.',
            'check'   => function ($d) { return $d['d_ex_1'] === null; },
            'html'    => ['extremitaetenbewegung'],
            'db'      => ['d_ex_1'],
        ],
        'd_pupillen' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Pupillen sind nicht gesetzt.',
            'check'   => function ($d) { return $d['d_pupillenw_1'] === null || $d['d_lichtreakt_1'] === null || $d['d_pupillenw_2'] === null || $d['d_lichtreakt_2'] === null; },
            'html'    => ['pupillenweite_li', 'pupillenweite_re', 'lichtreaktion_li', 'lichtreaktion_re'],
            'db'      => ['d_pupillenw_1', 'd_pupillenw_2', 'd_lichtreakt_1', 'd_lichtreakt_2'],
        ],
        'd_gcs' => [
            'section' => 2,
            'message' => '[2] Erstbefund: GCS ist nicht gesetzt.',
            'check'   => function ($d) { return $d['d_gcs_1'] === null || $d['d_gcs_2'] === null || $d['d_gcs_3'] === null; },
            'html'    => [],
            'db'      => ['d_gcs_1', 'd_gcs_2', 'd_gcs_3'],
        ],
        'psych' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Psychischer Zustand ist nicht gesetzt.',
            'check'   => function ($d) { return $d['psych'] === null; },
            'html'    => ['psychischer_zustand'],
            'db'      => ['psych'],
        ],
        'messwerte' => [
            'section' => 2,
            'message' => '[2] Erstbefund: Messwerte sind nicht gesetzt.',
            'check'   => function ($d) { return empty($d['spo2']) || empty($d['atemfreq']) || empty($d['rrsys']) || empty($d['herzfreq']) || empty($d['bz']); },
            'html'    => ['spo2', 'af', 'hf', 'rrsys', 'bz'],
            'db'      => ['spo2', 'atemfreq', 'rrsys', 'herzfreq', 'bz'],
        ],

        // ──── [3] Anamnese ────
        'naca_initial' => [
            'section' => 3,
            'message' => '[3] Anamnese: NACA-Score (initial) ist nicht gesetzt.',
            'check'   => function ($d) { return $d['naca_initial'] === null; },
            'html'    => ['naca_initial_display'],
            'db'      => ['naca_initial'],
        ],
        'elokation' => [
            'section' => 3,
            'message' => '[3] Anamnese: Einsatzort ist nicht gesetzt.',
            'check'   => function ($d) { return $d['elokation'] === null; },
            'html'    => ['elokation_display'],
            'db'      => ['elokation'],
        ],

        // ──── [4] Diagnose ────
        'diagnose_haupt' => [
            'section' => 4,
            'message' => '[4] Diagnose: Führende Diagnose ist nicht gesetzt.',
            'check'   => function ($d) { return $d['diagnose_haupt'] === null; },
            'html'    => ['diagnose_fuehrend'],
            'db'      => ['diagnose_haupt'],
        ],

        // ──── [6] Massnahmen ────
        'awsicherung_neu' => [
            'section' => 6,
            'message' => '[6] Maßnahmen: Atemwegssicherung ist nicht gesetzt.',
            'check'   => function ($d) { return $d['awsicherung_neu'] === null; },
            'html'    => ['atemwegssicherung'],
            'db'      => ['awsicherung_neu'],
        ],
        'b_beatmung' => [
            'section' => 6,
            'message' => '[6] Maßnahmen: Beatmung ist nicht gesetzt.',
            'check'   => function ($d) { return $d['b_beatmung'] === null; },
            'html'    => ['beatmung'],
            'db'      => ['b_beatmung'],
        ],
        'c_zugang' => [
            'section' => 6,
            'message' => '[6] Maßnahmen: Zugang ist nicht gesetzt.',
            'check'   => function ($d) { return $d['c_zugang'] === null; },
            'html'    => ['zugang_display'],
            'db'      => ['c_zugang'],
        ],
        'medis' => [
            'section' => 6,
            'message' => '[6] Maßnahmen: Medikamente sind nicht gesetzt.',
            'check'   => function ($d) { return $d['medis'] === null; },
            'html'    => ['medikamente'],
            'db'      => ['medis'],
        ],

        // ──── [7] Abschluss ────
        'ebesonderheiten' => [
            'section' => 7,
            'message' => '[7] Abschluss: Einsatzverlauf Besonderheiten sind nicht gesetzt.',
            'check'   => function ($d) { return empty($d['ebesonderheiten']); },
            'html'    => ['einsatzverlauf_besonderheiten'],
            'db'      => ['ebesonderheiten'],
        ],
        'na_nachf' => [
            'section' => 7,
            'message' => '[7] Abschluss: NA-Nachforderung ist nicht gesetzt.',
            'check'   => function ($d) { return $d['prot_by'] != 1 && $d['na_nachf'] === null; },
            'html'    => [],
            'db'      => ['na_nachf'],
        ],
        'pfname' => [
            'section' => 7,
            'message' => '[7] Abschluss: Kein Protokollant gesetzt.',
            'check'   => function ($d) { return empty($d['pfname']); },
            'html'    => ['pfname'],
            'db'      => ['pfname'],
        ],
        'prot_by' => [
            'section' => 7,
            'message' => '[7] Abschluss: Keine Protokollart gesetzt.',
            'check'   => function ($d) { return $d['prot_by'] === null; },
            'html'    => ['prot_by'],
            'db'      => ['prot_by'],
        ],
    ];
}

/**
 * Overrides: Welche Basis-Pflichtfelder werden bei einer Versorgungsart OPTIONAL?
 */
function enotf_get_condition_overrides(): array
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
 * Additions: Welche ZUSÄTZLICHEN Felder werden bei einer Versorgungsart Pflicht?
 */
function enotf_get_condition_additions(): array
{
    $zielAdresse = [
            'ziel_adresse' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: Transportziel (Ziel Adresse) ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['ziel_adresse']); },
                'html'    => ['ziel_poi_adresse'],
                'db'      => ['ziel_adresse'],
            ],
        ];

        $transportZeiten = [
            's7' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: E.-ab (7) ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['s7']); },
                'html'    => ['s7', 's7_datum'],
                'db'      => ['s7'],
            ],
            's8' => [
                'section' => 1,
                'message' => '[1] Rett. Daten: KH an (8) ist nicht gesetzt.',
                'check'   => function ($d) { return empty($d['s8']); },
                'html'    => ['s8', 's8_datum'],
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

// ──── API-Funktionen ────

/**
 * Gibt die aktiven Pflichtfelder zurück: Basis - Overrides + Additions.
 */
function enotf_get_active_required(?int $transportziel): array
{
    $base = enotf_get_base_required();
    $overrides = enotf_get_condition_overrides();
    $additions = enotf_get_condition_additions();

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

/**
 * Gibt die data-requires DB-Spalten für eine Nav-Section zurück.
 * Gruppiert nach Protokoll-Section.
 */
function enotf_get_nav_requires(?int $transportziel, int $section): string
{
    $active = enotf_get_active_required($transportziel);
    $dbCols = [];

    foreach ($active as $rule) {
        if ($rule['section'] === $section && !empty($rule['db'])) {
            $dbCols = array_merge($dbCols, $rule['db']);
        }
    }

    return implode(',', array_unique($dbCols));
}

/**
 * Gibt die komplette Konfiguration als JSON-fähiges Array zurück.
 */
function enotf_get_conditions_for_js(): array
{
    $base = enotf_get_base_required();
    $overrides = enotf_get_condition_overrides();
    $additions = enotf_get_condition_additions();

    $jsBase = [];
    foreach ($base as $key => $rule) {
        $jsBase[$key] = [
            'html'    => $rule['html'],
            'db'      => $rule['db'],
            'section' => $rule['section'],
        ];
    }

    $jsOverrides = [];
    foreach ($overrides as $tz => $keys) {
        $jsOverrides[(string)$tz] = $keys;
    }

    $jsAdditions = [];
    foreach ($additions as $tz => $fields) {
        $jsAdditions[(string)$tz] = [];
        foreach ($fields as $key => $rule) {
            $jsAdditions[(string)$tz][$key] = [
                'html'    => $rule['html'],
                'db'      => $rule['db'],
                'section' => $rule['section'],
            ];
        }
    }

    return [
        'base'      => $jsBase,
        'overrides' => $jsOverrides,
        'additions' => $jsAdditions,
    ];
}
