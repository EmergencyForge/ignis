<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * Diagnose-Katalog (diagnose_haupt als einzelner Code, diagnose_weitere als JSON-Array
 * derselben Codes).
 *
 * Quellen im Alt-System:
 * - plugins/enotf/templates/enotf/protokoll/diagnose/1_1…1_10_11.php (Radio-Werte diagnose_haupt)
 * - plugins/enotf/templates/enotf/protokoll/diagnose/2_1…2_10_11.php (Checkbox-Werte diagnose_weitere[])
 * - plugins/enotf/templates/enotf/print/index.php ($diagnose_labels, ab Z. 917)
 * - plugins/enotf/templates/enotf/schnittstelle/voranmeldung.php ($diagnose_labels, ab Z. 293)
 *
 * Die drei Label-Quellen (Formularblätter, Print, Voranmeldung Z. 293) sind identisch —
 * einzige Abweichung: Die Trauma-Blätter (1_10_1…1_10_10) zeigen als Radio-Label nur den
 * Schweregrad ("leicht" / "mittel" / "schwer" / "tödlich"), weil die Region über die
 * Blattnavigation gewählt wird. Hier stehen die vollen Labels aus Print/Voranmeldung.
 *
 * ACHTUNG: voranmeldung.php enthält bei Z. 148 ein ZWEITES, veraltetes Label-Array, das nur
 * für den Discord-Webhook-Text benutzt wird und ab Code 75 massiv abweicht — siehe
 * LEGACY_WEBHOOK_LABELS und README.md.
 */
