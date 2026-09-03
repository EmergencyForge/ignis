<?php

/**
 * Section: Anamnese — eNOTF v2 im v1-Look.
 *
 * Ansichten, alle als getreue Nachbauten der v1-Seiten
 * (plugins/enotf/templates/enotf/protokoll/anamnese/):
 *
 *   ÜBERSICHT (ohne ?t): Kachel-Seite wie v1 index.php — Themen-Spalte
 *     (Anamnese/Symptome/Einsatzort) und edivi__box-Kacheln mit
 *     readonly-Zusammenfassungen.
 *
 *   ?t=anamnese: Freitext-Seite wie v1 1.php — großes Textfeld,
 *     Textblock-Buttons (Vorerkrankungen/Medikation/Allergien/Drogen)
 *     mit Untermenüs, Zeilen-Warnung, OK speichert über den v2-Batch-
 *     Autosave und geht zurück zur Übersicht.
 *
 *   ?t=symptome: Wizard mit zwei Schritten wie v1 2_1.php/2_2.php —
 *     Symptombeginn (Datum/Zeit + geschätzt/nicht-feststellbar-Kacheln)
 *     und NACA (initial / bei Übergabe als btn-check-Spalten).
 *     Exklusivlogik geschätzt↔nicht feststellbar liegt in
 *     edivi-bridge.js (bindSymptombeginn, psych-Muster).
 *
 *   ?t=einsatzort: Radio-Spalten wie v1 3.php (elokation).
 *
 * Datenformate wie v1: symptombeginn_datum ist DATE_FIELD (Autosave
 * schickt den deutschen force-german-date-Wert, ProtokollService
 * konvertiert nach Y-m-d); symptombeginn_zeit 'HH:MM' (force-24h-time);
 * geschätzt/nf tinyint 1/0 (Checkbox-Autosave); naca_- und elokation-Codes.
 *
 * @var array<string,mixed> $protokoll
 * @var string $enr
 * @var bool $istGesperrt
 * @var string|null $fokusThema   ?t-Param aus ProtokollController::show
 */

use Plugin\EnotfV2\Catalogs\EinsatzCatalog;
use Plugin\EnotfV2\Catalogs\NacaCatalog;
use Plugin\EnotfV2\Helpers\EnotfV2Url;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$wert = static fn (string $feld): string => (string) ($protokoll[$feld] ?? '');

$sectionUrl = EnotfV2Url::protokoll($enr, 'anamnese');
$fokusUrl   = static fn (string $thema): string => $sectionUrl . '?t=' . rawurlencode($thema);

$dis = $istGesperrt ? 'disabled' : '';

// Themen wie v1s Unterseiten-Nav (index.php): Symptome/Einsatzort mit
// denselben data-requires-Füllstandsfarben
$themen = [
    'anamnese'   => ['label' => 'Anamnese',   'requires' => ''],
    'symptome'   => ['label' => 'Symptome',   'requires' => 'naca_initial'],
    'einsatzort' => ['label' => 'Einsatzort', 'requires' => 'elokation'],
];

$fokus = $fokusThema ?? null;
if ($fokus !== null && !isset($themen[$fokus])) {
    $fokus = null;
}

// Freitext-Seite ist wie v1 (anamnese/1.php) chromelos: keine Topbar/
// Section-Nav, Rückweg über den OK-Button
if ($fokus === 'anamnese') {
    $sectionChromeless = true;
}

// Server-Initialschritt für ?t=symptome: ohne explizites ?q KEIN Schritt
// offen — nur die Subnav-Spalte (v1: Themen-Einstieg zeigt die Navigation,
// erst der Klick öffnet die Frage; wizard.js verhält sich identisch)
$initialStep = -1;
if ($fokus === 'symptome') {
    $qRaw = $_GET['q'] ?? null;
    if (is_string($qRaw) && preg_match('/^\d+$/', $qRaw)) {
        $initialStep = max(0, min(1, (int) $qRaw - 1));
    }
}

// Themen-Spalte (v1: edivi__interactbutton-more auf jeder Unterseite)
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

