<?php

/**
 * Section: Maßnahmen — eNOTF v2 im v1-Look.
 *
 * Getreuer Nachbau der v1-Seiten unter
 * plugins/enotf/templates/enotf/protokoll/massnahmen/:
 *
 *   ÜBERSICHT (ohne ?t): Kachel-Seite wie v1 index.php — Themen-Spalte
 *     links und edivi__box-Kacheln mit den readonly-Anzeigefeldern
 *     atemwegssicherung/beatmung/o2gabe, zugang_display (hidden) + pvk/io-
 *     Textareas, medikamente-Textarea, lagerung/rettungstechnik.
 *
 *   THEMA (?t=atemwege|atmung|zugang|medikamente|weitere): Aufbau der
 *     jeweiligen v1-Unterseite. Mehrschrittige Themen (atmung, weitere,
 *     zugang) stehen komplett im DOM, wizard.js schaltet ohne Full-Reload
 *     um (?q=N). Bootstrap col-N statt v1s w-N/12 (Tailwind-4-Layering).
 *
 *   ?t=medimaske: die Medikamenten-Eingabemaske (v1 medikamente/1.php) —
 *     Liste links, Formular rechts, zurück/Löschen/Speichern unten.
 *     Läuft komplett gegen die v2-Medis-API (/api/enotf-v2/medis:
 *     GET Liste, POST add, POST delete — Speicherformat exakt v1).
 *
 * Zugang: läuft über den v1-Flow (PVK/intraossär → Lokation → Größe je
 * Seite als btn-check-Spalten), ohne eigenen Dialog. Die c_zugang-JSON-
 * Logik entspricht v1s enotf-zugang.js: Speicherformat {art, groesse,
 * ort, seite}, '0' = „Kein Zugang", null = nicht gesetzt; pro Lokation nur eine
 * Größe; Merge erhält fremde Einträge (z. B. zvk aus Altdaten). Gespeichert
 * wird über EnotfV2Autosave.queue('c_zugang', …) — der Server validiert
 * erneut (ProtokollService::validateCZugang).
 *
 * rettungstechnik: Mehrfachauswahl (JSON-Array von int) als btn-check-
 * Checkboxen name="rettungstechnik[]" mit data-ev2-multijson — Speicherung
 * über den generischen Multi-JSON-Handler in edivi-bridge.js (Muster wie
 * psych). Bewusst OHNE Exklusiv-Codes: Code 1 ist im v1-Formular
 * „Spineboard", einen „keine"-Wert gibt es dort nicht.
 *
 * @var array<string,mixed> $protokoll
 * @var string $enr
 * @var bool $istGesperrt
 * @var string|null $fokusThema   ?t-Param aus ProtokollController::show
 */

use Plugin\EnotfV2\Catalogs\BefundCatalog;
use Plugin\EnotfV2\Catalogs\MedikationCatalog;
use Plugin\EnotfV2\Catalogs\ZugangCatalog;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Models\Medikament;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$wert = static fn (string $feld): string => (string) ($protokoll[$feld] ?? '');

$sectionUrl = EnotfV2Url::protokoll($enr, 'massnahmen');
$fokusUrl   = static fn (string $thema): string => $sectionUrl . '?t=' . rawurlencode($thema);

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS;

// ── c_zugang dekodieren (Format wie v1/zugang_helpers.php) ───────────
$zugangRaw  = $protokoll['c_zugang'] ?? null;
$keinZugang = $zugangRaw === ZugangCatalog::KEIN_ZUGANG;
$zugaenge   = [];
if (is_string($zugangRaw) && $zugangRaw !== '' && !$keinZugang) {
    $decoded = json_decode($zugangRaw, true);
    if (is_array($decoded)) {
        $zugaenge = isset($decoded['art']) ? [$decoded] : array_values(array_filter($decoded, 'is_array'));
    }
}

// ── medis dekodieren (v1-Format, '0'/'1' = keine) ────────────────────
$medisRaw       = $protokoll['medis'] ?? null;
$keineMedis     = $medisRaw === MedikationCatalog::KEINE_MEDIKAMENTE || $medisRaw === '1';
$medisEintraege = [];
if (is_string($medisRaw) && $medisRaw !== '' && !$keineMedis) {
    $decoded = json_decode($medisRaw, true);
    if (is_array($decoded)) {
        $medisEintraege = array_values(array_filter($decoded, 'is_array'));
    }
}

// ── rettungstechnik dekodieren (JSON-Array von int) ──────────────────
$rettungstechnikAktiv = [];
$rtRaw = $protokoll['rettungstechnik'] ?? null;
if (is_string($rtRaw) && $rtRaw !== '' && $rtRaw !== '0') {
    $decoded = json_decode($rtRaw, true);
    if (is_array($decoded)) {
        $rettungstechnikAktiv = array_map('intval', $decoded);
    }
}

// ── Anzeige-Helfer für die Übersichts-Kacheln (v1 index.php) ─────────
$lbl = static function (string $feld, array $katalog) use ($wert): string {
    $code = $wert($feld);
    if ($code === '') {
        return '';
    }
    return $katalog[(int) $code] ?? '';
};

