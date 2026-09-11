<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * Erstbefund- und Maßnahmen-Optionslisten (ABCDE-Schema plus Basismaßnahmen).
 * Arrays behalten die Anzeige-Reihenfolge der Alt-Formulare (deshalb sind Codes
 * teilweise nicht aufsteigend sortiert).
 *
 * Quellen im Alt-System (plugins/enotf/templates/enotf/protokoll/):
 * - erstbefund/atemwege/1.php (awfrei_1), 2.php (zyanose_1)
 * - erstbefund/atmung/1.php (b_symptome), 2.php (b_auskult)
 * - erstbefund/kreislauf/1.php (c_kreislauf), 3.php (c_puls_reg/rad), 4.php (c_rekap), 5.php (c_blutung)
 * - erstbefund/ekg/index.php (c_ekg)
 * - erstbefund/neurologie/1.php (d_bewusstsein), 2.php (d_ex_1), 3_1.php (d_pupillenw_1/2),
 *   3_2.php (d_lichtreakt_1/2), 4.php (d_gcs_1/2/3)
 * - erstbefund/psychisch/index.php (psych[])
 * - erstbefund/erweitern/1.php (Bodymap v_muster_*), 2.php (sz_nrs, sz_toleranz_1)
 * - massnahmen/atemwege/1.php (awsicherung_neu + Checkbox-Flags)
 * - massnahmen/atmung/1.php (b_beatmung), 2.php (o2gabe)
 * - massnahmen/weitere/1.php (lagerung), 2.php (Checkbox-Flags), 3.php (rettungstechnik[])
 *
 * Abgleich gegen print/index.php: siehe README.md (u. a. c_kreislauf 3 vs. 99).
 */
final class BefundCatalog
{
    // ---------------------------------------------------------------- A - Atemwege

    /** awfrei_1 (Atemweg): Code => Label. */
    public const AWFREI = [
        1 => 'frei',
        2 => 'gefährdet',
        3 => 'verlegt',
    ];

    /** zyanose_1: Code => Label. */
    public const ZYANOSE = [
        1 => 'Nein',
        2 => 'Ja',
    ];

    // ---------------------------------------------------------------- B - Atmung

    /** b_symptome: Code => Label, in Formular-Reihenfolge. */
    public const B_SYMPTOME = [
        0 => 'unauffällig',
        1 => 'Dyspnoe',
        3 => 'Schnappatmung',
        2 => 'Apnoe',
        5 => 'Beatmung',
        6 => 'Hyperventilation',
        4 => 'sonstige path. Atemmuster',
        99 => 'nicht untersucht',
    ];

    /** b_auskult: Code => Label. */
    public const B_AUSKULT = [
        0 => 'unauffällig',
        1 => 'Spastik',
        2 => 'Stridor',
        3 => 'Rasselgeräusche',
        4 => 'Andere Pathologien',
        98 => 'nicht beurteilbar',
        99 => 'nicht untersucht',
    ];

    // ---------------------------------------------------------------- C - Kreislauf

    /**
     * c_kreislauf: Code => Label (Formular erstbefund/kreislauf/1.php).
     * print/index.php mappt abweichend 3 => 'Nicht beurteilbar' — siehe C_KREISLAUF_LEGACY.
     */
    public const C_KREISLAUF = [
        1 => 'stabil',
        2 => 'instabil',
        99 => 'nicht beurteilbar',
    ];

    /** Nur im Druck-Template vorhandenes Alt-Mapping; Formular schreibt 99. */
    public const C_KREISLAUF_LEGACY = [
        3 => 'Nicht beurteilbar',
    ];

    /** c_puls_reg: Code => Label. */
    public const C_PULS_REG = [
        1 => 'regelmäßig',
        2 => 'arrhythmisch',
    ];

    /**
     * c_puls_rad: Code => Label. Kurzlabels ohne "Radialispuls"-Präfix —
     * das Gruppenlabel RADIALISPULS steht im Formular direkt darüber.
     */
    public const C_PULS_RAD = [
        1 => 'tastbar',
        2 => 'nicht tastbar',
    ];

    /** c_rekap (Rekapillarisierungszeit): Code => Label. */
    public const C_REKAP = [
        1 => '< 2 s',
        2 => '> 2 s',
    ];

    /** c_blutung: Code => Label (Druck rendert 2 als "starke Blutung"). */
    public const C_BLUTUNG = [
        1 => 'nein',
        2 => 'ja',
    ];

