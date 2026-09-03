<?php

/**
 * Section: Diagnose — eNOTF v2 im v1-Look.
 *
 * Nachbau der v1-Seiten plugins/enotf/templates/enotf/protokoll/diagnose/:
 *
 *   ÜBERSICHT (ohne ?t): index.php — Themen-Spalte links (Diagnose
 *     führend/weitere/Text) + readonly-Kacheln mit den aufgelösten Labels.
 *
 *   ?t=haupt   (v1 1.php + 1_1…1_10_11): Kategoriebaum als Wizard-Schritte
 *     — Kategorie-Spalte (ZNS…Trauma), Trauma mit dritter Nav-Ebene
 *     (Schädel-Hirn…spezielle), Codes als btn-check-Radios
 *     name="diagnose_haupt" (normaler Batch-Autosave).
 *   ?t=weitere (v1 2.php + 2_1…2_10_11): gleiche Struktur, Checkboxen
 *     name="diagnose_weitere[]" — Speicherung als JSON-Array von int über
 *     den Multi-JSON-Handler in edivi-bridge.js (data-ev2-multijson).
 *   ?t=text    (v1 3.php): Freitext-Box, textarea name="diagnose".
 *
 * Datenquellen: ausschließlich DiagnoseCatalog (LABELS/CATEGORIES) —
 * keine Label-Kopien. Die Spaltenaufteilung folgt den v1-Blättern
 * (Herz-Kreislauf 11–20/21–29, Sonstige 81–89/91–99, Rest eine Spalte);
 * Trauma-Blätter zeigen wie v1 nur den Schweregrad als Label.
 *
 * Zusatz gegenüber v1 (in v1-Optik): kleines Suchfeld über den
 * Kategorie-Kacheln — Treffer erscheinen als Kachel-Spalte, ein Klick
 * wählt den Code und springt in dessen Kategorie-Blatt.
 *
 * @var array<string,mixed> $protokoll
 * @var string $enr
 * @var bool $istGesperrt
 * @var string|null $fokusThema   ?t-Param aus ProtokollController::show
 */

use Plugin\EnotfV2\Catalogs\DiagnoseCatalog;
use Plugin\EnotfV2\Helpers\EnotfV2Url;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$sectionUrl = EnotfV2Url::protokoll($enr, 'diagnose');
$fokusUrl   = static fn (string $t): string => $sectionUrl . '?t=' . rawurlencode($t);

$fokus = $fokusThema ?? null;
if (!in_array($fokus, ['haupt', 'weitere', 'text'], true)) {
    $fokus = null;
}

// Kategoriebaum-Einstieg: ohne explizites ?q KEIN Blatt offen — nur die
// Kategorien-Spalte (v1: 1.php/2.php zeigen erst die Navigation);
// Klemmung auf die Schrittanzahl folgt unten nach dem Katalog-Aufbau
$initialStep = -1;
$qRaw = $_GET['q'] ?? null;
if (($fokus === 'haupt' || $fokus === 'weitere') && is_string($qRaw) && preg_match('/^\d+$/', $qRaw)) {
    $initialStep = (int) $qRaw - 1;
}

// ── Aktuelle Werte (Formate exakt wie v1) ────────────────────────────
$hauptCode = ($protokoll['diagnose_haupt'] !== null && $protokoll['diagnose_haupt'] !== '')
    ? (int) $protokoll['diagnose_haupt']
    : null;

$weitereCodes = [];
if (!empty($protokoll['diagnose_weitere'])) {
    $decoded = json_decode((string) $protokoll['diagnose_weitere'], true);
    if (is_array($decoded)) {
        $weitereCodes = array_map('intval', $decoded);
    }
}

$hauptText = $hauptCode !== null
    ? (DiagnoseCatalog::LABELS[$hauptCode] ?? 'Unbekannte Diagnose')
    : '';

$weitereTexte = [];
foreach ($weitereCodes as $code) {
    if (isset(DiagnoseCatalog::LABELS[$code])) {
        $weitereTexte[] = DiagnoseCatalog::LABELS[$code];
    }
}
$weitereText = implode(', ', $weitereTexte);