// Zugänge einer Art als Text ("PVK 18 G Handrücken links" je Zeile, v1:
// displayZugaengeByArtText — Sonderwert '0' → "Kein Zugang")
$zugaengeText = static function (string $filterArt) use ($zugangRaw, $keinZugang, $zugaenge): string {
    if ($zugangRaw === null || $zugangRaw === '') {
        return '';
    }
    if ($keinZugang) {
        return 'Kein Zugang';
    }
    if (empty($zugaenge)) {
        return '';
    }
    $gefiltert = array_values(array_filter($zugaenge, static fn (array $z): bool => ($z['art'] ?? '') === $filterArt));
    if (empty($gefiltert)) {
        return 'Keine Zugänge dieser Art';
    }
    usort($gefiltert, static fn (array $a, array $b): int =>
        [$a['art'] ?? '', $a['ort'] ?? '', $a['seite'] ?? ''] <=> [$b['art'] ?? '', $b['ort'] ?? '', $b['seite'] ?? '']);
    $zeilen = [];
    foreach ($gefiltert as $z) {
        // Größe wie v1s normalize_groesse_pretty: '45mm' → '45 mm', G-Größen aus dem Katalog
        $groesse = ZugangCatalog::GROESSEN[$z['groesse'] ?? ''] ?? ($z['groesse'] ?? '');
        $groesse = (string) preg_replace('/^(15|25|45)mm$/i', '$1 mm', $groesse);
        $zeilen[] = trim(sprintf(
            '%s %s %s %s',
            ZugangCatalog::ARTEN[$z['art'] ?? ''] ?? ($z['art'] ?? ''),
            $groesse,
            $z['ort'] ?? '',
            $z['seite'] ?? ''
        ));
    }
    return implode("\n", $zeilen);
};

// Medikamente als Text (v1: displayAllMedikamente — '0'/'1' → "Keine Medikamente")
$medisText = static function () use ($medisRaw, $keineMedis, $medisEintraege): string {
    if ($medisRaw === null || $medisRaw === '') {
        return '';
    }
    if ($keineMedis) {
        return 'Keine Medikamente';
    }
    if (empty($medisEintraege)) {
        return '';
    }
    $sortiert = $medisEintraege;
    usort($sortiert, static fn (array $a, array $b): int => strcmp((string) ($a['zeit'] ?? ''), (string) ($b['zeit'] ?? '')));
    $zeilen = [];
    foreach ($sortiert as $med) {
        $einheit = (string) ($med['einheit'] ?? '');
        if ($einheit === 'mcg') {
            $einheit = 'µg';
        } elseif ($einheit === 'IE') {
            $einheit = 'I.E.';
        }
        $zeilen[] = sprintf(
            '%s: %s %s %s %s',
            $med['zeit'] ?? '',
            $med['wirkstoff'] ?? '',
            $med['dosierung'] ?? '',
            $einheit,
            $med['applikation'] ?? ''
        );
    }
    return implode("\n", $zeilen);
};

$rettungstechnikTexte = [];
foreach ($rettungstechnikAktiv as $code) {
    if (isset(BefundCatalog::RETTUNGSTECHNIK[$code])) {
        $rettungstechnikTexte[] = BefundCatalog::RETTUNGSTECHNIK[$code];
    }
}
$rettungstechnikDisplay = implode(', ', $rettungstechnikTexte);

// ── Themen (v1-Reihenfolge/-Beschriftung, requires wie v1) ───────────
$themen = [
    'atemwege'    => ['label' => 'Atemwege',    'requires' => 'awsicherung_neu'],
    'atmung'      => ['label' => 'Atmung',      'requires' => 'b_beatmung'],
    'zugang'      => ['label' => 'Zugang',      'requires' => 'c_zugang'],
    'medikamente' => ['label' => 'Medikamente', 'requires' => 'medis'],
    'weitere'     => ['label' => 'Weitere',     'requires' => ''],
];

// Fokus validieren (medimaske = Eingabemaske, nicht in der Themen-Spalte)
$fokus = $fokusThema ?? null;
if ($fokus !== null && !isset($themen[$fokus]) && $fokus !== 'medimaske') {
    $fokus = null;
}

// Medikamenten-Maske ist wie v1 (massnahmen/medikamente/1.php) chromelos:
// keine Topbar/Section-Nav, Rückweg über zurück/Speichern
if ($fokus === 'medimaske') {
    $sectionChromeless = true;
}

// Schrittanzahl der Wizard-Themen (?q-Klemmung). Ohne explizites ?q ist
// KEIN Schritt offen — nur die Subnav-Spalten (v1: Themen-Index zeigt die
// Navigation, erst der Klick öffnet die Frage; atemwege hat einen
// einzelnen verlinkten Schritt und folgt derselben Regel)
$schrittanzahl = ['atemwege' => 1, 'atmung' => 2, 'weitere' => 3, 'zugang' => 14];
$initialStep = -1;
if ($fokus !== null && isset($schrittanzahl[$fokus])) {
    $qRaw = $_GET['q'] ?? null;
    if (is_string($qRaw) && preg_match('/^\d+$/', $qRaw)) {
        $initialStep = max(0, min($schrittanzahl[$fokus] - 1, (int) $qRaw - 1));
    }
}

// Themen-Spalte (v1: erste edivi__interactbutton-more-Spalte jeder Seite)
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