    /** c_ekg: Code => Label, in Formular-Reihenfolge (ekg/index.php). */
    public const C_EKG = [
        1 => 'Sinusrhythmus',
        3 => 'Absolute Arrhythmie',
        5 => 'AV-Block II',
        51 => 'AV-Block III',
        4 => 'Kammerflimmern,-flattern',
        41 => 'Pulslose elektrische Aktivität',
        6 => 'Asystolie',
        21 => 'Schrittmacherrhythmus',
        2 => 'Infarkt EKG / STEMI',
        7 => 'Linksschenkelblock',
        71 => 'Rechtsschenkelblock',
        97 => 'Sonstige',
        98 => 'Nicht beurteilbar',
        99 => 'Kein EKG',
    ];

    // ---------------------------------------------------------------- D - Neurologie

    /** d_bewusstsein: Code => Label. */
    public const D_BEWUSSTSEIN = [
        1 => 'wach',
        2 => 'analgosediert / Narkose',
        3 => 'reagiert auf Ansprache',
        4 => 'reagiert auf Schmerzreiz',
        5 => 'bewusstlos',
        97 => 'Sonstige',
        98 => 'nicht beurteilbar',
        99 => 'nicht untersucht',
    ];

    /** d_pupillenw_1 (links) / d_pupillenw_2 (rechts): Code => Label. */
    public const D_PUPILLENWEITE = [
        1 => 'weit',
        2 => 'mittel',
        3 => 'eng',
        4 => 'entrundet',
        99 => 'Nicht untersucht',
    ];

    /** d_lichtreakt_1 (links) / d_lichtreakt_2 (rechts): Code => Label. */
    public const D_LICHTREAKTION = [
        1 => 'prompt',
        2 => 'träge',
        3 => 'keine',
        99 => 'Nicht untersucht',
    ];

    /**
     * GCS-Teilwerte. Gespeichert wird der Abstand zum Maximum:
     * Punkte = 4 - d_gcs_1 (Augen), 5 - d_gcs_2 (verbal), 6 - d_gcs_3 (motorisch).
     */
    public const D_GCS_AUGEN = [
        0 => 'spontan',
        1 => 'auf Aufforderung',
        2 => 'auf Schmerzreiz',
        3 => 'kein Öffnen',
    ];

    public const D_GCS_VERBAL = [
        0 => 'orientiert',
        1 => 'desorientiert',
        2 => 'inadäquate Äußerungen',
        3 => 'unverständliche Laute',
        4 => 'keine Reaktion',
    ];

    public const D_GCS_MOTORIK = [
        0 => 'folgt Aufforderung',
        1 => 'gezielte Abwehrbewegungen',
        2 => 'ungezielte Abwehrbewegungen',
        3 => 'Beugesynergismen',
        4 => 'Strecksynergismen',
        5 => 'keine Reaktion',
    ];

    /** d_ex_1 (Extremitätenbewegung): Code => Label. */
    public const D_EXTREMITAETEN = [
        1 => 'uneingeschränkt',
        2 => 'leicht eingeschränkt',
        3 => 'stark eingeschränkt',
        99 => 'nicht beurteilbar',
    ];

    // ---------------------------------------------------------------- E / weitere Befunde

    /**
     * psych: Code => Label (Mehrfachauswahl als JSON-Array von int).
     * 1, 98 und 99 sind im Alt-Frontend jeweils exklusiv zu allen anderen.
     */
    public const PSYCH = [
        1 => 'unauffällig',
        2 => 'aggressiv',
        3 => 'depressiv',
        4 => 'wahnhaft',
        5 => 'verwirrt',
        6 => 'verlangsamt',
        7 => 'euphorisch',
        8 => 'erregt',
        9 => 'ängstlich',
        10 => 'suizidal',
        11 => 'motorisch unruhig',
        12 => 'Sonstige',
        98 => 'nicht beurteilbar',
        99 => 'nicht untersucht',
    ];

    /** psych-Codes, die jeweils alle anderen ausschließen. */
    public const PSYCH_EXKLUSIV = [1, 98, 99];

    /** sz_nrs (Schmerzskala): Code => Label. */
    public const SZ_NRS = [
        0 => 'keine',
        1 => '1',
        2 => '2',
        3 => '3',
        4 => '4',
        5 => '5',
        6 => '6',
        7 => '7',
        8 => '8',
        9 => '9',
        10 => '10',
        98 => 'nicht erhoben',
        99 => 'nicht beurteilbar',
    ];

    /** sz_toleranz_1: Code => Label. */
    public const SZ_TOLERANZ = [
        1 => 'Tolerabel',
        2 => 'Nicht tolerabel',
    ];