final class DiagnoseCatalog
{
    /** Code => Label, wie in den Diagnose-Blättern gespeichert und in Print/Voranmeldung angezeigt. */
    public const LABELS = [
        // ZNS (1-9)
        1 => 'Schlaganfall / TIA / ICB',
        2 => 'ICB (klin. Diagn.)',
        3 => 'SAB (klin. Diagn.)',
        4 => 'Krampfanfall',
        5 => 'Status Epilepticus',
        6 => 'Meningitis / Encephalitis',
        9 => 'sonstige Erkrankung ZNS',

        // Herz-Kreislauf (11-29)
        11 => 'ACS / NSTEMI',
        12 => 'ACS / STEMI',
        13 => 'Kardiogener Schock',
        14 => 'tachykarde Arrhythmie',
        15 => 'bradykarde Arrhythmie',
        16 => 'Schrittmacher-/ICD Fehlfunktion',
        17 => 'Lungenembolie',
        18 => 'Lungenödem',
        19 => 'hypertensiver Notfall',
        20 => 'Aortenaneurysma',
        21 => 'Hypotonie',
        22 => 'Synkope',
        23 => 'Thrombose / art. Verschluss',
        24 => 'Herz-Kreislauf-Stillstand',
        25 => 'Schock unklarer Genese',
        26 => 'unklarer Thoraxschmerz',
        27 => 'orthostatische Fehlregulation',
        28 => 'hypertensive Krise / Entgleisung',
        29 => 'sonstige Erkrankung Herz-Kreislauf',

        // Atemwege (31-49)
        31 => 'Asthma (Anfall)',
        32 => 'Status Asthmaticus',
        33 => 'exacerbierte COPD',
        34 => 'Aspiration',
        35 => 'Pneumonie / Bronchitis',
        36 => 'Hyperventilation',
        37 => 'Spontanpneumothorax',
        38 => 'Hämoptysis',
        39 => 'Dyspnoe unklarer Ursache',
        49 => 'sonstige Erkrankung Atmung',

        // Abdomen (51-59)
        51 => 'akutes Abdomen',
        52 => 'obere GI-Blutung',
        53 => 'untere GI-Blutung',
        54 => 'Gallenkolik',
        55 => 'Nierenkolik',
        56 => 'Kolik allgemein',
        59 => 'sonstige Erkrankung Abdomen',

        // Psychiatrie (61-69)
        61 => 'psychischer Ausnahmezustand',
        62 => 'Depression',
        63 => 'Manie',
        64 => 'Intoxikation',
        65 => 'Entzug, Delir',
        66 => 'Suizidalität',
        67 => 'psychosoziale Krise',
        69 => 'sonstige Erkrankung Psychiatrie',

        // Stoffwechsel (71-79)
        71 => 'Hypoglykämie',
        72 => 'Hyperglykämie',
        73 => 'Urämie / ANV',
        74 => 'Exsikkose',
        75 => 'bek. Dialysepflicht',
        79 => 'sonstige Erkrankung Stoffwechsel',

        // Sonstige (81-99)
        81 => 'Anaphylaxie Grad 1/2',
        82 => 'Anaphylaxie Grad 3/4',
        83 => 'Infekt / Sepsis / Schock',
        84 => 'Hitzeerschöpfung / Hitzschlag',
        85 => 'Unterkühlung / Erfrierung',
        86 => 'akute Lumbago',
        87 => 'Palliative Situation',
        88 => 'medizinische Behandlungskompl.',
        89 => 'Epistaxis / HNO-Erkrankung',
        91 => 'urologische Erkrankung',
        92 => 'unklare Erkrankung',
        93 => 'akzidentelle Intoxikation',
        94 => 'Augenerkrankung',
        99 => 'Keine Erkrankung / Verletzung feststellbar',

        // Trauma - Schädel-Hirn (101-104)
        101 => 'Trauma Schädel-Hirn leicht',
        102 => 'Trauma Schädel-Hirn mittel',
        103 => 'Trauma Schädel-Hirn schwer',
        104 => 'Trauma Schädel-Hirn tödlich',

        // Trauma - Gesicht (111-114)
        111 => 'Trauma Gesicht leicht',
        112 => 'Trauma Gesicht mittel',
        113 => 'Trauma Gesicht schwer',
        114 => 'Trauma Gesicht tödlich',

        // Trauma - HWS (121-124)
        121 => 'Trauma HWS leicht',
        122 => 'Trauma HWS mittel',
        123 => 'Trauma HWS schwer',
        124 => 'Trauma HWS tödlich',

        // Trauma - Thorax (131-134)
        131 => 'Trauma Thorax leicht',
        132 => 'Trauma Thorax mittel',
        133 => 'Trauma Thorax schwer',
        134 => 'Trauma Thorax tödlich',

        // Trauma - Abdomen (141-144)
        141 => 'Trauma Abdomen leicht',
        142 => 'Trauma Abdomen mittel',
        143 => 'Trauma Abdomen schwer',
        144 => 'Trauma Abdomen tödlich',

        // Trauma - BWS / LWS (151-153)
        151 => 'Trauma BWS / LWS leicht',
        152 => 'Trauma BWS / LWS mittel',
        153 => 'Trauma BWS / LWS schwer',

        // Trauma - Becken (161-163)
        161 => 'Trauma Becken leicht',
        162 => 'Trauma Becken mittel',
        163 => 'Trauma Becken schwer',

        // Trauma - obere Extremitäten (171-173)
        171 => 'Trauma obere Extremitäten leicht',
        172 => 'Trauma obere Extremitäten mittel',
        173 => 'Trauma obere Extremitäten schwer',

        // Trauma - untere Extremitäten (181-183)
        181 => 'Trauma untere Extremitäten leicht',
        182 => 'Trauma untere Extremitäten mittel',
        183 => 'Trauma untere Extremitäten schwer',

        // Trauma - Weichteile (191-193)
        191 => 'Trauma Weichteile leicht',
        192 => 'Trauma Weichteile mittel',
        193 => 'Trauma Weichteile schwer',

        // Trauma - spezielle (201-209)
        201 => 'Verbrennung / Verbrühung',
        202 => 'Inhalationstrauma',
        203 => 'Elektrounfall',
        204 => '(beinahe-) Ertrinken',
        205 => 'Tauchunfall',
        206 => 'Verätzung',
        209 => 'Sonstige',
    ];