// Symptombeginn-Zusammenfassung "TT.MM.JJJJ HH:MM (geschätzt, …)" (v1 index.php)
$sbDatum = !empty($protokoll['symptombeginn_datum']) ? date('d.m.Y', strtotime((string) $protokoll['symptombeginn_datum'])) : '';
$sbZeit  = (string) ($protokoll['symptombeginn_zeit'] ?? '');
$sbOpts  = [];
if (!empty($protokoll['symptombeginn_geschaetzt'])) {
    $sbOpts[] = 'geschätzt';
}
if (!empty($protokoll['symptombeginn_nf'])) {
    $sbOpts[] = 'nicht feststellbar';
}
$sbDatetime = trim($sbDatum . ' ' . $sbZeit);
if ($sbDatetime !== '' && !empty($sbOpts)) {
    $sbDisplay = $sbDatetime . ' (' . implode(', ', $sbOpts) . ')';
} elseif ($sbDatetime !== '') {
    $sbDisplay = $sbDatetime;
} else {
    $sbDisplay = implode(', ', $sbOpts);
}

$nacaLabel = static function (string $feld) use ($protokoll): string {
    $code = $protokoll[$feld] ?? null;
    if ($code === null || $code === '') {
        return '';
    }
    return NacaCatalog::LABELS[(int) $code] ?? '';
};

$elokationLabel = '';
if (($protokoll['elokation'] ?? null) !== null && $protokoll['elokation'] !== '') {
    $elokationLabel = EinsatzCatalog::ELOKATION[(int) $protokoll['elokation']] ?? '';
}

// elokation-Radio-Spaltenaufteilung wie v1 3.php
$elokationCol1 = [1, 2, 3, 4, 5, 6, 7, 8, 9];
$elokationCol2 = [10, 11, 98, 99];

// Radio-Spalte (btn-check) für elokation/NACA — v1-Markup 1:1
$radioCol = static function (string $name, array $options, ?string $header = null, string $width = 'col-2') use ($protokoll, $e, $istGesperrt): string {
    $current = (string) ($protokoll[$name] ?? '');
    $html = '<div class="' . $e($width) . ' d-flex flex-column edivi__interactbutton px-3">';
    if ($header !== null) {
        $html .= '<label class="edivi__interactbutton-text">' . $e($header) . '</label>';
    }
    foreach ($options as $code => $optLabel) {
        $id      = $name . '-' . $code;
        $checked = $current !== '' && $current === (string) $code ? ' checked' : '';
        $html .= '<input type="radio" class="btn-check" id="' . $e($id) . '" name="' . $e($name) . '" value="' . $e($code) . '"'
            . $checked . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
            . '<label for="' . $e($id) . '">' . $e($optLabel) . '</label>';
    }
    return $html . '</div>';
};

// Anamnese-Textblöcke (v1 1.php): Hauptthemen + Untermenü-Bausteine
$textbloecke = [
    'vorerkrankungen' => ['label' => 'Vorerkrankungen', 'text' => 'VORERKRANKUNGEN:', 'sub' => [
        ['label' => 'keine bekannt',    'text' => 'Beim Pat. sind keine Vorerkrankungen bekannt.'],
        ['label' => 'nicht ermittelbar', 'text' => 'Es konnten keine Vorerkrankungen ermittelt werden.'],
    ]],
    'medikation' => ['label' => 'Medikation', 'text' => 'MEDIKATION:', 'sub' => [
        ['label' => 'keine Vormedikation', 'text' => 'Beim Pat. ist keine Vormedikation bekannt.'],
        ['label' => 'nicht ermittelbar',   'text' => 'Es konnte keine Vormedikation ermittelt werden.'],
    ]],
    'allergien' => ['label' => 'Allergien', 'text' => 'ALLERGIEN:', 'sub' => [
        ['label' => 'keine Allergien', 'text' => 'Beim Pat. sind keine Allergien bekannt.'],
    ]],
    'drogen' => ['label' => 'Drogen / Abusus', 'text' => 'DROGEN / ABUSUS:', 'sub' => [
        ['label' => 'Nikotin',            'text' => 'Bekannter Nikotinabusus'],
        ['label' => 'Alkohol',            'text' => 'Bekannter Alkoholabusus'],
        ['label' => 'Opiate',             'text' => 'Bekannter Opiatabusus'],
        ['label' => 'Benzodiazepine',     'text' => 'Bekannter Benzodiazepinabusus'],
        ['label' => 'Foetor alcoholicus', 'text' => 'Beim Pat. ist Alkoholgeruch wahrnehmbar.'],
    ]],
];
?>