// ── Blatt-Struktur aus dem Katalog: Kategorien → Wizard-Schritte ─────
// Spaltenaufteilung wie die v1-Blätter: Herz-Kreislauf und Sonstige auf
// zwei Spalten (Split nach dem 10. bzw. vor Code 91), Rest eine Spalte.
$spalten = static function (string $key, array $codes): array {
    if ($key === 'herz_kreislauf') {
        return [array_slice($codes, 0, 10), array_slice($codes, 10)];
    }
    if ($key === 'sonstige') {
        $erste = array_values(array_filter($codes, static fn (int $c): bool => $c < 91));
        $rest  = array_values(array_filter($codes, static fn (int $c): bool => $c >= 91));
        return [$erste, $rest];
    }
    return [$codes];
};

// Schritte: 7 Nicht-Trauma-Kategorien + 11 Trauma-Unterblätter.
// Kategorie-Links (2. Spalte) springen per data-wiz-goto, Trauma deckt
// alle Unterblätter ab (data-wiz-covers, v1s dritte Nav-Ebene 1_10_x).
$steps        = [];   // ['cols' => int[][], 'trauma' => bool]
$katNav       = [];   // ['label', 'goto', 'covers'|null]
$traumaSteps  = [];   // Index → Subkategorie-Label (für die 3. Spalte)
foreach (DiagnoseCatalog::CATEGORIES as $key => $category) {
    if (isset($category['subcategories'])) {
        $first = count($steps);
        foreach ($category['subcategories'] as $sub) {
            $traumaSteps[count($steps)] = $sub['label'];
            $steps[] = ['cols' => [$sub['codes']], 'trauma' => true];
        }
        $katNav[] = [
            'label'  => $category['label'],
            'goto'   => $first,
            'covers' => implode(',', range($first, count($steps) - 1)),
        ];
        continue;
    }
    $katNav[] = ['label' => $category['label'], 'goto' => count($steps), 'covers' => null];
    $steps[]  = ['cols' => $spalten($key, $category['codes']), 'trauma' => false];
}

// Trauma-Blätter zeigen wie v1 nur den Schweregrad ("leicht" … "tödlich");
// die speziellen Traumata (Verbrennung usw.) behalten ihr volles Label.
$blattLabel = static function (int $code): string {
    $label = DiagnoseCatalog::LABELS[$code] ?? (string) $code;
    if ($code >= 101 && $code <= 193 && str_starts_with($label, 'Trauma ')) {
        return substr($label, (int) strrpos($label, ' ') + 1);
    }
    return $label;
};

// ── Spalten-Renderer (v1 1_x/2_x: btn-check in edivi__interactbutton) ─
$codeSpalte = static function (array $codes, string $mode) use ($e, $istGesperrt, $hauptCode, $weitereCodes, $blattLabel): string {
    $html = '<div class="col-2 d-flex flex-column edivi__interactbutton px-3">';
    foreach ($codes as $code) {
        $label = $blattLabel($code);
        if ($mode === 'haupt') {
            $checked = $hauptCode === $code ? ' checked' : '';
            $html .= '<input type="radio" class="btn-check" id="diagnose_haupt-' . $code . '" name="diagnose_haupt" value="' . $code . '"'
                . $checked . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
                . '<label for="diagnose_haupt-' . $code . '">' . $e($label) . '</label>';
        } else {
            $checked = in_array($code, $weitereCodes, true) ? ' checked' : '';
            $html .= '<input type="checkbox" class="btn-check" id="diagnose_weitere-' . $code . '" name="diagnose_weitere[]" value="' . $code . '"'
                . ' data-autosave-ignore data-ev2-multijson="diagnose_weitere"'
                . $checked . ($istGesperrt ? ' disabled' : '') . ' autocomplete="off">'
                . '<label for="diagnose_weitere-' . $code . '">' . $e($label) . '</label>';
        }
    }
    return $html . '</div>';
};

// Themen-Spalte (v1: Diagnose führend/weitere/Text in jeder Unterseite)
$themenSpalte = static function (?string $aktiv) use ($fokusUrl, $e): string {
    $items = [
        'haupt'   => ['label' => 'Diagnose (führend)', 'requires' => 'diagnose_haupt'],
        'weitere' => ['label' => 'Diagnose (weitere)', 'requires' => ''],
        'text'    => ['label' => 'Diagnose Text',      'requires' => ''],
    ];
    $html = '<div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">';
    foreach ($items as $key => $item) {
        $html .= '<a href="' . $e($fokusUrl($key)) . '"'
            . ($item['requires'] !== '' ? ' data-requires="' . $e($item['requires']) . '"' : '')
            . ($key === $aktiv ? ' class="active"' : '') . '>'
            . '<span>' . $e($item['label']) . '</span></a>';
    }
    return $html . '</div>';
};
?>