    /**
     * Kategoriebaum, wie ihn die Diagnose-Blattnavigation abbildet
     * (diagnose/1.php bzw. 2.php; Trauma-Unterblätter in 1_10.php).
     * 'sheet' = Blattnummer der Hauptdiagnose (das Pendant für diagnose_weitere ist 2_x).
     */
    public const CATEGORIES = [
        'zns' => [
            'label' => 'ZNS',
            'sheet' => '1_1',
            'codes' => [1, 2, 3, 4, 5, 6, 9],
        ],
        'herz_kreislauf' => [
            'label' => 'Herz-Kreislauf',
            'sheet' => '1_2',
            'codes' => [11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29],
        ],
        'atemwege' => [
            'label' => 'Atemwege',
            'sheet' => '1_3',
            'codes' => [31, 32, 33, 34, 35, 36, 37, 38, 39, 49],
        ],
        'abdomen' => [
            'label' => 'Abdomen',
            'sheet' => '1_4',
            'codes' => [51, 52, 53, 54, 55, 56, 59],
        ],
        'psychiatrie' => [
            'label' => 'Psychiatrie',
            'sheet' => '1_5',
            'codes' => [61, 62, 63, 64, 65, 66, 67, 69],
        ],
        'stoffwechsel' => [
            'label' => 'Stoffwechsel',
            'sheet' => '1_6',
            'codes' => [71, 72, 73, 74, 75, 79],
        ],
        'sonstige' => [
            'label' => 'Sonstige',
            'sheet' => '1_9',
            'codes' => [81, 82, 83, 84, 85, 86, 87, 88, 89, 91, 92, 93, 94, 99],
        ],
        'trauma' => [
            'label' => 'Trauma',
            'sheet' => '1_10',
            'codes' => [
                101, 102, 103, 104,
                111, 112, 113, 114,
                121, 122, 123, 124,
                131, 132, 133, 134,
                141, 142, 143, 144,
                151, 152, 153,
                161, 162, 163,
                171, 172, 173,
                181, 182, 183,
                191, 192, 193,
                201, 202, 203, 204, 205, 206, 209,
            ],
            'subcategories' => [
                'schaedel_hirn' => ['label' => 'Schädel-Hirn', 'sheet' => '1_10_1', 'codes' => [101, 102, 103, 104]],
                'gesicht' => ['label' => 'Gesicht', 'sheet' => '1_10_2', 'codes' => [111, 112, 113, 114]],
                'hws' => ['label' => 'HWS', 'sheet' => '1_10_3', 'codes' => [121, 122, 123, 124]],
                'thorax' => ['label' => 'Thorax', 'sheet' => '1_10_4', 'codes' => [131, 132, 133, 134]],
                'abdomen' => ['label' => 'Abdomen', 'sheet' => '1_10_5', 'codes' => [141, 142, 143, 144]],
                'bws_lws' => ['label' => 'BWS / LWS', 'sheet' => '1_10_6', 'codes' => [151, 152, 153]],
                'becken' => ['label' => 'Becken', 'sheet' => '1_10_7', 'codes' => [161, 162, 163]],
                'obere_extremitaeten' => ['label' => 'obere Extremitäten', 'sheet' => '1_10_8', 'codes' => [171, 172, 173]],
                'untere_extremitaeten' => ['label' => 'untere Extremitäten', 'sheet' => '1_10_9', 'codes' => [181, 182, 183]],
                'weichteile' => ['label' => 'Weichteile', 'sheet' => '1_10_10', 'codes' => [191, 192, 193]],
                'spezielle' => ['label' => 'spezielle', 'sheet' => '1_10_11', 'codes' => [201, 202, 203, 204, 205, 206, 209]],
            ],
        ],
    ];