    /** Bodymap: Regionsfeld => Anzeigename (Schweregrad-Feld ist jeweils <feld>1). */
    public const V_MUSTER_REGIONEN = [
        'v_muster_k' => 'Schädel-Hirn',
        'v_muster_w' => 'Wirbelsäule',
        'v_muster_t' => 'Thorax',
        'v_muster_a' => 'Abdomen',
        'v_muster_al' => 'Obere Extremitäten',
        'v_muster_bl' => 'Untere Extremitäten',
    ];

    /** Bodymap-Schweregrad (v_muster_k, v_muster_w, …): Code => Label (Buttons in erweitern/1.php). */
    public const V_MUSTER_SCHWERE = [
        1 => 'Keine',
        2 => 'Leicht',
        3 => 'Mittel',
        4 => 'Schwer',
    ];

    /** Nur im Druck-Template vorhandener Zusatzwert für die Schweregrad-Anzeige. */
    public const V_MUSTER_SCHWERE_LEGACY = [
        99 => 'Nicht untersucht',
    ];

    /** Bodymap-Verletzungsart (v_muster_k1, v_muster_w1, …): Code => Label. */
    public const V_MUSTER_ART = [
        1 => 'Offen',
        2 => 'Geschlossen',
    ];

    // ---------------------------------------------------------------- Maßnahmen

    /** awsicherung_neu: Code => Label, in Formular-Reihenfolge (massnahmen/atemwege/1.php). */
    public const AWSICHERUNG_NEU = [
        1 => 'keine',
        2 => 'Endotrachealtubus',
        6 => 'Wendl-Tubus',
        3 => 'Larynxmaske',
        5 => 'Larynxtubus',
        4 => 'Guedeltubus',
        99 => 'Sonstige',
    ];

    /** b_beatmung: Code => Label. */
    public const B_BEATMUNG = [
        1 => 'Spontanatmung',
        2 => 'Assistierte Beatmung',
        3 => 'Kontrollierte Beatmung',
        4 => 'Maschinelle Beatmung',
        5 => 'keine',
    ];

    /** o2gabe: Code => Label (0 = keine, sonst Liter pro Minute). */
    public const O2GABE = [
        0 => 'keine',
        1 => '1 L/min',
        2 => '2 L/min',
        3 => '3 L/min',
        4 => '4 L/min',
        5 => '5 L/min',
        6 => '6 L/min',
        7 => '7 L/min',
        8 => '8 L/min',
        9 => '9 L/min',
        10 => '10 L/min',
        11 => '11 L/min',
        12 => '12 L/min',
        13 => '13 L/min',
        14 => '14 L/min',
        15 => '15 L/min',
    ];

    /** lagerung: Code => Label, in Formular-Reihenfolge (99 steht dort vor 6). */
    public const LAGERUNG = [
        1 => 'OK Hochlagerung',
        2 => 'Flachlagerung',
        3 => 'Schocklagerung',
        4 => 'stabile Seitenlage',
        5 => 'sitzender Transport',
        99 => 'sonstige Lagerung',
        6 => 'keine',
    ];

    /**
     * rettungstechnik: Code => Label (Mehrfachauswahl als JSON-Array von int).
     * Anders als bei psych ist Code 1 hier KEIN Exklusivwert ("keine"), sondern
     * "Spineboard" — die Exklusiv-Kachel "keine" läuft über die Quickfill-Logik.
     */
    public const RETTUNGSTECHNIK = [
        1 => 'Spineboard',
        2 => 'KED-System',
        3 => 'Beckenschlinge',
        4 => 'Schaufeltrage',
        5 => 'Vakuummatratze',
        6 => 'SAMsplint',
        99 => 'sonstige Immobilisation',
    ];

    /** Boolesche Maßnahmen-Checkboxen (Spalte => Label, gespeichert als 1/0). */
    public const MASSNAHMEN_FLAGS = [
        'awsicherung_1' => 'Atemwege freim.',
        'awsicherung_2' => 'Absaugen',
        'entlastungspunktion' => 'Entlastungspunktion',
        'hws_immo' => 'HWS-Immobilisation',
        'waerme_passiv' => 'passiver Wärmeerhalt',
        'waerme_aktiv' => 'aktiver Wärmeerhalt',
        'e_reposition' => 'Reposition',
        'e_verband' => 'Verband',
        'e_krintervention' => 'Krisenintervention',
        'e_kuehlung' => 'Kühlung',
        'e_narkose' => 'Notfallnarkose',
        'e_tourniquet' => 'Tourniquet',
        'e_cpr' => 'CPR / HLW',
    ];
}