<?php if ($fokus === null): ?>

    <!-- ── ÜBERSICHT: Kachel-Seite (v1 anamnese/index.php) ── -->
    <div class="row" style="margin-left: 0">
        <?php if (!$istGesperrt): ?>
            <?= $themenSpalte(null) ?>
        <?php endif; ?>
        <div class="col edivi__overview-container">
            <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('anamnese')) ?>" style="cursor:pointer">
                <h5 class="text-light px-2 py-1">Anamnese</h5>
                <div class="col">
                    <div class="row my-2">
                        <div class="col">
                            <label for="anamnese" class="edivi__description" style="display: none;">Anamnese</label>
                            <textarea name="anamnese" id="anamnese" class="w-100 ignis-input" style="height: 50vh; overflow-y: auto; resize: none; border: 0 !important;" readonly><?= $e($wert('anmerkungen')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('symptome')) ?>&q=1" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Symptome</h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label class="edivi__description">Symptombeginn</label>
                                    <input type="text" class="w-100 ignis-input" value="<?= $e($sbDisplay) ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('symptome')) ?>&q=2" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">NACA</h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label class="edivi__description">Initial</label>
                                    <input type="text" name="naca_initial_display" class="w-100 ignis-input edivi__input-check" value="<?= $e($nacaLabel('naca_initial')) ?>" readonly>
                                </div>
                                <div class="col">
                                    <label class="edivi__description">bei Übergabe</label>
                                    <input type="text" class="w-100 ignis-input" value="<?= $e($nacaLabel('naca_uebergabe')) ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('einsatzort')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Einsatzort <i id="icon-elokation_display" class="fa-solid fa-circle-exclamation" style="color:#d91425; margin-left:4px; display:none;"></i></h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label class="edivi__description" style="display:none">Einsatzort</label>
                                    <input type="text" name="elokation_display" class="w-100 ignis-input edivi__input-check" value="<?= $e($elokationLabel) ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($fokus === 'anamnese'): ?>

    <!-- ── ANAMNESE-FREITEXT (v1 anamnese/1.php): Textfeld + OK +
         Textblock-Buttons. Speichern über den v2-Batch-Autosave. ── -->
    <!-- chromelos (kein Topbar-Offset): Textfeld füllt die Seite wie v1 1.php -->
    <div class="d-flex flex-column" style="min-height: calc(100vh - 40px);">
        <div class="row" style="margin-left: 0; flex-grow: 1;">
            <div class="col-10 edivi__box py-1 px-3" style="margin: 10px">
                <textarea name="anmerkungen" id="anmerkungen" class="w-100 ignis-input" style="resize: none; height: 100%; min-height: 55vh; border-radius: 0;" rows="12" data-autosave-ignore <?= $istGesperrt ? 'readonly' : '' ?>><?= $e($wert('anmerkungen')) ?></textarea>
            </div>
            <?php if (!$istGesperrt): ?>
                <div class="col">
                    <div class="flex justify-center align-items-center" style="margin: 10px 0; height: 80px;">
                        <button type="button" id="save-anamnese-btn" class="ignis-btn ignis-btn--success px-4 w-100 h-full" style="font-size:1.4rem">OK</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div id="anmerkungen-line-warning" style="display: none; margin: 0 10px; background: rgba(217, 20, 37, 0.85); color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; flex-shrink: 0;">
            <i class="fa-solid fa-triangle-exclamation"></i> Der Text ist l&auml;nger als 22 Zeilen &ndash; ggf. wird nicht der komplette Text im Protokoll-Ausdruck sichtbar sein.
        </div>
        <?php if (!$istGesperrt): ?>
            <div class="flex" style="flex-shrink: 0;">
                <div class="d-flex flex-column edivi__interactbutton" id="textblock-main" style="flex: 0 0 auto; min-width: 220px;">
                    <?php foreach ($textbloecke as $key => $block): ?>
                        <a href="javascript:void(0)" class="anamnese-textblock-btn has-submenu" data-key="<?= $e($key) ?>" data-text="<?= $e($block['text']) ?>" data-newline="2"><span><?= $e($block['label']) ?></span></a>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($textbloecke as $key => $block): ?>
                    <div class="flex-col edivi__interactbutton textblock-submenu" id="textblock-sub-<?= $e($key) ?>" style="display: none; flex: 0 0 auto; min-width: 220px;">
                        <?php foreach ($block['sub'] as $sub): ?>
                            <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="<?= $e($sub['text']) ?>"><span><?= $e($sub['label']) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Textblöcke + Zeilen-Warnung + OK (v1 anamnese/1.php; Speichern
        // über den v2-Batch-Autosave statt jQuery-POST)
        document.addEventListener('DOMContentLoaded', function () {
            var textarea = document.getElementById('anmerkungen');
            var lineWarning = document.getElementById('anmerkungen-line-warning');

            function checkLineCount() {
                if (!textarea || !lineWarning) return;
                var lines = textarea.value.split('\n').length;
                lineWarning.style.display = lines > 22 ? 'block' : 'none';
            }

            checkLineCount();
            textarea.addEventListener('input', checkLineCount);

            var activeSubmenu = null;

            // newlineMode: 0 = kein Umbruch, 1 = \n davor, 2 = \n davor + \n danach
            function insertTextAtCursor(text, newlineMode) {
                var cursorPos = textarea.selectionStart;
                var before = textarea.value.substring(0, cursorPos);
                var after = textarea.value.substring(cursorPos);
                var prefix = '';
                if (newlineMode >= 1 && before.length > 0) prefix = '\n';
                if (newlineMode >= 3 && before.length > 0) prefix = '\n\n';
                var suffix = newlineMode >= 2 ? '\n' : '';
                var insertText = prefix + text + suffix;
                textarea.value = before + insertText + after;
                textarea.selectionStart = textarea.selectionEnd = cursorPos + insertText.length;
                textarea.focus();
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                checkLineCount();
            }

            document.querySelectorAll('.anamnese-textblock-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var key = this.getAttribute('data-key');
                    var text = this.getAttribute('data-text');
                    var addNewline = parseInt(this.getAttribute('data-newline') || '0', 10);
                    var self = this;

                    document.querySelectorAll('.textblock-submenu').forEach(function (sub) {
                        sub.style.display = 'none';
                    });
                    document.querySelectorAll('.anamnese-textblock-btn').forEach(function (b) {
                        b.classList.remove('active');
                    });

                    if (activeSubmenu === key) {
                        activeSubmenu = null;
                    } else {
                        insertTextAtCursor(text, addNewline);
                        var submenu = document.getElementById('textblock-sub-' + key);
                        if (submenu) submenu.style.display = 'flex';
                        self.classList.add('active');
                        activeSubmenu = key;
                    }
                });
            });

            document.querySelectorAll('.anamnese-subblock-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    insertTextAtCursor(this.getAttribute('data-text'), 0);
                });
            });

            // OK: als Batch speichern, dann zurück zur Übersicht
            var saveBtn = document.getElementById('save-anamnese-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function () {
                    if (!window.EnotfV2Autosave) return;
                    window.EnotfV2Autosave.queue('anmerkungen', textarea.value);
                    if (window.__dynamicDaten) window.__dynamicDaten.anmerkungen = textarea.value;
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
            }
        });
    </script>

