<?php

/**
 * Section: Erstbefund — eNOTF v2 im v1-Look.
 *
 * Zwei Modi, beide als getreue Nachbauten der v1-Seiten
 * (plugins/enotf/templates/enotf/protokoll/erstbefund/):
 *
 *   ÜBERSICHT (ohne ?t): Kachel-Seite wie v1 index.php — Themen-Spalte
 *     links (edivi__interactbutton-more mit data-requires-Färbung) und
 *     edivi__box-Kacheln mit readonly-Zusammenfassungen.
 *
 *   THEMA (?t=atemwege … ?t=messwerte): Aufbau der jeweiligen v1-
 *     Unterseite — Themen-Spalte, Unterthemen-Spalte (mit „ohne path.
 *     Befund"-Quickfill) und EINE Frage als btn-check-Spalte(n).
 *     Bessere Technik als v1: ALLE Schritte eines Themas stehen im DOM,
 *     wizard.js schaltet ohne Full-Reload um (?q=N per History-Replace,
 *     Einstieg beim ersten unbeantworteten Schritt); gespeichert wird
 *     über den v2-Batch-Autosave (names/Formate unverändert).
 *
 * Sonderfälle (Formate wie v1):
 *   - psych: JSON-Array von int, 1/98/99 exklusiv — Checkboxen
 *     name="psych[]" + data-autosave-ignore, Speicherung über
 *     edivi-bridge.js (EnotfV2Autosave.queue('psych', …)).
 *   - bz: Speicherung IMMER mg/dl, Eingabe in ENOTF_BZ_UNIT
 *     (BloodSugarHelper) — Konvertierung im Messwerte-Script.
 *   - GCS: d_gcs_1/2/3 speichern den Abstand zum Maximum.
 *   - Bodymap: v_muster_X = Schweregrad (1–4, Legacy 99 wird lesend
 *     toleriert), v_muster_X1 = Wundart Offen/Geschlossen. Klickbare
 *     SVG-Körpergrafik wie v1 erweitern/1.php: Zonen-Klick öffnet das
 *     Detail-Panel, gespeichert wird über den v2-Batch-Autosave.
 *
 * @var array<string,mixed> $protokoll
 * @var string $enr
 * @var bool $istGesperrt
 * @var string|null $fokusThema   ?t-Param aus ProtokollController::show
 */

use Plugin\Enotf\Helpers\BloodSugarHelper;
use Plugin\EnotfV2\Catalogs\BefundCatalog;
use Plugin\EnotfV2\Helpers\EnotfV2Url;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$wert = static fn (string $feld): string => (string) ($protokoll[$feld] ?? '');

$sectionUrl = EnotfV2Url::protokoll($enr, 'erstbefund');
$fokusUrl   = static fn (string $thema): string => $sectionUrl . '?t=' . rawurlencode($thema);

// ── Blutzucker: Anzeige-Einheit aus Config, Speicherung mg/dl ────────
$bzHelper  = new BloodSugarHelper();
$bzUnit    = $bzHelper->getCurrentUnit();
$bzRaw     = trim($wert('bz'));
$bzDisplay = $bzRaw;
if (
    $bzRaw !== ''
    && !in_array(strtolower($bzRaw), ['ng', 'nm'], true)
    && is_numeric(str_replace(',', '.', $bzRaw))
) {
    $bzDisplay = str_replace('.', ',', (string) $bzHelper->toDisplayUnit($bzRaw));
}

// ── psych: JSON-Array von int ────────────────────────────────────────
$psychAktiv = [];
$psychRaw   = $protokoll['psych'] ?? null;
if (is_string($psychRaw) && $psychRaw !== '' && $psychRaw !== '0') {
    $decoded = json_decode($psychRaw, true);
    if (is_array($decoded)) {
        $psychAktiv = array_map('intval', $decoded);
    }
}

// ── Spalten-Renderer: edivi__interactbutton mit btn-check (v1 1:1) ───
$btnCol = static function (string $name, array $options, array $opts = []) use ($protokoll, $e, $istGesperrt): string {
    $current = (string) ($protokoll[$name] ?? '');
    $green   = $opts['green'] ?? null;   // Code mit edivi__unauffaellig (grüne Schrift)
    $header  = $opts['header'] ?? null;  // edivi__interactbutton-text-Überschrift
    // Bootstrap col-2 statt Tailwind w-2/12: die Tailwind-4-Utilities
    // liegen in @layer utilities und verlieren gegen Bootstraps
    // ungelayertes .row > * { width:100% } (deshalb stapeln die v1-
    // Unterseiten seit dem Tailwind-Upgrade — hier bewusst vermieden)
    $width   = $opts['width'] ?? 'col-2';

    $html = '<div class="' . $e($width) . ' d-flex flex-column edivi__interactbutton px-3">';
    if ($header !== null) {
        $html .= '<label class="edivi__interactbutton-text">' . $e($header) . '</label>';
    }
    foreach ($options as $code => $optLabel) {
        $id      = $name . '-' . $code;
        $checked = $current !== '' && $current === (string) $code ? ' checked' : '';
        $cls     = ($green !== null && (string) $green === (string) $code) ? ' class="edivi__unauffaellig"' : '';
        $html .= '<input type="radio" class="btn-check" id="' . $e($id) . '" name="' . $e($name) . '" value="' . $e($code) . '"'
            . $checked . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
            . '<label for="' . $e($id) . '"' . $cls . '>' . $e($optLabel) . '</label>';
    }
    return $html . '</div>';
};

// v1-Labeltexte, wo sie vom Katalog abweichen (kreislauf/3.php)
$pulsRadLabels = [1 => 'Radialispuls tastbar', 2 => 'Radialispuls nicht tastbar'];

// EKG-Spaltenaufteilung wie v1 ekg/index.php
$ekgCol1 = [1, 3, 5, 51, 4, 41, 6];
$ekgCol2 = [21, 2, 7, 71, 97, 98, 99];
$pick    = static fn (array $codes, array $katalog): array => array_combine($codes, array_map(static fn ($c) => $katalog[$c], $codes));