// Radio-Spalte (btn-check) — v1 nutzt in den Maßnahmen-Unterseiten
// edivi__interactbutton-more auch für die Antwort-Spalten
$radioCol = static function (string $name, array $options, array $opts = []) use ($protokoll, $e, $istGesperrt): string {
    $current = (string) ($protokoll[$name] ?? '');
    $green   = $opts['green'] ?? null;
    $width   = $opts['width'] ?? 'col-2';

    $html = '<div class="' . $e($width) . ' d-flex flex-column edivi__interactbutton-more px-3">';
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

// Boolesche Flag-Checkbox (Spalte tinyint 1/0, Autosave nativ)
$flagBox = static function (string $name, string $label) use ($wert, $e, $istGesperrt): string {
    return '<input type="checkbox" class="btn-check" id="' . $e($name) . '-1" name="' . $e($name) . '" value="1"'
        . ($wert($name) === '1' ? ' checked' : '') . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
        . '<label for="' . $e($name) . '-1">' . $e($label) . '</label>';
};

// Übersichts-Kachel-Input (readonly; edivi__input-check für die Färbung)
$checkInput = static function (string $label, string $value, string $id, bool $optional = false) use ($e): string {
    return '<div class="row my-2"><div class="col">'
        . '<label for="' . $e($id) . '" class="edivi__description">' . $e($label) . '</label>'
        . '<input type="text" id="' . $e($id) . '" name="' . $e($id) . '" class="w-100 ignis-input'
        . ($optional ? '' : ' edivi__input-check') . '" value="' . $e($value) . '" readonly>'
        . '</div></div>';
};

// ── Zugang: Blatt-Navigation und -Reihenfolge wie v1 (zugang/1_1, 1_2;
//    „Fuß" wird gespeichert, das Blatt heißt in der Nav „Fuss") ────────
$zugangNav = [
    'pvk' => [
        'label'    => 'PVK',
        'groessen' => ZugangCatalog::GROESSEN_PVK,
        'orte'     => [
            // [gespeicherter ort-Wert, Nav-Label]
            ['Handrücken', 'Handrücken'],
            ['Unterarm', 'Unterarm'],
            ['Ellbeuge', 'Ellbeuge'],
            ['Oberarm', 'Oberarm'],
            ['Hals', 'Hals'],
            ['Kopf', 'Kopf'],
            ['Bein', 'Bein'],
            ['Fuß', 'Fuss'],
            ['Sonstige', 'Sonstige'],
        ],
    ],
    'io' => [
        'label'    => 'intraossär',
        'groessen' => ZugangCatalog::GROESSEN_IO,
        'orte'     => [
            ['Tibia proximal', 'Tibia proximal'],
            ['Tibia distal', 'Tibia distal'],
            ['Humerus proximal', 'Humerus proximal'],
            ['Sternum', 'Sternum'],
            ['anderer Ort', 'anderer Ort'],
        ],
    ],
];

// Größen-Spalte eines Zugang-Blatts (v1 1_1_x/1_2_x: eine Spalte je Seite)
$zugangSpalte = static function (string $art, string $ort, string $seite, array $groessen) use ($zugaenge, $e, $istGesperrt): string {
    $aktuell = null;
    foreach ($zugaenge as $z) {
        if (($z['art'] ?? '') === $art && ($z['ort'] ?? '') === $ort && ($z['seite'] ?? '') === $seite) {
            $aktuell = $z;
            break;
        }
    }

    $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/u', '', $art . $ort . ($seite === '' ? 'x' : $seite)));
    $html = '<div class="col-1 d-flex flex-column edivi__interactbutton px-3">';
    if ($seite !== '') {
        $html .= '<label class="edivi__interactbutton-text">' . $e($seite) . '</label>';
    }
    foreach ($groessen as $i => $groesse) {
        $inputId = 'c_zugang-' . $slug . '-' . ($i + 1);
        $daten   = ['art' => $art, 'groesse' => $groesse, 'ort' => $ort, 'seite' => $seite];
        $checked = $aktuell !== null && (string) ($aktuell['groesse'] ?? '') === $groesse;
        // ohne name-Attribut: Autosave/Bridge fassen die Boxen nicht an,
        // gespeichert wird programmatisch (Skript unten, v1: enotf-zugang.js)
        $html .= '<input type="checkbox" class="btn-check zugang-checkbox" id="' . $e($inputId) . '"'
            . ' data-zugang=\'' . htmlspecialchars((string) json_encode($daten, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '\''
            . ' data-location="' . $e($art . '|' . $ort . '|' . $seite) . '"'
            . ($checked ? ' checked' : '') . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
            . '<label for="' . $e($inputId) . '" class="edivi__zugang-' . $e($groesse) . '">'
            . $e(ZugangCatalog::GROESSEN[$groesse] ?? $groesse) . '</label>';
    }
    return $html . '</div>';
};
?>

<?php if ($fokus === null): ?>

    <!-- ── ÜBERSICHT: Kachel-Seite (v1 massnahmen/index.php) ── -->
    <div class="row" style="margin-left: 0">
        <?php if (!$istGesperrt): ?>
            <?= $themenSpalte(null) ?>
        <?php endif; ?>
        <div class="col edivi__overview-container">
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('atemwege')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Atemwege</h5>
                        <div class="col">
                            <?= $checkInput('Atemwegssicherung', $lbl('awsicherung_neu', BefundCatalog::AWSICHERUNG_NEU), 'atemwegssicherung') ?>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('atmung')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Atmung</h5>
                        <div class="col">
                            <div class="row">
                                <div class="col"><?= $checkInput('Beatmung', $lbl('b_beatmung', BefundCatalog::B_BEATMUNG), 'beatmung') ?></div>
                                <div class="col"><?= $checkInput('O2-Gabe', $lbl('o2gabe', BefundCatalog::O2GABE), 'o2gabe', true) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('zugang')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Zugänge <i id="icon-zugang_display" class="fa-solid fa-circle-exclamation" style="color:#d91425; margin-left:4px; display:none;"></i></h5>
                        <input type="hidden" name="zugang_display" class="edivi__input-check" value="<?= $e($zugangRaw ?? '') ?>">
                        <div class="col">
                            <div class="row">
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="pvk" class="edivi__description">PVK</label>
                                            <textarea name="pvk" id="pvk" class="w-100 ignis-input" style="height: 200px; overflow-y: auto; resize: vertical;" readonly><?= $e($zugaengeText('pvk')) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="io" class="edivi__description">intraossär</label>
                                            <textarea name="io" id="io" class="w-100 ignis-input" style="height: 200px; overflow-y: auto; resize: vertical;" readonly><?= $e($zugaengeText('io')) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('medikamente')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Medikamente <i id="icon-medikamente" class="fa-solid fa-circle-exclamation" style="color:#d91425; margin-left:4px; display:none;"></i></h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label for="medikamente" class="edivi__description" style="display: none;">Medikamente</label>
                                    <textarea name="medikamente" id="medikamente" class="w-100 ignis-input edivi__input-check" style="height: 36vh; overflow-y: auto; resize: vertical;" readonly><?= $e($medisText()) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('weitere')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Weitere</h5>
                        <div class="col">
                            <div class="row">
                                <div class="col"><?= $checkInput('Lagerung', $lbl('lagerung', BefundCatalog::LAGERUNG), 'lagerung', true) ?></div>
                                <div class="col"><?= $checkInput('Rettungstechnik', $rettungstechnikDisplay, 'rettungstechnik', true) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($fokus === 'atemwege'): ?>

    <!-- ── ATEMWEGE: v1 atemwege/index.php (Subnav-Checkboxen) + 1.php
         (Radio-Spalte als Schritt — öffnet erst der Klick auf
         „Atemwegssicherung", wie v1s Unterseiten-Link) ── -->
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?= $themenSpalte('atemwege') ?>
        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <a href="<?= $e($fokusUrl('atemwege')) ?>&q=1" data-wiz-goto="0" data-requires="awsicherung_neu">
                <span>Atemwegssicherung</span>
            </a>
            <?= $flagBox('awsicherung_1', 'Atemwege freim.') ?>
            <?= $flagBox('awsicherung_2', 'Absaugen') ?>
            <?= $flagBox('entlastungspunktion', 'Entlastungspunktion') ?>
            <?= $flagBox('hws_immo', 'HWS-Immobilisation') ?>
        </div>
        <div class="ev2-stepwrap<?= $initialStep === 0 ? '' : ' is-hidden' ?>" data-wiz-step data-wiz-fields="awsicherung_neu">
            <?= $radioCol('awsicherung_neu', BefundCatalog::AWSICHERUNG_NEU, ['green' => 1]) ?>
        </div>
    </div>

<?php elseif ($fokus === 'atmung'): ?>

    <!-- ── ATMUNG: v1 atmung/1.php + 2.php als Wizard-Schritte ── -->
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?= $themenSpalte('atmung') ?>
        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <a href="<?= $e($fokusUrl('atmung')) ?>&q=1" data-wiz-goto="0" data-requires="b_beatmung">
                <span>Beatmung</span>
            </a>
            <a href="<?= $e($fokusUrl('atmung')) ?>&q=2" data-wiz-goto="1">
                <span>O2-Gabe</span>
            </a>
        </div>

        <div class="ev2-stepwrap<?= $initialStep === 0 ? '' : ' is-hidden' ?>" data-wiz-step data-wiz-fields="b_beatmung">
            <?= $radioCol('b_beatmung', BefundCatalog::B_BEATMUNG) ?>
        </div>
        <div class="ev2-stepwrap<?= $initialStep === 1 ? '' : ' is-hidden' ?>" data-wiz-step data-wiz-fields="o2gabe">
            <?php
            $o2 = BefundCatalog::O2GABE;
            $o2Col1 = array_intersect_key($o2, array_flip(range(0, 8)));
            $o2Col2 = array_intersect_key($o2, array_flip(range(9, 15)));
            ?>
            <?= $radioCol('o2gabe', $o2Col1, ['width' => 'col-1']) ?>
            <?= $radioCol('o2gabe', $o2Col2, ['width' => 'col-1']) ?>
        </div>
    </div>

<?php elseif ($fokus === 'zugang'): ?>

    <!-- ── ZUGANG: v1-Flow zugang/1 → 1_1/1_2 → Lokations-Blätter als
         Wizard-Schritte (alle 14 Blätter im DOM, wizard.js schaltet um).
         Speicherformat identisch zu v1. ── -->
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?= $themenSpalte('zugang') ?>
        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <a href="<?= $e($fokusUrl('zugang')) ?>" class="active">
                <span>Zugang</span>
            </a>
            <input type="checkbox" class="btn-check" id="c_zugang-0" value="0"
                <?= $keinZugang ? 'checked' : '' ?> <?= $istGesperrt ? 'disabled' : '' ?> autocomplete="off">
            <label for="c_zugang-0">Kein Zugang</label>
        </div>
        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <?php
            $stepOffset = 0;
            foreach ($zugangNav as $artKey => $artInfo):
                $covers = implode(',', range($stepOffset, $stepOffset + count($artInfo['orte']) - 1));
            ?>
                <a href="<?= $e($fokusUrl('zugang')) ?>&q=<?= $stepOffset + 1 ?>"
                   data-wiz-goto="<?= $stepOffset ?>" data-wiz-covers="<?= $e($covers) ?>">
                    <span><?= $e($artInfo['label']) ?></span>
                </a>
            <?php
                $stepOffset += count($artInfo['orte']);
            endforeach;
            ?>
        </div>

        <?php
        $stepIndex = 0;
        foreach ($zugangNav as $artKey => $artInfo):
            $artStart = $stepIndex;
            $orte     = $artInfo['orte'];
            $orteInfo = $artKey === 'pvk' ? ZugangCatalog::ORTE_PVK : ZugangCatalog::ORTE_IO;
            foreach ($orte as $ortIdx => [$ortWert, $ortLabel]):
        ?>
            <div class="ev2-stepwrap<?= $stepIndex === $initialStep ? '' : ' is-hidden' ?>" data-wiz-step data-wiz-fields="c_zugang">
                <!-- Lokations-Nav des Blatts (v1: vierte Spalte in 1_1_x/1_2_x) -->
                <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
                    <?php foreach ($orte as $navIdx => [$navWert, $navLabel]): ?>
                        <a href="<?= $e($fokusUrl('zugang')) ?>&q=<?= $artStart + $navIdx + 1 ?>"
                           data-wiz-goto="<?= $artStart + $navIdx ?>">
                            <span><?= $e($navLabel) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php foreach (($orteInfo[$ortWert]['seiten'] ?? ['']) as $seite): ?>
                    <?= $zugangSpalte($artKey, $ortWert, $seite, $artInfo['groessen']) ?>
                <?php endforeach; ?>
            </div>
        <?php
                $stepIndex++;
            endforeach;
        endforeach;
        ?>
    </div>

<?php elseif ($fokus === 'medikamente'): ?>

    <!-- ── MEDIKAMENTE: v1 medikamente/index.php (Link zur Maske + „Keine Medikamente") ── -->
    <div class="row" style="margin-left: 0">
        <?= $themenSpalte('medikamente') ?>
        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <a href="<?= $e($fokusUrl('medimaske')) ?>">
                <span>Medikamente auswählen</span>
            </a>
            <input type="checkbox" class="btn-check" id="medis-0" value="0"
                <?= $keineMedis ? 'checked' : '' ?> <?= $istGesperrt ? 'disabled' : '' ?> autocomplete="off">
            <label for="medis-0">Keine Medikamente</label>
        </div>
    </div>

<?php elseif ($fokus === 'medimaske'): ?>

    <!-- ── MEDIKAMENTEN-MASKE: v1 medikamente/1.php — Liste links,
         Eingabeformular rechts, zurück/Löschen/Speichern unten.
         Datenfluss komplett über die v2-Medis-API. ── -->
    <?php
    $wirkstoffe = Medikament::active()
        ->orderBy('wirkstoff')
        ->get(['wirkstoff', 'herstellername', 'dosierungen']);

    // Gaben-Liste serverseitig vorrendern ($medisEintraege liegt schon
    // dekodiert vor) — gleiche Struktur wie renderMedis() im JS unten, das
    // beim Init nur noch die Klick-Handler anhängt. Kein Lade-Spinner mehr:
    // der asynchrone Erst-Fetch entfiel, die Daten stehen als JSON im Template.
    $medisSortiert = $medisEintraege;
    usort($medisSortiert, static fn (array $a, array $b): int => strcmp((string) ($a['zeit'] ?? ''), (string) ($b['zeit'] ?? '')));
    ?>
    <div class="row mt-4 mx-4">
        <div class="col">
            <div class="w-100 p-3" style="background-color: #333333; min-height: 60vh; border-radius: 8px;" id="medis-list">
                <?php if ($medisSortiert === []): ?>
                    <div class="text-center p-4 text-light"><i class="fa-solid fa-pills" style="font-size: 3em;"></i><div class="mt-2">Keine Medikamente eingetragen</div></div>
                <?php else: ?>
                    <?php foreach ($medisSortiert as $med): ?>
                        <div class="medikament-item p-1 mb-1" data-timestamp="<?= $e($med['timestamp'] ?? '') ?>" style="cursor: pointer; transition: all 0.2s;"><div class="flex justify-content-between align-items-center text-light"><div class="medikament-compact"><span style="color:#a2a2a2"><?= $e($med['zeit'] ?? '') ?></span> <span><?= $e($med['wirkstoff'] ?? '') ?></span> <span><?= $e($med['dosierung'] ?? '') ?> <?= $e($med['einheit'] ?? '') ?></span> <span><?= $e($med['applikation'] ?? '') ?></span></div></div></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <script type="application/json" id="medis-initial"><?= json_encode($medisEintraege, $jsonFlags) ?></script>
        </div>
        <div class="col">
            <div class="row">
                <div class="col">
                    <select class="form-select ignis-input" name="medis-select" id="medis-select" required autocomplete="off" style="background-color: #333333; color: white;" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                        <option value="" disabled hidden selected>Wirkstoff</option>
                        <?php foreach ($wirkstoffe as $med): ?>
                            <?php
                            $displayName = (string) $med['wirkstoff'];
                            if (!empty($med['herstellername'])) {
                                $displayName = $med['herstellername'] . ' (' . $med['wirkstoff'] . ')';
                            }
                            ?>
                            <option value="<?= $e($med['wirkstoff']) ?>" data-dosierungen="<?= $e($med['dosierungen'] ?? '') ?>"><?= $e($displayName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <input type="time" name="medis-time" id="medis-time" class="ignis-input w-100" style="background-color: #333333; color: white;" step="1" value="<?= date('H:i:s') ?>" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                </div>
                <div class="col">
                    <select class="form-select ignis-input" name="medis-admission" id="medis-admission" required autocomplete="off" style="background-color: #333333; color: white;" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                        <option value="" disabled hidden selected>Applikationsart</option>
                        <?php foreach (MedikationCatalog::APPLIKATIONSWEGE as $code => $label): ?>
                            <option value="<?= $e($code) ?>"><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <div class="position-relative" id="dosierung-autocomplete-wrapper">
                        <input class="ignis-input w-100" type="text" placeholder="Dosierung" name="medis-concentration" id="medis-concentration" autocomplete="off" style="background-color: #333333; color: white;" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                        <div id="dosierung-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background-color: #444; border: 1px solid #555; border-radius: 4px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.3);"></div>
                    </div>
                </div>
                <div class="col">
                    <select class="form-select ignis-input" name="medis-unit" id="medis-unit" required autocomplete="off" style="background-color: #333333; color: white;" data-autosave-ignore <?= $istGesperrt ? 'disabled' : '' ?>>
                        <option value="" disabled hidden selected>Einheit</option>
                        <?php foreach (MedikationCatalog::EINHEITEN as $code => $label): ?>
                            <option value="<?= $e($code) ?>"><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="edivi__freigabe-buttons">
        <div class="row">
            <div class="col">
                <a href="<?= $e($fokusUrl('medikamente')) ?>">zurück</a>
            </div>
            <div class="col">
                <a href="#" id="delete-btn">Löschen</a>
            </div>
            <div class="col">
                <a href="#" id="save-btn">Speichern</a>
            </div>
        </div>
    </div>
    <!-- Ev2Select-Styles kommen zentral aus templates/_ev2-select-styles.php (Layout-Head) -->

<?php elseif ($fokus === 'weitere'): ?>

    <!-- ── WEITERE: v1 weitere/1.php (Lagerung), 3.php (Rettungstechnik),
         2.php (spezielle Maßnahmen) als Wizard-Schritte ── -->
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?= $themenSpalte('weitere') ?>
        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <a href="<?= $e($fokusUrl('weitere')) ?>&q=1" data-wiz-goto="0">
                <span>Lagerung</span>
            </a>
            <a href="<?= $e($fokusUrl('weitere')) ?>&q=2" data-wiz-goto="1">
                <span>Rettungstechnik</span>
            </a>
            <a href="<?= $e($fokusUrl('weitere')) ?>&q=3" data-wiz-goto="2">
                <span>spezielle Maßnahmen</span>
            </a>
            <?= $flagBox('waerme_passiv', 'passiver Wärmeerhalt') ?>
            <?= $flagBox('e_reposition', 'Reposition') ?>
            <?= $flagBox('e_verband', 'Verband') ?>
        </div>

        <div class="ev2-stepwrap<?= $initialStep === 0 ? '' : ' is-hidden' ?>" data-wiz-step data-wiz-fields="lagerung">
            <?= $radioCol('lagerung', BefundCatalog::LAGERUNG) ?>
        </div>
        <div class="ev2-stepwrap<?= $initialStep === 1 ? '' : ' is-hidden' ?>" data-wiz-step data-wiz-fields="rettungstechnik">
            <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
                <?php foreach (BefundCatalog::RETTUNGSTECHNIK as $code => $label): ?>
                    <input type="checkbox" class="btn-check" id="rettungstechnik-<?= $code ?>" name="rettungstechnik[]" value="<?= $code ?>"
                        data-autosave-ignore data-ev2-multijson="rettungstechnik" <?= in_array((int) $code, $rettungstechnikAktiv, true) ? 'checked' : '' ?>
                        <?= $istGesperrt ? 'disabled' : '' ?> autocomplete="off">
                    <label for="rettungstechnik-<?= $code ?>"><?= $e($label) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="ev2-stepwrap<?= $initialStep === 2 ? '' : ' is-hidden' ?>" data-wiz-step
             data-wiz-fields="waerme_aktiv,e_krintervention,e_kuehlung,e_narkose,e_tourniquet,e_cpr">
            <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
                <?= $flagBox('waerme_aktiv', 'aktiver Wärmeerhalt') ?>
                <?= $flagBox('e_krintervention', 'Krisenintervention') ?>
                <?= $flagBox('e_kuehlung', 'Kühlung') ?>
                <?= $flagBox('e_narkose', 'Notfallnarkose') ?>
                <?= $flagBox('e_tourniquet', 'Tourniquet') ?>
                <?= $flagBox('e_cpr', 'CPR / HLW') ?>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php if ($fokus === 'zugang' && !$istGesperrt): ?>
<script>
    // Zugang: v1-Logik (enotf-zugang.js) gegen den v2-Batch-Autosave.
    // Eine Größe pro Lokation; Merge erhält fremde Einträge (z. B. zvk);
    // leer nach Abwahl → '0' (v1-Parität), „Kein Zugang" abwählen → null.
    (function () {
        'use strict';
        var boxes = Array.prototype.slice.call(document.querySelectorAll('.zugang-checkbox'));
        var keinerBox = document.getElementById('c_zugang-0');

        function daten() {
            if (!window.__dynamicDaten) window.__dynamicDaten = {};
            return window.__dynamicDaten;
        }

        function bestehende() {
            var raw = daten().c_zugang;
            if (!raw || raw === '0' || raw === 0) return [];
            try {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) return parsed.filter(function (z) { return z && typeof z === 'object'; });
                if (parsed && typeof parsed === 'object') return [parsed];
            } catch (e) { /* Altdaten */ }
            return [];
        }

        function speichern(value, meldung) {
            daten().c_zugang = value;
            if (window.EnotfV2Autosave) window.EnotfV2Autosave.queue('c_zugang', value);
            if (meldung && typeof window.showToast === 'function') window.showToast(meldung, 'success');
        }

        boxes.forEach(function (box) {
            box.addEventListener('change', function () {
                var location = box.dataset.location;
                // Nur eine Größe pro Lokation (v1: gleiche data-location abwählen)
                boxes.forEach(function (b) {
                    if (b !== box && b.dataset.location === location && b.checked) b.checked = false;
                });

                var eintrag;
                try { eintrag = JSON.parse(box.dataset.zugang); } catch (e) { return; }

                // Eigene Lokation komplett ersetzen, alles andere behalten
                var merged = bestehende().filter(function (z) {
                    return !(z.art === eintrag.art && z.ort === eintrag.ort && z.seite === eintrag.seite);
                });
                var meldung;
                if (box.checked) {
                    merged.push(eintrag);
                    if (keinerBox) keinerBox.checked = false;
                    var artLabel = { pvk: 'PVK', zvk: 'ZVK', io: 'i.o.' }[eintrag.art] || eintrag.art;
                    meldung = artLabel + ' ' + eintrag.groesse + ' an ' + eintrag.ort
                        + (eintrag.seite ? ' ' + eintrag.seite : '') + ' gespeichert';
                } else {
                    meldung = merged.length === 0 ? 'Zugang entfernt' : 'Zugang von dieser Stelle entfernt';
                }
                speichern(merged.length === 0 ? '0' : JSON.stringify(merged), meldung);
            });
        });

        if (keinerBox) {
            keinerBox.addEventListener('change', function () {
                if (keinerBox.checked) {
                    boxes.forEach(function (b) { b.checked = false; });
                    speichern('0', 'Alle Zugänge entfernt');
                } else {
                    speichern(null, 'Zugang-Auswahl zurückgesetzt');
                }
            });
        }
    })();
</script>
<?php endif; ?>

<?php if ($fokus === 'medikamente' && !$istGesperrt): ?>
<script>
    // „Keine Medikamente": '0' setzen bzw. beim Abwählen zurück auf null
    // (v1: Checkbox medis-0 auf medikamente/index.php)
    (function () {
        'use strict';
        var box = document.getElementById('medis-0');
        if (!box) return;
        box.addEventListener('change', function () {
            var value = box.checked ? '0' : null;
            if (!window.__dynamicDaten) window.__dynamicDaten = {};
            window.__dynamicDaten.medis = value;
            if (window.EnotfV2Autosave) window.EnotfV2Autosave.queue('medis', value);
        });
    })();
</script>
<?php endif; ?>

<?php if ($fokus === 'medimaske'): ?>
<script>
    // Medikamenten-Maske: v1-Bedienung (Liste, Dosierungs-Vorschläge,
    // Einheiten-Parser) gegen die v2-Medis-API. Selects laufen über
    // Ev2Select (CEF-tauglich, Optik der v1-Custom-Dropdowns).
    (function () {
        'use strict';
        var LOCKED = <?= $istGesperrt ? 'true' : 'false' ?>;
        var ENR = <?= json_encode((string) $enr, $jsonFlags) ?>;
        var MEDIS_API = <?= json_encode(EnotfV2Url::api('medis'), $jsonFlags) ?>;
        // Gaben-Liste kommt eingebettet aus dem Template (#medis-initial,
        // Serverstand beim Rendern) — kein Erst-Fetch, kein Lade-Spinner
        var medis = [];
        try {
            var initialEl = document.getElementById('medis-initial');
            if (initialEl) medis = JSON.parse(initialEl.textContent) || [];
        } catch (e) { medis = []; }

        function toast(message, type) {
            if (typeof window.showToast === 'function') window.showToast(message, type || 'success');
        }

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // Nav-Füllstand nach jeder Änderung nachziehen (edivi-bridge)
        function syncDaten() {
            if (!window.__dynamicDaten) window.__dynamicDaten = {};
            window.__dynamicDaten.medis = medis.length ? JSON.stringify(medis) : '0';
            document.dispatchEvent(new Event('change'));
        }

        // Custom-Selects erst nach dem DOM-Aufbau enhancen (v1-Look-Seiten
        // haben data-page=massnahmen, der Auto-Init von ev2-select greift nicht)
        function enhanceSelects() {
            if (window.Ev2Select) window.Ev2Select.init(document.getElementById('edivi__content') || document);
        }

        function renderMedis() {
            var list = document.getElementById('medis-list');
            if (!list) return;
            if (!medis.length) {
                list.innerHTML = '<div class="text-center p-4 text-light"><i class="fa-solid fa-pills" style="font-size: 3em;"></i><div class="mt-2">Keine Medikamente eingetragen</div></div>';
                return;
            }
            var sortiert = medis.slice().sort(function (a, b) {
                return String(a.zeit || '').localeCompare(String(b.zeit || ''));
            });
            list.innerHTML = sortiert.map(function (med) {
                return '<div class="medikament-item p-1 mb-1" data-timestamp="' + esc(med.timestamp) + '" style="cursor: pointer; transition: all 0.2s;">'
                    + '<div class="flex justify-content-between align-items-center text-light">'
                    + '<div class="medikament-compact">'
                    + '<span style="color:#a2a2a2">' + esc(med.zeit) + '</span> '
                    + '<span>' + esc(med.wirkstoff) + '</span> '
                    + '<span>' + esc(med.dosierung) + ' ' + esc(med.einheit) + '</span> '
                    + '<span>' + esc(med.applikation) + '</span>'
                    + '</div></div></div>';
            }).join('');

            list.querySelectorAll('.medikament-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    list.querySelectorAll('.medikament-item').forEach(function (other) {
                        other.classList.remove('selected');
                        other.style.backgroundColor = 'transparent';
                    });
                    item.classList.add('selected');
                    item.style.backgroundColor = '#666';
                });
                item.addEventListener('mouseenter', function () { item.style.backgroundColor = '#555'; });
                item.addEventListener('mouseleave', function () {
                    if (!item.classList.contains('selected')) item.style.backgroundColor = 'transparent';
                });
            });
        }

        // ── Dosierungs-Vorschläge + Einheiten-Parser (v1 medikamente/1.php) ──
        var UNIT_MAP = { 'mcg': 'mcg', 'μg': 'mcg', 'µg': 'mcg', 'ug': 'mcg', 'mg': 'mg', 'g': 'g', 'ml': 'ml', 'ie': 'IE' };

        function parseDosierungAndSetUnit(value) {
            var match = value.match(/^([0-9]+(?:[.,][0-9]+)?)\s*(mcg|μg|µg|ug|mg|g|ml|ie)$/i);
            if (!match) return false;
            var unitValue = UNIT_MAP[match[2].toLowerCase()];
            if (!unitValue) return false;
            document.getElementById('medis-concentration').value = match[1];
            var unitSelect = document.getElementById('medis-unit');
            unitSelect.value = unitValue;
            unitSelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (window.Ev2Select) window.Ev2Select.refresh(unitSelect);
            return true;
        }

        function updateDosierungDropdown(select) {
            var opt = select.options[select.selectedIndex];
            var dosierungen = (opt && opt.dataset.dosierungen) || '';
            var dropdown = document.getElementById('dosierung-dropdown');
            dropdown.innerHTML = '';
            if (!dosierungen) return;
            dosierungen.split(',').map(function (d) { return d.trim(); }).filter(Boolean).forEach(function (value) {
                var item = document.createElement('div');
                item.className = 'dosierung-item';
                item.textContent = value;
                item.style.cssText = 'padding: 8px 12px; cursor: pointer; color: white; border-bottom: 1px solid #555;';
                item.addEventListener('mouseenter', function () { item.style.backgroundColor = '#555'; });
                item.addEventListener('mouseleave', function () { item.style.backgroundColor = 'transparent'; });
                item.addEventListener('click', function () {
                    if (!parseDosierungAndSetUnit(value)) {
                        document.getElementById('medis-concentration').value = value;
                    }
                    dropdown.style.display = 'none';
                    dropdown.innerHTML = '';
                });
                dropdown.appendChild(item);
            });
            if (dropdown.lastChild) dropdown.lastChild.style.borderBottom = 'none';
        }

        function getCurrentTime() {
            var now = new Date();
            return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0')
                + ':' + String(now.getSeconds()).padStart(2, '0');
        }

        function resetForm() {
            ['medis-select', 'medis-admission', 'medis-unit'].forEach(function (id) {
                var el = document.getElementById(id);
                el.value = '';
                if (window.Ev2Select) window.Ev2Select.refresh(el);
            });
            document.getElementById('medis-time').value = getCurrentTime();
            document.getElementById('medis-concentration').value = '';
        }

        function saveMedikament() {
            if (LOCKED) return;
            var eintrag = {
                wirkstoff: document.getElementById('medis-select').value,
                zeit: document.getElementById('medis-time').value,
                applikation: document.getElementById('medis-admission').value,
                dosierung: document.getElementById('medis-concentration').value.trim(),
                einheit: document.getElementById('medis-unit').value
            };
            if (!eintrag.wirkstoff || !eintrag.zeit || !eintrag.applikation || !eintrag.dosierung || !eintrag.einheit) {
                toast('Bitte füllen Sie alle Felder aus.', 'error');
                return;
            }
            fetch(MEDIS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ enr: ENR, medikament: eintrag })
            })
                .then(function (r) { return r.json().catch(function () { return {}; }).then(function (data) { return { status: r.status, data: data }; }); })
                .then(function (result) {
                    if (result.data && result.data.ok) {
                        medis.push(result.data.medikament);
                        renderMedis();
                        resetForm();
                        syncDaten();
                        toast('Medikament erfolgreich hinzugefügt.', 'success');
                    } else {
                        toast('Fehler beim Speichern: ' + ((result.data && result.data.error) || 'HTTP ' + result.status), 'error');
                    }
                })
                .catch(function () { toast('Fehler beim Speichern des Medikaments.', 'error'); });
        }

        function deleteSelected() {
            if (LOCKED) return;
            var selected = document.querySelector('.medikament-item.selected');
            if (!selected) {
                toast('Bitte wählen Sie ein Medikament zum Löschen aus.', 'warning');
                return;
            }
            fetch(MEDIS_API + '/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ enr: ENR, timestamp: selected.dataset.timestamp })
            })
                .then(function (r) { return r.json().catch(function () { return {}; }); })
                .then(function (data) {
                    if (data && data.ok) {
                        medis = medis.filter(function (m) { return String(m.timestamp) !== String(selected.dataset.timestamp); });
                        renderMedis();
                        syncDaten();
                        toast('Medikament erfolgreich gelöscht.', 'success');
                    } else {
                        toast('Fehler beim Löschen: ' + ((data && data.error) || 'unbekannt'), 'error');
                    }
                })
                .catch(function () { toast('Fehler beim Löschen des Medikaments.', 'error'); });
        }

        function init() {
            enhanceSelects();
            // Liste steht schon serverseitig im DOM — renderMedis erzeugt
            // dasselbe Markup neu und hängt die Klick-Handler an
            renderMedis();

            var saveBtn = document.getElementById('save-btn');
            if (saveBtn) saveBtn.addEventListener('click', function (e) { e.preventDefault(); saveMedikament(); });
            var deleteBtn = document.getElementById('delete-btn');
            if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.preventDefault(); deleteSelected(); });

            var medisSelect = document.getElementById('medis-select');
            if (medisSelect) medisSelect.addEventListener('change', function () { updateDosierungDropdown(medisSelect); });

            var input = document.getElementById('medis-concentration');
            var dropdown = document.getElementById('dosierung-dropdown');
            if (input && dropdown) {
                input.addEventListener('focus', function () {
                    if (dropdown.children.length > 0) dropdown.style.display = 'block';
                });
                input.addEventListener('input', function () {
                    var value = input.value.toLowerCase();
                    var hasVisible = false;
                    dropdown.querySelectorAll('.dosierung-item').forEach(function (item) {
                        var hit = item.textContent.toLowerCase().indexOf(value) !== -1;
                        item.style.display = hit ? 'block' : 'none';
                        if (hit) hasVisible = true;
                    });
                    dropdown.style.display = hasVisible ? 'block' : 'none';
                    parseDosierungAndSetUnit(input.value);
                });
                document.addEventListener('click', function (e) {
                    if (!(e.target instanceof Element) || !e.target.closest('#dosierung-autocomplete-wrapper')) {
                        dropdown.style.display = 'none';
                    }
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
<?php endif; ?>