<?php if ($fokus === null): ?>

    <!-- ── ÜBERSICHT: Kachel-Seite (v1 diagnose/index.php) ── -->
    <div class="row" style="margin-left: 0">
        <?php if (!$istGesperrt): ?>
            <?= $themenSpalte(null) ?>
        <?php endif; ?>
        <div class="col edivi__overview-container">
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('haupt')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1 edivi__group-check">Diagnose (führend)</h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label for="diagnose_fuehrend" class="edivi__description" style="display: none;">Diagnose (führend)</label>
                                    <input type="text" name="diagnose_fuehrend" id="diagnose_fuehrend" class="w-100 ignis-input edivi__input-check" value="<?= $e($hauptText) ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('weitere')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Diagnose (weitere)</h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label for="diagnose_weitere_display" class="edivi__description" style="display: none;">Diagnose (weitere)</label>
                                    <input type="text" name="diagnose_weitere_display" id="diagnose_weitere_display" class="w-100 ignis-input" value="<?= $e($weitereText) ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= $e($fokusUrl('text')) ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Diagnose Text</h5>
                        <div class="col">
                            <div class="row my-2">
                                <div class="col">
                                    <label for="diagnose_text" class="edivi__description" style="display: none;">Diagnose Text</label>
                                    <textarea name="diagnose_text" id="diagnose_text" rows="5" class="w-100 ignis-input" style="resize: none" readonly><?= $e($protokoll['diagnose'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($fokus === 'text'): ?>

    <!-- ── FREITEXT (v1 diagnose/3.php) ── -->
    <div class="row" style="margin-left: 0">
        <?= $themenSpalte('text') ?>
        <div class="col-4 edivi__overview-container px-3" style="margin:0; padding:0;">
            <div class="row edivi__box" style="margin:0;">
                <h5 class="text-light px-2 py-1">Freitext Diagnose</h5>
                <div class="col">
                    <div class="row my-2">
                        <div class="col">
                            <textarea name="diagnose" id="diagnose" rows="5" class="w-100 ignis-input" style="resize: none" placeholder="..." <?= $istGesperrt ? 'readonly' : '' ?>><?= $e($protokoll['diagnose'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- ── KATEGORIEBAUM (v1 1.php/2.php + Blätter), Schritte client-seitig ── -->
    <div class="row" style="margin-left: 0" data-ev2-steps>
        <?= $themenSpalte($fokus) ?>

        <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
            <?php if (!$istGesperrt): ?>
                <!-- Diagnose-Suche (Zusatz gegenüber v1) -->
                <input type="text" id="diag-suche" class="w-100 ignis-input mb-2" placeholder="Suchen…"
                       data-autosave-ignore autocomplete="off" style="flex: 0 0 auto;">
            <?php endif; ?>
            <?php foreach ($katNav as $item): ?>
                <a href="<?= $e($fokusUrl($fokus)) ?>&q=<?= $item['goto'] + 1 ?>"
                   data-wiz-goto="<?= $item['goto'] ?>"
                   <?= $item['covers'] !== null ? 'data-wiz-covers="' . $e($item['covers']) . '"' : '' ?>>
                    <span><?= $e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php $initialStep = $initialStep >= count($steps) ? count($steps) - 1 : $initialStep; ?>
        <?php foreach ($steps as $i => $step): ?>
            <div class="ev2-stepwrap<?= $i === $initialStep ? '' : ' is-hidden' ?>" data-wiz-step
                 data-wiz-fields="<?= $fokus === 'haupt' ? 'diagnose_haupt' : 'diagnose_weitere' ?>">

                <?php if ($step['trauma']): ?>
                    <!-- Dritte Nav-Ebene wie v1 1_10.php (Trauma-Unterblätter) -->
                    <div class="col-2 d-flex flex-column edivi__interactbutton-more px-3">
                        <?php foreach ($traumaSteps as $stepIndex => $subLabel): ?>
                            <a href="<?= $e($fokusUrl($fokus)) ?>&q=<?= $stepIndex + 1 ?>" data-wiz-goto="<?= $stepIndex ?>">
                                <span><?= $e($subLabel) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($step['cols'] as $codes): ?>
                    <?= $codeSpalte($codes, $fokus) ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- Suchtreffer-Spalte (nur bei aktiver Suche sichtbar) -->
        <div class="col-3 d-flex flex-column edivi__interactbutton px-3 is-hidden" id="diag-suchtreffer"></div>
    </div>

    <?php if (!$istGesperrt): ?>
    <style>
        /* Suchtreffer im v1-Kachel-Look (btn-check-Label-Optik ohne Input) */
        #diag-suchtreffer button {
            border: 0; text-align: center; font: inherit;
        }
        #diag-suchtreffer .diag-treffer-leer {
            color: #888; font-size: .85rem; padding: 12px 4px; text-align: center;
        }
    </style>
    <script>
        // Diagnose-Suche: filtert alle Katalog-Codes; Klick wählt den Code
        // (Input im versteckten Blatt) und springt in dessen Kategorie.
        (function () {
            'use strict';
            var MODE = <?= json_encode($fokus) ?>;
            var suche = document.getElementById('diag-suche');
            var treffer = document.getElementById('diag-suchtreffer');
            if (!suche || !treffer) return;

            // Kandidaten aus dem DOM: Label-Text = Blatt-Label, Titel-Text =
            // volles Katalog-Label (Trauma-Blätter zeigen nur den Schweregrad)
            var LABELS = <?= json_encode(DiagnoseCatalog::LABELS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
            var inputs = Array.prototype.slice.call(document.querySelectorAll(
                MODE === 'haupt' ? 'input[name="diagnose_haupt"]' : 'input[name="diagnose_weitere[]"]'
            ));

            function stepIndexOf(input) {
                var step = input.closest('[data-wiz-step]');
                if (!step) return 0;
                var steps = Array.prototype.slice.call(document.querySelectorAll('[data-wiz-step]'));
                return steps.indexOf(step);
            }

            function render(term) {
                var stepWraps = document.querySelectorAll('[data-wiz-step]');
                if (term === '') {
                    treffer.classList.add('is-hidden');
                    treffer.innerHTML = '';
                    // Wizard neu synchronisieren: der zuletzt aktive Schritt
                    // steht im ?q-Param (wizard.js hält ihn per History aktuell).
                    // Ohne q bleibt wie beim Einstieg KEIN Schritt offen —
                    // nur die Kategorien-Spalte (v1: Themen-Index).
                    var q = -1;
                    try {
                        var qRaw = new URL(window.location.href).searchParams.get('q');
                        if (qRaw && /^\d+$/.test(qRaw)) q = parseInt(qRaw, 10) - 1;
                    } catch (e) {}
                    if (q < 0) return;
                    var links = document.querySelectorAll('[data-wiz-goto]');
                    var target = null;
                    links.forEach(function (l) {
                        if (parseInt(l.dataset.wizGoto, 10) === q) target = target || l;
                    });
                    if (target || links[0]) (target || links[0]).click();
                    return;
                }
                stepWraps.forEach(function (s) { s.classList.add('is-hidden'); });
                treffer.classList.remove('is-hidden');

                var html = '';
                var count = 0;
                inputs.forEach(function (input) {
                    var full = LABELS[input.value] || '';
                    if (full.toLowerCase().indexOf(term) === -1) return;
                    if (count >= 20) return;
                    count++;
                    html += '<button type="button" data-diag-code="' + input.value + '"'
                        + (input.checked ? ' style="background-color:#2f5e3f;"' : '')
                        + '>' + full.replace(/</g, '&lt;') + '</button>';
                });
                treffer.innerHTML = count > 0
                    ? html
                    : '<div class="diag-treffer-leer">Keine Diagnose gefunden.</div>';
            }

            suche.addEventListener('input', function () {
                render(suche.value.trim().toLowerCase());
            });

            treffer.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-diag-code]');
                if (!btn) return;
                var input = inputs.filter(function (i) { return i.value === btn.dataset.diagCode; })[0];
                if (!input) return;
                if (input.type === 'radio') {
                    input.checked = true;
                } else {
                    input.checked = !input.checked;
                }
                input.dispatchEvent(new Event('change', { bubbles: true }));

                // Suche schließen und in das Kategorie-Blatt des Codes springen
                var idx = stepIndexOf(input);
                suche.value = '';
                treffer.classList.add('is-hidden');
                treffer.innerHTML = '';
                var link = document.querySelector('[data-wiz-goto="' + idx + '"]');
                if (link) link.click();
            });
        })();
    </script>
    <?php endif; ?>

<?php endif; ?>