// ── Themen-Definition (v1-Reihenfolge und -Beschriftung) ─────────────
// steps: label/requires (Subnav) + fields (Einstiegslogik) + html-Closure
$themen = [
    'atemwege' => [
        'label' => 'Atemwege', 'requires' => 'awfrei_1,zyanose_1',
        'quickfill' => ['id' => 'atemwege-ohne-path', 'label' => 'ohne path. Befund', 'data' => ['awfrei_1' => 1, 'zyanose_1' => 1]],
        'steps' => [
            ['label' => 'Atemwegszustand', 'requires' => 'awfrei_1', 'fields' => 'awfrei_1',
                'html' => static fn (): string => $btnCol('awfrei_1', BefundCatalog::AWFREI, ['green' => 1])],
            ['label' => 'Zyanose', 'requires' => 'zyanose_1', 'fields' => 'zyanose_1',
                'html' => static fn (): string => $btnCol('zyanose_1', BefundCatalog::ZYANOSE, ['green' => 1])],
        ],
    ],
    'atmung' => [
        'label' => 'Atmung', 'requires' => 'b_symptome,b_auskult',
        'quickfill' => ['id' => 'atmung-ohne-path', 'label' => 'ohne path. Befund', 'data' => ['b_symptome' => 0, 'b_auskult' => 0]],
        'steps' => [
            ['label' => 'Beurteilung Atmung', 'requires' => 'b_symptome', 'fields' => 'b_symptome',
                'html' => static fn (): string => $btnCol('b_symptome', BefundCatalog::B_SYMPTOME, ['green' => 0])],
            ['label' => 'Auskultation', 'requires' => 'b_auskult', 'fields' => 'b_auskult',
                'html' => static fn (): string => $btnCol('b_auskult', BefundCatalog::B_AUSKULT, ['green' => 0])],
        ],
    ],
    'kreislauf' => [
        'label' => 'Kreislauf', 'requires' => 'c_kreislauf,c_puls_rad,c_puls_reg',
        'quickfill' => ['id' => 'kreislauf-ohne-path', 'label' => 'ohne path. Befund', 'data' => ['c_kreislauf' => 1, 'c_puls_rad' => 1, 'c_puls_reg' => 1, 'c_rekap' => 1, 'c_blutung' => 1]],
        'steps' => [
            ['label' => 'Patientenzustand', 'requires' => 'c_kreislauf', 'fields' => 'c_kreislauf',
                'html' => static fn (): string => $btnCol('c_kreislauf', BefundCatalog::C_KREISLAUF, ['green' => 1])],
            ['label' => 'Rekap. Zeit', 'requires' => '', 'fields' => 'c_rekap',
                'html' => static fn (): string => $btnCol('c_rekap', BefundCatalog::C_REKAP, ['green' => 1])],
            ['label' => 'Puls', 'requires' => 'c_puls_rad,c_puls_reg', 'fields' => 'c_puls_reg,c_puls_rad',
                'html' => static fn (): string => $btnCol('c_puls_reg', BefundCatalog::C_PULS_REG, ['green' => 1])
                    . $btnCol('c_puls_rad', $pulsRadLabels, ['green' => 1])],
            ['label' => 'starke Blutung', 'requires' => '', 'fields' => 'c_blutung',
                'html' => static fn (): string => $btnCol('c_blutung', BefundCatalog::C_BLUTUNG, ['green' => 1])],
        ],
    ],
    'neurologie' => [
        'label' => 'Neurologie', 'requires' => 'd_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3',
        'quickfill' => ['id' => 'neuro-ohne-path', 'label' => 'ohne path. Befund', 'data' => ['d_bewusstsein' => 1, 'd_ex_1' => 1, 'd_pupillenw_1' => 2, 'd_pupillenw_2' => 2, 'd_lichtreakt_1' => 1, 'd_lichtreakt_2' => 1, 'd_gcs_1' => 0, 'd_gcs_2' => 0, 'd_gcs_3' => 0]],
        // Subnav wie v1 (Bewusstseinslage/Extremitäten/Pupillen/GCS);
        // „Pupillen" deckt die zwei Schritte Weite + Lichtreaktion ab
        // (v1s dritte Nav-Ebene 3_1/3_2 wird je Schritt mitgerendert)
        'subnav' => [
            ['label' => 'Bewusstseinslage', 'requires' => 'd_bewusstsein', 'goto' => 0],
            ['label' => 'Extremitätenbewegung', 'requires' => 'd_ex_1', 'goto' => 1],
            ['label' => 'Pupillen', 'requires' => 'd_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2', 'goto' => 2, 'covers' => '2,3'],
            ['label' => 'GCS', 'requires' => 'd_gcs_1,d_gcs_2,d_gcs_3', 'goto' => 4],
        ],
        'steps' => [
            ['fields' => 'd_bewusstsein',
                'html' => static fn (): string => $btnCol('d_bewusstsein', BefundCatalog::D_BEWUSSTSEIN, ['green' => 1])],
            ['fields' => 'd_ex_1',
                'html' => static fn (): string => $btnCol('d_ex_1', BefundCatalog::D_EXTREMITAETEN, ['green' => 1])],
            ['fields' => 'd_pupillenw_1,d_pupillenw_2', 'nav3' => true,
                'html' => static fn (): string => $btnCol('d_pupillenw_1', BefundCatalog::D_PUPILLENWEITE, ['header' => 'links'])
                    . $btnCol('d_pupillenw_2', BefundCatalog::D_PUPILLENWEITE, ['header' => 'rechts'])],
            ['fields' => 'd_lichtreakt_1,d_lichtreakt_2', 'nav3' => true,
                'html' => static fn (): string => $btnCol('d_lichtreakt_1', BefundCatalog::D_LICHTREAKTION, ['header' => 'links'])
                    . $btnCol('d_lichtreakt_2', BefundCatalog::D_LICHTREAKTION, ['header' => 'rechts'])],
            ['fields' => 'd_gcs_1,d_gcs_2,d_gcs_3',
                'html' => static fn (): string => $btnCol('d_gcs_1', BefundCatalog::D_GCS_AUGEN, ['header' => 'Augen öffnen'])
                    . $btnCol('d_gcs_2', BefundCatalog::D_GCS_VERBAL, ['header' => 'Beste verbale Reaktion'])
                    . $btnCol('d_gcs_3', BefundCatalog::D_GCS_MOTORIK, ['header' => 'Beste motorische Reaktion'])],
        ],
    ],
    'erweitern' => [
        'label' => 'Erweitern', 'requires' => '',
        'quickfill' => ['id' => 'erweitern-ohne-path', 'label' => 'keine', 'data' => ['v_muster_k' => 1, 'v_muster_w' => 1, 'v_muster_t' => 1, 'v_muster_a' => 1, 'v_muster_al' => 1, 'v_muster_bl' => 1]],
        'steps' => [
            ['label' => 'Verletzungen', 'requires' => '', 'fields' => '', 'html' => null /* eigener Block unten */],
            ['label' => 'Schmerzen', 'requires' => '', 'fields' => 'sz_nrs,sz_toleranz_1',
                'html' => static fn (): string => $btnCol('sz_nrs', $pick([0, 1, 2, 3, 4, 5, 6], BefundCatalog::SZ_NRS))
                    . $btnCol('sz_nrs', $pick([7, 8, 9, 10, 98, 99], BefundCatalog::SZ_NRS))
                    . $btnCol('sz_toleranz_1', BefundCatalog::SZ_TOLERANZ)],
        ],
    ],
    'ekg' => [
        'label' => 'EKG-Befund', 'requires' => 'c_ekg',
        'steps' => [
            ['fields' => 'c_ekg',
                'html' => static fn (): string => $btnCol('c_ekg', $pick($ekgCol1, BefundCatalog::C_EKG), ['green' => 1])
                    . $btnCol('c_ekg', $pick($ekgCol2, BefundCatalog::C_EKG))],
        ],
    ],
    'psychisch' => [
        'label' => 'psych. Zustand', 'requires' => 'psych',
        'steps' => [
            ['fields' => 'psych', 'html' => null /* eigener Block unten */],
        ],
    ],
    'messwerte' => [
        'label' => 'Messwerte', 'requires' => 'spo2,atemfreq,rrsys,herzfreq,bz',
        'steps' => [
            ['fields' => 'spo2,atemfreq,rrsys,herzfreq,bz', 'html' => null /* eigener Block unten */],
        ],
    ],
];

// Fokus-Thema validieren (unbekannt → Übersicht)
$fokus = $fokusThema ?? null;
if ($fokus !== null && !isset($themen[$fokus])) {
    $fokus = null;
}

// Messwerte-Ansicht nutzt die v1-Vitalparameter-/Keypad-Styles
// (divi.css scoped auf body[data-page="verlauf"]) und ist wie die
// v1-Seite (erstbefund/messwerte/index.php) chromelos: keine Topbar/
// Section-Nav, Rückweg über Abbrechen/Speichern
if ($fokus === 'messwerte') {
    $sectionBodyPage   = 'verlauf';
    $sectionChromeless = true;
}