<?php elseif ($fokus === 'symptome'): ?>

    <!-- ── SYMPTOME: Wizard Symptombeginn/NACA (v1 2_1.php/2_2.php) ── -->
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?= $themenSpalte('symptome') ?>

        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <a href="<?= $e($fokusUrl('symptome')) ?>&q=1" data-wiz-goto="0">
                <span>Symptombeginn</span>
            </a>
            <a href="<?= $e($fokusUrl('symptome')) ?>&q=2" data-wiz-goto="1" data-requires="naca_initial">
                <span>NACA</span>
            </a>
        </div>

        <!-- Schritt 1: Symptombeginn (v1 2_1.php) -->
        <div class="ev2-stepwrap<?= $initialStep === 0 ? '' : ' is-hidden' ?>" data-wiz-step
             data-wiz-fields="symptombeginn_datum,symptombeginn_zeit">
            <div class="col-2 d-flex flex-column edivi__interactbutton px-3">
                <label class="edivi__interactbutton-text">Datum</label>
                <input type="date" name="symptombeginn_datum" id="symptombeginn_datum"
                    class="edivi__interactbutton-input"
                    value="<?= !empty($protokoll['symptombeginn_datum']) ? date('Y-m-d', strtotime((string) $protokoll['symptombeginn_datum'])) : '' ?>"
                    data-autosave-ignore <?= $istGesperrt ? 'readonly' : '' ?>>
                <input type="checkbox" class="btn-check" id="symptombeginn_geschaetzt_1"
                    name="symptombeginn_geschaetzt" value="1"
                    <?= !empty($protokoll['symptombeginn_geschaetzt']) ? 'checked' : '' ?>
                    <?= $dis ?> autocomplete="off">
                <label for="symptombeginn_geschaetzt_1">geschätzt</label>
            </div>
            <div class="col-2 d-flex flex-column edivi__interactbutton px-3">
                <label class="edivi__interactbutton-text">Zeit</label>
                <input type="time" name="symptombeginn_zeit" id="symptombeginn_zeit"
                    class="edivi__interactbutton-input"
                    value="<?= $e($wert('symptombeginn_zeit')) ?>"
                    data-autosave-ignore <?= $istGesperrt ? 'readonly' : '' ?>>
                <input type="checkbox" class="btn-check" id="symptombeginn_nf_1"
                    name="symptombeginn_nf" value="1"
                    <?= !empty($protokoll['symptombeginn_nf']) ? 'checked' : '' ?>
                    <?= $dis ?> autocomplete="off">
                <label for="symptombeginn_nf_1">nicht feststellbar</label>
            </div>
            <div class="col-2 d-flex flex-column edivi__interactbutton justify-center px-3">
                <button type="button" id="save-symptombeginn-btn" class="ignis-btn ignis-btn--success w-100" <?= $dis ?>>
                    <i class="fa-solid fa-floppy-disk"></i> Speichern
                </button>
            </div>
        </div>

        <!-- Schritt 2: NACA (v1 2_2.php) -->
        <div class="ev2-stepwrap<?= $initialStep === 1 ? '' : ' is-hidden' ?>" data-wiz-step
             data-wiz-fields="naca_initial,naca_uebergabe">
            <?= $radioCol('naca_initial', NacaCatalog::LABELS, 'initial', 'col-3') ?>
            <?= $radioCol('naca_uebergabe', NacaCatalog::LABELS, 'bei Übergabe', 'col-3') ?>
        </div>
    </div>

    <script>
        // Symptombeginn (v1 2_1.php): Datum leer → heute vorbesetzen,
        // Zeit-Fokus → aktuelle Uhrzeit, Speichern-Button schickt Datum+Zeit
        // als EINEN v2-Batch (symptombeginn_datum ist DATE_FIELD — der
        // deutsche Anzeige-Wert wird serverseitig nach Y-m-d konvertiert).
        document.addEventListener('DOMContentLoaded', function () {
            var locked = <?= $istGesperrt ? 'true' : 'false' ?>;
            var datumInput = document.getElementById('symptombeginn_datum');
            var zeitInput = document.getElementById('symptombeginn_zeit');
            if (!datumInput || !zeitInput) return;

            var _now = new Date();
            var heute = String(_now.getDate()).padStart(2, '0') + '.' + String(_now.getMonth() + 1).padStart(2, '0') + '.' + _now.getFullYear();

            // Datum auf heute vorsetzen wenn leer (v1-Verhalten)
            if (!locked && !datumInput.value) {
                datumInput.value = heute;
            }

            // Zeit erst bei Klick/Fokus auf aktuelle Uhrzeit vorfüllen
            zeitInput.addEventListener('focus', function () {
                if (locked || zeitInput.value) return;
                var now = new Date();
                zeitInput.value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            });

            var saveBtn = document.getElementById('save-symptombeginn-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (locked || !window.EnotfV2Autosave) return;
                    window.EnotfV2Autosave.queue('symptombeginn_datum', datumInput.value);
                    window.EnotfV2Autosave.queue('symptombeginn_zeit', zeitInput.value);
                    if (window.__dynamicDaten) {
                        window.__dynamicDaten.symptombeginn_datum = datumInput.value;
                        window.__dynamicDaten.symptombeginn_zeit = zeitInput.value;
                    }
                    window.EnotfV2Autosave.flushNow();
                    var waited = 0;
                    var poll = setInterval(function () {
                        waited += 100;
                        if (!window.EnotfV2Autosave.hasPending() || waited >= 4000) {
                            clearInterval(poll);
                            if (window.showToast) window.showToast('Symptombeginn gespeichert.', 'success');
                        }
                    }, 100);
                });
            }
        });
    </script>

<?php elseif ($fokus === 'einsatzort'): ?>

    <!-- ── EINSATZORT: elokation-Radios (v1 anamnese/3.php) ── -->
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?php if (!$istGesperrt): ?>
            <?= $themenSpalte('einsatzort') ?>
        <?php endif; ?>
        <div class="ev2-stepwrap" data-wiz-step data-wiz-fields="elokation">
            <?= $radioCol('elokation', array_intersect_key(EinsatzCatalog::ELOKATION, array_flip($elokationCol1))) ?>
            <?= $radioCol('elokation', array_intersect_key(EinsatzCatalog::ELOKATION, array_flip($elokationCol2))) ?>
        </div>
    </div>

<?php endif; ?>