    /**
     * VERALTETES Label-Array aus voranmeldung.php Z. 148 — wurde dort nur benutzt, um für den
     * Discord-Webhook einen Diagnose-Text aufzulösen. Bis Code 74 deckungsgleich mit LABELS,
     * ab 75 komplett andere Bedeutungen (75 Hypothermie statt bek. Dialysepflicht, 81-89
     * Verbrennung/Umwelt statt Anaphylaxie/Sonstige, 91-99 SHT/Polytrauma statt urologisch/unklar,
     * 101-119 Gynäkologie/Pädiatrie statt Trauma, zusätzlich 76, 105, 109, 115, 116, 119, 999).
     * Diese Codes wurden von den Formularblättern NIE geschrieben — nur hier dokumentiert,
     * damit die Abweichung nicht verloren geht. Nicht für Neu-Daten verwenden.
     */
    public const LEGACY_WEBHOOK_LABELS = [
        1 => 'Schlaganfall / TIA / ICB',
        2 => 'ICB (klin. Diagn.)',
        3 => 'SAB (klin. Diagn.)',
        4 => 'Krampfanfall',
        5 => 'Status Epilepticus',
        6 => 'Meningitis / Encephalitis',
        9 => 'sonstige Erkrankung ZNS',
        11 => 'ACS / NSTEMI',
        12 => 'ACS / STEMI',
        13 => 'Kardiogener Schock',
        14 => 'tachykarde Arrhythmie',
        15 => 'bradykarde Arrhythmie',
        16 => 'Schrittmacher-/ICD Fehlfunktion',
        17 => 'Lungenembolie',
        18 => 'Lungenödem',
        19 => 'hypertensiver Notfall',
        20 => 'Aortenaneurysma',
        21 => 'Hypotonie',
        22 => 'Synkope',
        23 => 'Thrombose / art. Verschluss',
        24 => 'Herz-Kreislauf-Stillstand',
        25 => 'Schock unklarer Genese',
        26 => 'unklarer Thoraxschmerz',
        27 => 'orthostatische Fehlregulation',
        28 => 'hypertensive Krise / Entgleisung',
        29 => 'sonstige Erkrankung Herz-Kreislauf',
        31 => 'Asthma (Anfall)',
        32 => 'Status Asthmaticus',
        33 => 'exacerbierte COPD',
        34 => 'Aspiration',
        35 => 'Pneumonie / Bronchitis',
        36 => 'Hyperventilation',
        37 => 'Spontanpneumothorax',
        38 => 'Hämoptysis',
        39 => 'Dyspnoe unklarer Ursache',
        49 => 'sonstige Erkrankung Atmung',
        51 => 'akutes Abdomen',
        52 => 'obere GI-Blutung',
        53 => 'untere GI-Blutung',
        54 => 'Gallenkolik',
        55 => 'Nierenkolik',
        56 => 'Kolik allgemein',
        59 => 'sonstige Erkrankung Abdomen',
        61 => 'psychischer Ausnahmezustand',
        62 => 'Depression',
        63 => 'Manie',
        64 => 'Intoxikation',
        65 => 'Entzug, Delir',
        66 => 'Suizidalität',
        67 => 'psychosoziale Krise',
        69 => 'sonstige Erkrankung Psychiatrie',
        71 => 'Hypoglykämie',
        72 => 'Hyperglykämie',
        73 => 'Urämie / ANV',
        74 => 'Exsikkose',
        75 => 'Hypothermie',
        76 => 'Hyperthermie',
        79 => 'sonstige Erkrankung Stoffwechsel',
        81 => 'Verbrennung',
        82 => 'Erfrierung',
        83 => 'Verätzung',
        84 => 'Inhalationstrauma',
        85 => 'Stromunfall',
        86 => 'Ertrinkungsunfall',
        87 => 'Tauchunfall',
        88 => 'Strangulation',
        89 => 'sonstige Vergiftung / Umwelt',
        91 => 'SHT',
        92 => 'Polytrauma',
        93 => 'Thoraxtrauma',
        94 => 'Bauchtrauma',
        95 => 'Extremitätentrauma',
        96 => 'Wirbelsäulentrauma',
        97 => 'Beckenfraktur',
        98 => 'andere traumatologische Verletzung',
        99 => 'sonstiges Trauma',
        101 => 'Wochenbett',
        102 => 'Schwangerschaft',
        103 => 'Geburt',
        104 => 'Fehlgeburt',
        105 => 'gynäkologische Blutung',
        109 => 'sonstige gynäkologische Erkrankung',
        111 => 'Neugeborenes (U1)',
        112 => 'Frühgeburt',
        113 => 'Fieberkrampf',
        114 => 'Pseudokrupp',
        115 => 'Epiglottitis',
        116 => 'Fremdkörperaspiration',
        119 => 'sonstige pädiatrische Erkrankung',
        999 => 'Sonstiges',
    ];

    public static function label(int $code): ?string
    {
        return self::LABELS[$code] ?? null;
    }

    /** Kategorie-Schlüssel (Top-Level) zu einem Diagnose-Code, null wenn unbekannt. */
    public static function categoryKey(int $code): ?string
    {
        foreach (self::CATEGORIES as $key => $category) {
            if (in_array($code, $category['codes'], true)) {
                return $key;
            }
        }

        return null;
    }
}