// Server-Initialschritt: ohne explizites ?q KEIN Schritt offen — nur die
// Subnav-Spalte (v1: Themen-Index zeigt nur die Navigation). Themen OHNE
// Schritt-Links (ein Schritt, keine eigene Subnav — ekg/psychisch) zeigen
// ihren Schritt direkt, wie v1 dort direkt in die Antwort-Spalten springt.
// wizard.js entscheidet identisch (data-wiz-goto-Links vorhanden ja/nein).
$initialStep = -1;
if ($fokus !== null) {
    $qRaw = $_GET['q'] ?? null;
    if (is_string($qRaw) && preg_match('/^\d+$/', $qRaw)) {
        $initialStep = max(0, min(count($themen[$fokus]['steps']) - 1, (int) $qRaw - 1));
    } elseif (count($themen[$fokus]['steps']) === 1 && !isset($themen[$fokus]['subnav'])) {
        $initialStep = 0;
    }
}

// psych-Radio-Spaltenaufteilung wie v1 psychisch/index.php
$psychCol1 = [1, 2, 3, 4, 5, 6, 7, 8, 9];
$psychCol2 = [10, 11, 12, 98, 99];

// Checkbox-Spalte für psych (btn-check, name="psych[]" — Speicherung
// über edivi-bridge.js als JSON-Batch, deshalb data-autosave-ignore)
$psychCol = static function (array $codes) use ($e, $istGesperrt, $psychAktiv): string {
    $html = '<div class="col-2 d-flex flex-column edivi__interactbutton px-3">';
    foreach ($codes as $code) {
        $optLabel = BefundCatalog::PSYCH[$code];
        $checked  = in_array($code, $psychAktiv, true) ? ' checked' : '';
        $cls      = $code === 1 ? ' class="edivi__unauffaellig"' : '';
        $html .= '<input type="checkbox" class="btn-check" id="psych-' . $code . '" name="psych[]" value="' . $code . '"'
            . ' data-autosave-ignore' . $checked . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
            . '<label for="psych-' . $code . '"' . $cls . '>' . $e($optLabel) . '</label>';
    }
    return $html . '</div>';
};

// ── Übersichts-Labels (v1 index.php; Legacy-Codes lesbar halten) ─────
$lbl = static function (string $feld, array $katalog) use ($wert): string {
    $code = $wert($feld);
    if ($code === '') {
        return '';
    }
    return $katalog[(int) $code] ?? '';
};
$kreislaufLabels = BefundCatalog::C_KREISLAUF + BefundCatalog::C_KREISLAUF_LEGACY;
$schwereLabels   = BefundCatalog::V_MUSTER_SCHWERE + BefundCatalog::V_MUSTER_SCHWERE_LEGACY;

// Verletzungs-Zusammenfassung einer Region ("leicht / offen" wie v1)
$verletzung = static function (string $feld) use ($wert, $schwereLabels): string {
    $schwere = $wert($feld);
    if ($schwere === '') {
        return '';
    }
    $text = mb_strtolower($schwereLabels[(int) $schwere] ?? '');
    $art  = $wert($feld . '1');
    if ($art !== '') {
        $text .= ' / ' . mb_strtolower(BefundCatalog::V_MUSTER_ART[(int) $art] ?? '');
    }
    return $text;
};

// GCS-Summe (Punkte = max − Code), nur wenn alle drei Teilwerte gesetzt
$gcsSumme = '--';
if ($wert('d_gcs_1') !== '' && $wert('d_gcs_2') !== '' && $wert('d_gcs_3') !== '') {
    $gcsSumme = (string) ((4 - (int) $wert('d_gcs_1')) + (5 - (int) $wert('d_gcs_2')) + (6 - (int) $wert('d_gcs_3')));
}

$psychTexte = [];
foreach ($psychAktiv as $code) {
    if (isset(BefundCatalog::PSYCH[$code])) {
        $psychTexte[] = BefundCatalog::PSYCH[$code];
    }
}
$psychDisplay = implode(', ', $psychTexte);

// Themen-Spalte (v1: edivi__interactbutton-more in jeder Unterseite)
$themenSpalte = static function (?string $aktiv) use ($themen, $fokusUrl, $e): string {
    $html = '<div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">';
    foreach ($themen as $key => $thema) {
        $html .= '<a href="' . $e($fokusUrl($key)) . '"'
            . ($thema['requires'] !== '' ? ' data-requires="' . $e($thema['requires']) . '"' : '')
            . ($key === $aktiv ? ' class="active"' : '') . '>'
            . '<span>' . $e($thema['label']) . '</span></a>';
    }
    return $html . '</div>';
};

// Übersichts-Kachel-Input (readonly, edivi__input-check für die Färbung)
$checkInput = static function (string $label, string $value, string $id, bool $optional = false) use ($e): string {
    return '<div class="row my-2"><div class="col">'
        . '<label for="' . $e($id) . '" class="edivi__description">' . $label . '</label>'
        . '<input type="text" id="' . $e($id) . '" name="' . $e($id) . '" class="w-100 ignis-input'
        . ($optional ? '' : ' edivi__input-check') . '" value="' . $e($value) . '" readonly>'
        . '</div></div>';
};
?>

<?php if ($fokus === null): ?>

    <!-- ── ÜBERSICHT: Kachel-Seite (v1 erstbefund/index.php) ── -->
    <div class="row" style="margin-left: 0">
        <?php if (!$istGesperrt): ?>
            <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                <?php foreach ($themen as $key => $thema): ?>
                    <a href="<?= $e($fokusUrl($key)) ?>" <?= $thema['requires'] !== '' ? 'data-requires="' . $e($thema['requires']) . '"' : '' ?>>
                        <span><?= $e($thema['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="col edivi__overview-container">
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('atemwege')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Atemwege</h5>
                        <div class="col">
                            <?= $checkInput('Atemwegszustand', $lbl('awfrei_1', BefundCatalog::AWFREI), 'atemwegszustand') ?>
                            <?= $checkInput('Zyanose', $lbl('zyanose_1', BefundCatalog::ZYANOSE), 'zyanose') ?>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('atmung')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Atmung</h5>
                        <div class="col">
                            <?= $checkInput('Beurteilung Atmung', $lbl('b_symptome', BefundCatalog::B_SYMPTOME), 'beurteilung_atmung') ?>
                            <?= $checkInput('Auskultation', $lbl('b_auskult', BefundCatalog::B_AUSKULT), 'auskultation') ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('kreislauf')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Kreislauf</h5>
                        <div class="col">
                            <div class="row">
                                <div class="col"><?= $checkInput('Patientenzustand', $lbl('c_kreislauf', $kreislaufLabels), 'patientenzustand') ?></div>
                                <div class="col"><?= $checkInput('Rekap. Zeit', $lbl('c_rekap', BefundCatalog::C_REKAP), 'rekap') ?></div>
                                <div class="col"><?= $checkInput('EKG-Befund', $lbl('c_ekg', BefundCatalog::C_EKG), 'ekgbefund') ?></div>
                            </div>
                            <div class="row">
                                <div class="col"><?= $checkInput('Pulsqualität', $lbl('c_puls_reg', BefundCatalog::C_PULS_REG), 'pulsregelmaessig') ?></div>
                                <div class="col"><?= $checkInput('Radialispuls', $lbl('c_puls_rad', BefundCatalog::C_PULS_RAD), 'radialispuls') ?></div>
                                <div class="col"><?= $checkInput('starke Blutung', $lbl('c_blutung', BefundCatalog::C_BLUTUNG), 'blutung') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('neurologie')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Neurologie</h5>
                        <div class="col">
                            <div class="row">
                                <div class="col"><?= $checkInput('Bewusstseinslage', $lbl('d_bewusstsein', BefundCatalog::D_BEWUSSTSEIN), 'bewusstseinslage') ?></div>
                                <div class="col"><?= $checkInput('Extremitätenbewegung', $lbl('d_ex_1', BefundCatalog::D_EXTREMITAETEN), 'extremitaetenbewegung') ?></div>
                                <div class="col-4"><?= $checkInput('GCS', $gcsSumme, '_GCS_', true) ?></div>
                            </div>
                            <div class="row">
                                <div class="col"><?= $checkInput('Pupillenweite li', $lbl('d_pupillenw_1', BefundCatalog::D_PUPILLENWEITE), 'pupillenweite_li') ?></div>
                                <div class="col"><?= $checkInput('re', $lbl('d_pupillenw_2', BefundCatalog::D_PUPILLENWEITE), 'pupillenweite_re') ?></div>
                                <div class="col"><?= $checkInput('Lichtreaktion li', $lbl('d_lichtreakt_1', BefundCatalog::D_LICHTREAKTION), 'lichtreaktion_li') ?></div>
                                <div class="col"><?= $checkInput('re', $lbl('d_lichtreakt_2', BefundCatalog::D_LICHTREAKTION), 'lichtreaktion_re') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-8">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('erweitern')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Verletzungen</h5>
                        <div class="col">
                            <div class="row">
                                <div class="col"><?= $checkInput('Schädel-Hirn', $verletzung('v_muster_k'), 'schaedel_hirn') ?></div>
                                <div class="col"><?= $checkInput('Wirbelsäule', $verletzung('v_muster_w'), 'wirbelsaeule') ?></div>
                                <div class="col"><?= $checkInput('Thorax', $verletzung('v_muster_t'), 'thorax') ?></div>
                                <div class="col"><?= $checkInput('Abdomen', $verletzung('v_muster_a'), 'abdomen') ?></div>
                            </div>
                            <div class="row">
                                <div class="col"><?= $checkInput('Obere Extremitäten', $verletzung('v_muster_al'), 'obere_extremitaeten') ?></div>
                                <div class="col"><?= $checkInput('Untere Extremitäten', $verletzung('v_muster_bl'), 'untere_extremitaeten') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('erweitern')) ?>&q=2" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Schmerzen</h5>
                        <div class="col">
                            <?= $checkInput('Intensität', $lbl('sz_nrs', BefundCatalog::SZ_NRS), 'intensitaet', true) ?>
                            <?= $checkInput('Toleranz', $lbl('sz_toleranz_1', BefundCatalog::SZ_TOLERANZ), 'toleranz', true) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-4">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('psychisch')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Psyche</h5>
                        <div class="col">
                            <?= $checkInput('Psychischer Zustand', $psychDisplay, 'psychischer_zustand') ?>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('messwerte')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Vitalparameter</h5>
                        <div class="col">
                            <div class="row">
                                <div class="col"><?= $checkInput('SpO<sub>2</sub>', $wert('spo2'), 'ov_spo2') ?></div>
                                <div class="col"><?= $checkInput('AF', $wert('atemfreq'), 'ov_af') ?></div>
                                <div class="col"><?= $checkInput('etCO<sub>2</sub>', $wert('etco2'), 'ov_etco2', true) ?></div>
                                <div class="col"><?= $checkInput('HF', $wert('herzfreq'), 'ov_hf') ?></div>
                                <div class="col"><?= $checkInput('RR<sub>sys</sub>', $wert('rrsys'), 'ov_rrsys') ?></div>
                                <div class="col"><?= $checkInput('RR<sub>dia</sub>', $wert('rrdias'), 'ov_rrdia', true) ?></div>
                                <div class="col"><?= $checkInput('BZ', $bzDisplay, 'ov_bz') ?></div>
                                <div class="col"><?= $checkInput('Temp', $wert('temp'), 'ov_temp', true) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($fokus === 'messwerte'): ?>

    <!-- ── MESSWERTE: v1 messwerte/index.php (Vitalparameter + Keypad).
         Eingaben laufen NICHT über den Feld-Autosave (data-autosave-ignore
         wie v1s data-ignore-autosave) — „Speichern" schickt alles als
         EINEN v2-Batch (BZ konvertiert nach mg/dl) und geht zurück. ── -->
    <form name="form" id="vitalsForm" method="post" action="" onsubmit="return false">
        <div class="row">
            <div class="col position-relative">
                <div class="row my-3">
                    <div class="col edivi__vitalparam-box" data-before="SpO₂" data-after="%">
                        <input type="text" name="spo2" id="spo2" class="form-control edivi__vitalparam keypad-input"
                            placeholder="96" value="<?= $e($wert('spo2')) ?>" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                    </div>
                    <div class="col edivi__vitalparam-box" data-before="AF" data-after="/min">
                        <input type="text" name="atemfreq" id="atemfreq" class="form-control edivi__vitalparam keypad-input"
                            placeholder="16" value="<?= $e($wert('atemfreq')) ?>" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                    </div>
                    <div class="col edivi__vitalparam-box" data-before="etCO₂" data-after="mmHg">
                        <input type="text" name="etco2" id="etco2" class="form-control edivi__vitalparam keypad-input"
                            placeholder="35" value="<?= $e($wert('etco2')) ?>" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                    </div>
                </div>
                <div class="row my-3">
                    <div class="col edivi__vitalparam-box" data-before="HF" data-after="/min">
                        <input type="text" name="herzfreq" id="herzfreq" class="form-control edivi__vitalparam keypad-input"
                            placeholder="80" value="<?= $e($wert('herzfreq')) ?>" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                    </div>
                    <div class="col edivi__vitalparam-box" data-before="NIBP/RR" data-after="mmHg">
                        <!-- Rahmen/Hintergrund liegen auf der Box (divi.css:
                             NIBP/RR als EIN Feld), die Inputs sind randlos -->
                        <input type="text" name="rrsys" id="rrsys" class="form-control edivi__vitalparam-shared keypad-input"
                            placeholder="120" value="<?= $e($wert('rrsys')) ?>" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                        <div class="edivi_vitalparam-spacer">/</div>
                        <input type="text" name="rrdias" id="rrdias" class="form-control edivi__vitalparam-shared keypad-input"
                            placeholder="80" value="<?= $e($wert('rrdias')) ?>" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                    </div>
                </div>
                <div class="row my-3">
                    <div class="col edivi__vitalparam-box" data-before="BZ" data-after="<?= $e($bzUnit) ?>">
                        <input type="text" name="bz" id="bz" class="form-control edivi__vitalparam keypad-input"
                            placeholder="<?= $bzUnit === 'mmol/l' ? '5,0' : '90' ?>" value="<?= $e($bzDisplay) ?>"
                            data-autosave-ignore data-unit="<?= $e($bzUnit) ?>" <?= $istGesperrt ? 'disabled' : '' ?>>
                    </div>
                    <div class="col edivi__vitalparam-box" data-before="Temperatur" data-after="°C">
                        <input type="text" name="temp" id="temp" class="form-control edivi__vitalparam keypad-input"
                            placeholder="36,5" value="<?= $e($wert('temp')) ?>" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                    </div>
                </div>
                <div class="row edivi__vitalparam-mainbuttons">
                    <div class="col"><a href="<?= $e($sectionUrl) ?>">Abbrechen</a></div>
                    <div class="col" style="border-left:2px solid #191919;">
                        <button type="button" id="saveVitalsBtn" <?= $istGesperrt ? 'disabled' : '' ?>>Speichern</button>
                    </div>
                </div>
            </div>
            <div class="col-5">
                <div class="keypad-container">
                    <div class="keypad-grid">
                        <?php foreach (['7', '8', '9', '4', '5', '6', '1', '2', '3'] as $d): ?>
                            <button type="button" class="keypad-btn" data-keypad-digit="<?= $d ?>"><?= $d ?></button>
                        <?php endforeach; ?>
                        <button type="button" class="keypad-btn wide" data-keypad-digit="0">0</button>
                        <button type="button" class="keypad-btn special" data-keypad-digit=",">,</button>
                    </div>
                    <div class="function-grid">
                        <button type="button" class="keypad-btn danger" data-keypad-clear>Löschen</button>
                        <button type="button" class="keypad-btn danger" data-keypad-backspace><i class="fa-solid fa-delete-left"></i></button>
                        <button type="button" class="keypad-btn special" data-keypad-set="ng">nicht gemessen</button>
                        <button type="button" class="keypad-btn special" data-keypad-set="nm">nicht messbar</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

<?php else: ?>

    <!-- ── THEMA: v1-Unterseiten-Aufbau, Schritte client-seitig ── -->
    <?php $thema = $themen[$fokus]; ?>
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?= $themenSpalte($fokus) ?>

        <?php
        // Unterthemen-Spalte (Subnav + Quickfill) — nur wenn das Thema
        // mehrere Schritte oder eine Quickfill-Kachel hat (v1: ekg/psych
        // springen direkt in die Antwort-Spalten)
        $subnav = $thema['subnav'] ?? null;
        if ($subnav === null && count($thema['steps']) > 1) {
            $subnav = [];
            foreach ($thema['steps'] as $i => $step) {
                $subnav[] = ['label' => $step['label'], 'requires' => $step['requires'] ?? '', 'goto' => $i];
            }
        }
        ?>
        <?php if ($subnav !== null || isset($thema['quickfill'])): ?>
            <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
                <?php if (isset($thema['quickfill'])): ?>
                    <input type="checkbox" class="btn-check" id="<?= $e($thema['quickfill']['id']) ?>"
                        data-quickfill='<?= json_encode($thema['quickfill']['data']) ?>'
                        <?= $istGesperrt ? 'disabled' : '' ?> autocomplete="off">
                    <label for="<?= $e($thema['quickfill']['id']) ?>" class="edivi__unauffaellig"><?= $e($thema['quickfill']['label']) ?></label>
                <?php endif; ?>
                <?php foreach (($subnav ?? []) as $item): ?>
                    <a href="<?= $e($fokusUrl($fokus)) ?>&q=<?= $item['goto'] + 1 ?>"
                       data-wiz-goto="<?= $item['goto'] ?>"
                       <?= isset($item['covers']) ? 'data-wiz-covers="' . $e($item['covers']) . '"' : '' ?>
                       <?= $item['requires'] !== '' ? 'data-requires="' . $e($item['requires']) . '"' : '' ?>>
                        <span><?= $e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($thema['steps'] as $i => $step): ?>
            <div class="ev2-stepwrap<?= $i === $initialStep ? '' : ' is-hidden' ?>" data-wiz-step
                 data-wiz-fields="<?= $e($step['fields']) ?>">

                <?php if (!empty($step['nav3'])): ?>
                    <!-- Dritte Nav-Ebene wie v1 neurologie/3_1|3_2 -->
                    <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
                        <a href="<?= $e($fokusUrl('neurologie')) ?>&q=3" data-wiz-goto="2" data-requires="d_pupillenw_1,d_pupillenw_2">
                            <span>Pupillenweite</span>
                        </a>
                        <a href="<?= $e($fokusUrl('neurologie')) ?>&q=4" data-wiz-goto="3" data-requires="d_lichtreakt_1,d_lichtreakt_2">
                            <span>Lichtreaktion</span>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($step['html'] !== null): ?>
                    <?= $step['html']() ?>
                <?php elseif ($fokus === 'psychisch'): ?>
                    <?= $psychCol($psychCol1) ?>
                    <?= $psychCol($psychCol2) ?>
                <?php elseif ($fokus === 'erweitern' && $i === 0): ?>
                    <?php
                    // Körper-Silhouette der v1-Bodymap (erweitern/1.php), unverändert übernommen
                    $bodyPath = "M104.265,117.959c-0.304,3.58,2.126,22.529,3.38,29.959c0.597,3.52,2.234,9.255,1.645,12.3 c-0.841,4.244-1.084,9.736-0.621,12.934c0.292,1.942,1.211,10.899-0.104,14.175c-0.688,1.718-1.949,10.522-1.949,10.522 c-3.285,8.294-1.431,7.886-1.431,7.886c1.017,1.248,2.759,0.098,2.759,0.098c1.327,0.846,2.246-0.201,2.246-0.201 c1.139,0.943,2.467-0.116,2.467-0.116c1.431,0.743,2.758-0.627,2.758-0.627c0.822,0.414,1.023-0.109,1.023-0.109 c2.466-0.158-1.376-8.05-1.376-8.05c-0.92-7.088,0.913-11.033,0.913-11.033c6.004-17.805,6.309-22.53,3.909-29.24 c-0.676-1.937-0.847-2.704-0.536-3.545c0.719-1.941,0.195-9.748,1.072-12.848c1.692-5.979,3.361-21.142,4.231-28.217 c1.169-9.53-4.141-22.308-4.141-22.308c-1.163-5.2,0.542-23.727,0.542-23.727c2.381,3.705,2.29,10.245,2.29,10.245 c-0.378,6.859,5.541,17.342,5.541,17.342c2.844,4.332,3.921,8.442,3.921,8.747c0,1.248-0.273,4.269-0.273,4.269l0.109,2.631 c0.049,0.67,0.426,2.977,0.365,4.092c-0.444,6.862,0.646,5.571,0.646,5.571c0.92,0,1.931-5.522,1.931-5.522 c0,1.424-0.348,5.687,0.42,7.295c0.919,1.918,1.595-0.329,1.607-0.78c0.243-8.737,0.768-6.448,0.768-6.448 c0.511,7.088,1.139,8.689,2.265,8.135c0.853-0.407,0.073-8.506,0.073-8.506c1.461,4.811,2.569,5.577,2.569,5.577 c2.411,1.693,0.92-2.983,0.585-3.909c-1.784-4.92-1.839-6.625-1.839-6.625c2.229,4.421,3.909,4.257,3.909,4.257 c2.174-0.694-1.9-6.954-4.287-9.953c-1.218-1.528-2.789-3.574-3.245-4.789c-0.743-2.058-1.304-8.674-1.304-8.674 c-0.225-7.807-2.155-11.198-2.155-11.198c-3.3-5.282-3.921-15.135-3.921-15.135l-0.146-16.635 c-1.157-11.347-9.518-11.429-9.518-11.429c-8.451-1.258-9.627-3.988-9.627-3.988c-1.79-2.576-0.767-7.514-0.767-7.514 c1.485-1.208,2.058-4.415,2.058-4.415c2.466-1.891,2.345-4.658,1.206-4.628c-0.914,0.024-0.707-0.733-0.707-0.733 C115.068,0.636,104.01,0,104.01,0h-1.688c0,0-11.063,0.636-9.523,13.089c0,0,0.207,0.758-0.715,0.733 c-1.136-0.03-1.242,2.737,1.215,4.628c0,0,0.572,3.206,2.058,4.415c0,0,1.023,4.938-0.767,7.514c0,0-1.172,2.73-9.627,3.988 c0,0-8.375,0.082-9.514,11.429l-0.158,16.635c0,0-0.609,9.853-3.922,15.135c0,0-1.921,3.392-2.143,11.198 c0,0-0.563,6.616-1.303,8.674c-0.451,1.209-2.021,3.255-3.249,4.789c-2.408,2.993-6.455,9.24-4.29,9.953 c0,0,1.689,0.164,3.909-4.257c0,0-0.046,1.693-1.827,6.625c-0.35,0.914-1.839,5.59,0.573,3.909c0,0,1.117-0.767,2.569-5.577 c0,0-0.779,8.099,0.088,8.506c1.133,0.555,1.751-1.047,2.262-8.135c0,0,0.524-2.289,0.767,6.448 c0.012,0.451,0.673,2.698,1.596,0.78c0.779-1.608,0.429-5.864,0.429-7.295c0,0,0.999,5.522,1.933,5.522 c0,0,1.099,1.291,0.648-5.571c-0.073-1.121,0.32-3.422,0.369-4.092l0.106-2.631c0,0-0.274-3.014-0.274-4.269 c0-0.311,1.078-4.415,3.921-8.747c0,0,5.913-10.488,5.532-17.342c0,0-0.082-6.54,2.299-10.245c0,0,1.69,18.526,0.545,23.727 c0,0-5.319,12.778-4.146,22.308c0.864,7.094,2.53,22.237,4.226,28.217c0.886,3.094,0.362,10.899,1.072,12.848 c0.32,0.847,0.152,1.627-0.536,3.545c-2.387,6.71-2.083,11.436,3.921,29.24c0,0,1.848,3.945,0.914,11.033 c0,0-3.836,7.892-1.379,8.05c0,0,0.192,0.523,1.023,0.109c0,0,1.327,1.37,2.761,0.627c0,0,1.328,1.06,2.463,0.116 c0,0,0.91,1.047,2.237,0.201c0,0,1.742,1.175,2.777-0.098c0,0,1.839,0.408-1.435-7.886c0,0-1.254-8.793-1.945-10.522 c-1.318-3.275-0.387-12.251-0.106-14.175c0.453-3.216,0.21-8.695-0.618-12.934c-0.606-3.038,1.035-8.774,1.641-12.3 c1.245-7.423,3.685-26.373,3.38-29.959l1.008,0.354C103.809,118.312,104.265,117.959,104.265,117.959z";
                    ?>
                    <!-- Verletzungen: klickbare SVG-Körpergrafik (v1 erweitern/1.php,
                         SVG 1:1). Zonen-Klick öffnet das Detail-Panel darunter;
                         Rückansicht trägt nur die Wirbelsäulen-Zone. -->
                    <div class="col flex" style="padding: 0;">
                        <div class="bodymap-container">
                            <div class="bodymap-views">
                                <!-- Vorderansicht -->
                                <div>
                                    <div class="bodymap-view-label">Vorne</div>
                                    <svg class="bodymap-svg" viewBox="55 0 96 210" xmlns="http://www.w3.org/2000/svg">
                                        <defs>
                                            <clipPath id="body-clip-front">
                                                <path d="<?= $bodyPath ?>" />
                                            </clipPath>
                                        </defs>

                                        <g clip-path="url(#body-clip-front)">
                                            <!-- Kopf (inkl. Hals) -->
                                            <rect class="bodymap-zone" data-field="v_muster_k" data-label="Schädel-Hirn"
                                                x="0" y="0" width="206" height="28" />
                                            <!-- Thorax -->
                                            <rect class="bodymap-zone" data-field="v_muster_t" data-label="Thorax"
                                                x="83" y="28" width="40" height="34" />
                                            <!-- Abdomen -->
                                            <rect class="bodymap-zone" data-field="v_muster_a" data-label="Abdomen"
                                                x="83" y="62" width="40" height="28" />
                                            <!-- Beine -->
                                            <rect class="bodymap-zone" data-field="v_muster_bl" data-label="Untere Extremitäten"
                                                x="83" y="90" width="20" height="120" />
                                            <rect class="bodymap-zone" data-field="v_muster_bl" data-label="Untere Extremitäten"
                                                x="103" y="90" width="20" height="120" />
                                            <!-- Arme (nach den Beinen, damit die Hände Vorrang haben) -->
                                            <rect class="bodymap-zone" data-field="v_muster_al" data-label="Obere Extremitäten"
                                                x="0" y="28" width="83" height="182" />
                                            <rect class="bodymap-zone" data-field="v_muster_al" data-label="Obere Extremitäten"
                                                x="123" y="28" width="83" height="182" />
                                        </g>

                                        <path class="bodymap-outline" d="<?= $bodyPath ?>" />

                                        <g clip-path="url(#body-clip-front)" style="pointer-events: none;">
                                            <line class="bodymap-zone-divider" x1="0" y1="28" x2="206" y2="28" />
                                            <line class="bodymap-zone-divider" x1="83" y1="28" x2="83" y2="90" />
                                            <line class="bodymap-zone-divider" x1="123" y1="28" x2="123" y2="90" />
                                            <line class="bodymap-zone-divider" x1="83" y1="62" x2="123" y2="62" />
                                            <line class="bodymap-zone-divider" x1="83" y1="90" x2="123" y2="90" />
                                            <line class="bodymap-zone-divider" x1="103" y1="90" x2="103" y2="210" />
                                        </g>
                                    </svg>
                                </div>

                                <!-- Rückansicht -->
                                <div>
                                    <div class="bodymap-view-label">Hinten</div>
                                    <svg class="bodymap-svg-back" viewBox="55 0 96 210" xmlns="http://www.w3.org/2000/svg">
                                        <defs>
                                            <clipPath id="body-clip-back">
                                                <path d="<?= $bodyPath ?>" />
                                            </clipPath>
                                        </defs>

                                        <g clip-path="url(#body-clip-back)">
                                            <!-- Rücken als nicht klickbarer Hintergrund -->
                                            <rect style="fill: rgba(40,40,40,0.3); pointer-events: none;"
                                                x="0" y="0" width="206" height="210" />
                                            <!-- Wirbelsäule als schmaler Streifen -->
                                            <rect class="bodymap-zone" data-field="v_muster_w" data-label="Wirbelsäule"
                                                x="99" y="16" width="8" height="74" rx="2" />
                                        </g>

                                        <path class="bodymap-outline" d="<?= $bodyPath ?>" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Detail-Panel: erscheint nach Zonen-Klick -->
                            <div class="bodymap-panel">
                                <div class="bodymap-detail" id="bodymap-detail">
                                    <h6 id="bodymap-detail-title">Region</h6>

                                    <div class="bodymap-detail-label">Schweregrad</div>
                                    <div class="bodymap-detail-severity" id="bodymap-severity-btns">
                                        <button type="button" data-value="1">Keine</button>
                                        <button type="button" data-value="2">Leicht</button>
                                        <button type="button" data-value="3">Mittel</button>
                                        <button type="button" data-value="4">Schwer</button>
                                    </div>

                                    <div class="bodymap-detail-label" style="margin-top: 6px;">Wundart</div>
                                    <div class="bodymap-detail-woundtype" id="bodymap-woundtype-btns">
                                        <button type="button" data-value="1">Offen</button>
                                        <button type="button" data-value="2">Geschlossen</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($fokus === 'erweitern'): ?>
        <style>
            /* SVG-Bodymap (Styles aus v1 erweitern/1.php) */
            .bodymap-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                padding: 0;
                height: 100%;
            }

            .bodymap-views {
                display: flex;
                justify-content: center;
                align-items: flex-start;
                gap: 32px;
            }

            .bodymap-view-label {
                text-align: center;
                font-size: 0.7rem;
                color: #888;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 4px;
            }

            .bodymap-svg {
                height: calc(100vh - 200px);
                max-height: 560px;
                min-height: 180px;
                width: auto;
            }

            .bodymap-svg-back {
                height: calc(100vh - 200px);
                max-height: 560px;
                min-height: 180px;
                width: auto;
                opacity: 0.6;
            }

            /* Zonen: Grundfarbe grau, Färbung über data-severity */
            .bodymap-zone {
                fill: rgba(60, 60, 60, 0.4);
                stroke: none;
                cursor: pointer;
                transition: fill 0.25s ease;
            }

            .bodymap-zone:hover {
                filter: brightness(1.4);
            }

            .bodymap-zone.bodymap-zone-selected {
                stroke: #fff;
                stroke-width: 0.8;
                stroke-dasharray: 2 1;
            }

            .bodymap-zone[data-severity="1"] {
                fill: rgba(60, 60, 60, 0.4);
            }

            .bodymap-zone[data-severity="2"] {
                fill: rgba(46, 204, 113, 0.55);
            }

            .bodymap-zone[data-severity="3"] {
                fill: rgba(241, 196, 15, 0.55);
            }

            .bodymap-zone[data-severity="4"] {
                fill: rgba(231, 76, 60, 0.6);
            }

            /* Legacy-Wert 99 (nicht untersucht): grau schraffiert, nur lesend */
            .bodymap-zone[data-severity="99"] {
                fill: rgba(120, 120, 120, 0.45);
                stroke: #888;
                stroke-width: 0.3;
                stroke-dasharray: 1.5 1;
            }

            .bodymap-outline {
                fill: none;
                stroke: #999;
                stroke-width: 0.4;
                pointer-events: none;
            }

            .bodymap-zone-divider {
                stroke: #777;
                stroke-width: 0.3;
                stroke-dasharray: 1.5 1;
                pointer-events: none;
            }

            .bodymap-panel {
                width: 100%;
                max-width: 400px;
            }

            .bodymap-detail {
                padding: 10px;
                background: #252525;
                border: 1px solid #444;
                display: none;
            }

            .bodymap-detail.active {
                display: block;
            }

            .bodymap-detail h6 {
                font-size: 0.85rem;
                color: #fff;
                margin-bottom: 10px;
                padding-bottom: 6px;
                border-bottom: 1px solid #444;
            }

            .bodymap-detail-severity {
                display: flex;
                gap: 4px;
                margin-bottom: 8px;
            }

            .bodymap-detail-severity button {
                flex: 1;
                padding: 8px 4px;
                border: 1px solid #555;
                background: #333;
                color: #ccc;
                font-size: 0.75rem;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .bodymap-detail-severity button:hover { background: #444; }

            .bodymap-detail-severity button.active-keine {
                background: #444; border-color: #888; color: #fff;
            }
            .bodymap-detail-severity button.active-leicht {
                background: rgba(46, 204, 113, 0.35); border-color: #2ecc71; color: #2ecc71;
            }
            .bodymap-detail-severity button.active-mittel {
                background: rgba(241, 196, 15, 0.35); border-color: #f1c40f; color: #f1c40f;
            }
            .bodymap-detail-severity button.active-schwer {
                background: rgba(231, 76, 60, 0.35); border-color: #e74c3c; color: #e74c3c;
            }
            .bodymap-detail-severity button.active-nu {
                background: rgba(120, 120, 120, 0.35); border-color: #888; color: #aaa;
            }

            .bodymap-detail-woundtype {
                display: none;
                gap: 4px;
            }

            .bodymap-detail-woundtype.visible {
                display: flex;
            }

            .bodymap-detail-woundtype button {
                flex: 1;
                padding: 8px 4px;
                border: 1px solid #555;
                background: #333;
                color: #ccc;
                font-size: 0.75rem;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .bodymap-detail-woundtype button:hover { background: #444; }

            .bodymap-detail-woundtype button.active {
                background: rgba(52, 152, 219, 0.35); border-color: #3498db; color: #3498db;
            }

            .bodymap-detail-label {
                font-size: 0.7rem;
                color: #888;
                margin-bottom: 4px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            @media (max-width: 500px) {
                .bodymap-views {
                    gap: 8px;
                }
            }
        </style>
        <script>
            // SVG-Bodymap: Vanilla-Port der v1-Logik (erweitern/1.php).
            // Zonen-Klick → Detail-Panel, Schweregrad färbt die Zone,
            // Wundart nur bei verletzten Regionen. Gespeichert wird über
            // den v2-Batch-Autosave; Formate wie v1 (X = Schweregrad-Code,
            // X1 = Wundart, bei Schweregrad 1/99 wird X1 geleert).
            (function () {
                'use strict';

                var locked = <?= $istGesperrt ? 'true' : 'false' ?>;

                var woundTypeMap = {
                    'v_muster_k': 'v_muster_k1', 'v_muster_w': 'v_muster_w1',
                    'v_muster_t': 'v_muster_t1', 'v_muster_a': 'v_muster_a1',
                    'v_muster_al': 'v_muster_al1', 'v_muster_bl': 'v_muster_bl1'
                };

                var fieldLabels = {
                    'v_muster_k': 'Schädel-Hirn', 'v_muster_w': 'Wirbelsäule',
                    'v_muster_t': 'Thorax', 'v_muster_a': 'Abdomen',
                    'v_muster_al': 'Obere Extremitäten', 'v_muster_bl': 'Untere Extremitäten'
                };

                var severityFields = ['v_muster_k', 'v_muster_w', 'v_muster_t', 'v_muster_a', 'v_muster_al', 'v_muster_bl'];

                var severityClasses = { '1': 'active-keine', '2': 'active-leicht', '3': 'active-mittel', '4': 'active-schwer', '99': 'active-nu' };

                var selectedField = null;
                var keineInitDone = false;

                function daten() {
                    if (!window.__dynamicDaten) window.__dynamicDaten = {};
                    return window.__dynamicDaten;
                }

                // Effektiver Schweregrad: null/leer/0 zählt als "1" (keine),
                // Legacy 99 bleibt lesbar
                function getSeverity(field) {
                    var val = String(daten()[field] || '');
                    return (val === '2' || val === '3' || val === '4' || val === '99') ? val : '1';
                }

                function initZones() {
                    document.querySelectorAll('.bodymap-zone').forEach(function (zone) {
                        zone.setAttribute('data-severity', getSeverity(zone.getAttribute('data-field')));
                    });
                }

                function updateZoneColors(field, severity) {
                    document.querySelectorAll('.bodymap-zone[data-field="' + field + '"]').forEach(function (zone) {
                        zone.setAttribute('data-severity', String(severity));
                    });
                }

                function highlightSelected(field) {
                    document.querySelectorAll('.bodymap-zone').forEach(function (zone) {
                        zone.classList.toggle('bodymap-zone-selected', zone.getAttribute('data-field') === field);
                    });
                }

                // Wert lokal nachziehen und über den Batch-Autosave queuen
                // (null leert das Feld — wie v1s clearNull-Request)
                function saveField(field, value) {
                    if (locked) return;
                    daten()[field] = value;
                    if (window.EnotfV2Autosave) window.EnotfV2Autosave.queue(field, value);
                }

                // Leere Regionen beim ersten Anzeigen auf "keine" (1) setzen
                // (v1-Verhalten beim Öffnen der Verletzungen-Seite)
                function autoInitKeineFields() {
                    if (locked) return;
                    severityFields.forEach(function (field) {
                        var val = daten()[field];
                        if (val === null || val === '' || val === undefined || val === 0 || val === '0') {
                            saveField(field, '1');
                        }
                    });
                }

                function showDetail(field) {
                    selectedField = field;
                    highlightSelected(field);

                    var panel = document.getElementById('bodymap-detail');
                    panel.classList.add('active');
                    document.getElementById('bodymap-detail-title').textContent = fieldLabels[field] || field;

                    var currentSeverity = getSeverity(field);
                    document.getElementById('bodymap-severity-btns').querySelectorAll('button').forEach(function (btn) {
                        btn.className = '';
                        if (btn.getAttribute('data-value') === currentSeverity) {
                            btn.classList.add(severityClasses[currentSeverity] || '');
                        }
                    });

                    var isInjured = (currentSeverity === '2' || currentSeverity === '3' || currentSeverity === '4');
                    var woundBtns = document.getElementById('bodymap-woundtype-btns');
                    woundBtns.classList.toggle('visible', isInjured);

                    if (isInjured) {
                        var woundField = woundTypeMap[field];
                        var currentWound = String(daten()[woundField] || '');
                        woundBtns.querySelectorAll('button').forEach(function (btn) {
                            btn.classList.toggle('active', btn.getAttribute('data-value') === currentWound);
                        });
                    }
                }

                function bindHandlers() {
                    // Zonen-Klicks
                    document.querySelectorAll('.bodymap-zone').forEach(function (zone) {
                        zone.addEventListener('click', function (e) {
                            e.preventDefault();
                            showDetail(this.getAttribute('data-field'));
                        });
                    });

                    // Schweregrad-Buttons
                    document.getElementById('bodymap-severity-btns').addEventListener('click', function (e) {
                        var btn = e.target.closest('button');
                        if (!btn || !selectedField) return;

                        var value = btn.getAttribute('data-value');
                        updateZoneColors(selectedField, value);
                        saveField(selectedField, value);

                        // "Keine"/99 haben keine Wundart — Gegenfeld leeren
                        if (value === '1' || value === '99') {
                            saveField(woundTypeMap[selectedField], null);
                        }

                        showDetail(selectedField);
                    });

                    // Wundart-Buttons
                    document.getElementById('bodymap-woundtype-btns').addEventListener('click', function (e) {
                        var btn = e.target.closest('button');
                        if (!btn || !selectedField) return;

                        saveField(woundTypeMap[selectedField], btn.getAttribute('data-value'));

                        document.getElementById('bodymap-woundtype-btns').querySelectorAll('button').forEach(function (b) {
                            b.classList.toggle('active', b === btn);
                        });
                    });

                    // Quickfill "keine": edivi-bridge.js setzt die Felder im
                    // Document-Change-Handler — danach Zonen neu färben und
                    // verwaiste Wundarten leeren (setTimeout: Bridge-Handler
                    // läuft nach diesem Listener)
                    document.addEventListener('change', function (e) {
                        if (!(e.target instanceof HTMLElement) || e.target.id !== 'erweitern-ohne-path') return;
                        setTimeout(function () {
                            initZones();
                            severityFields.forEach(function (field) {
                                if (getSeverity(field) === '1') {
                                    var woundField = woundTypeMap[field];
                                    var currentWound = daten()[woundField];
                                    if (currentWound !== null && currentWound !== '' && currentWound !== undefined) {
                                        saveField(woundField, null);
                                    }
                                }
                            });
                            if (selectedField) showDetail(selectedField);
                        }, 0);
                    });
                }

                function lockBodymap() {
                    document.querySelectorAll('.bodymap-zone').forEach(function (z) {
                        z.style.pointerEvents = 'none';
                        z.style.cursor = 'default';
                    });
                    var detailPanel = document.getElementById('bodymap-detail');
                    if (detailPanel) detailPanel.style.display = 'none';
                }

                // Auto-Init erst, wenn der Verletzungen-Schritt sichtbar wird
                // (v1: erst beim Öffnen der Seite; hier stehen beide Schritte
                // im DOM und wizard.js blendet um)
                function maybeAutoInit(stepWrap) {
                    if (keineInitDone || stepWrap.classList.contains('is-hidden')) return;
                    keineInitDone = true;
                    autoInitKeineFields();
                    initZones();
                }

                function init() {
                    initZones();
                    if (locked) {
                        lockBodymap();
                        return;
                    }
                    bindHandlers();

                    var container = document.querySelector('.bodymap-container');
                    var stepWrap = container ? container.closest('[data-wiz-step]') : null;
                    if (!stepWrap) return;
                    maybeAutoInit(stepWrap);
                    new MutationObserver(function () { maybeAutoInit(stepWrap); })
                        .observe(stepWrap, { attributes: true, attributeFilter: ['class'] });
                }

                // Module (autosave/wizard/bridge) laufen vor DOMContentLoaded —
                // ab hier sind __dynamicDaten und die Schritt-Sichtbarkeit gesetzt
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();

            // clip-path url(#id) auf absolute URL umschreiben — nötig im
            // FiveM-CEF/iframe-Kontext (wie v1 erweitern/1.php)
            (function () {
                var baseUrl = window.location.href.split('#')[0];
                document.querySelectorAll('[clip-path]').forEach(function (el) {
                    var val = el.getAttribute('clip-path');
                    if (val && val.indexOf('url(#') === 0) {
                        el.setAttribute('clip-path', val.replace('url(#', 'url(' + baseUrl + '#'));
                    }
                });
            })();
        </script>
    <?php endif; ?>

<?php endif; ?>

<?php if ($fokus === 'messwerte'): ?>
<script>
    // Messwerte: Keypad + Batch-Save (v1-Bedienung, v2-Technik)
    (function () {
        'use strict';
        var locked = <?= $istGesperrt ? 'true' : 'false' ?>;
        var FIELDS = ['spo2', 'atemfreq', 'etco2', 'herzfreq', 'rrsys', 'rrdias', 'bz', 'temp'];
        var current = null;

        document.querySelectorAll('.keypad-input').forEach(function (input) {
            input.addEventListener('focus', function () { current = input; });
        });

        function ziel() {
            if (current) return current;
            current = document.getElementById('spo2');
            return current;
        }

        document.querySelectorAll('[data-keypad-digit]').forEach(function (btn) {
            btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            btn.addEventListener('click', function () {
                if (locked) return;
                var t = ziel();
                if (['ng', 'nm'].indexOf(t.value) !== -1) t.value = '';
                t.value += btn.dataset.keypadDigit;
                t.focus();
            });
        });
        var clearBtn = document.querySelector('[data-keypad-clear]');
        if (clearBtn) clearBtn.addEventListener('click', function () { if (!locked) { var t = ziel(); t.value = ''; t.focus(); } });
        var backBtn = document.querySelector('[data-keypad-backspace]');
        if (backBtn) backBtn.addEventListener('click', function () { if (!locked) { var t = ziel(); t.value = t.value.slice(0, -1); t.focus(); } });
        document.querySelectorAll('[data-keypad-set]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (locked) return;
                var t = ziel();
                t.value = btn.dataset.keypadSet;
                t.focus();
            });
        });

        // Speichern: EIN Batch über den v2-Autosave (BZ → mg/dl)
        var saveBtn = document.getElementById('saveVitalsBtn');
        if (saveBtn) saveBtn.addEventListener('click', function () {
            if (locked || !window.EnotfV2Autosave) return;
            FIELDS.forEach(function (name) {
                var input = document.getElementsByName(name)[0];
                if (!input) return;
                var raw = input.value.trim();
                var value = raw;
                if (name === 'bz' && raw !== '' && ['ng', 'nm'].indexOf(raw.toLowerCase()) === -1) {
                    var num = parseFloat(raw.replace(',', '.'));
                    if (isNaN(num)) return;
                    value = input.dataset.unit === 'mmol/l'
                        ? String(Math.round(num * 18.0180)) // BloodSugarHelper::MMOL_TO_MG_FACTOR
                        : String(Math.round(num));
                }
                window.EnotfV2Autosave.queue(name, value);
                if (window.__dynamicDaten) window.__dynamicDaten[name] = value;
            });
            window.EnotfV2Autosave.flushNow();
            saveBtn.disabled = true;
            var waited = 0;
            var poll = setInterval(function () {
                waited += 100;
                if (!window.EnotfV2Autosave.hasPending() || waited >= 4000) {
                    clearInterval(poll);
                    window.location.href = <?= json_encode($sectionUrl) ?>;
                }
            }, 100);
        });
    })();
</script>
<?php endif; ?>
