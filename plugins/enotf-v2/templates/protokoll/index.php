<?php

/**
 * View: Protokoll-Editor-Shell (eNOTF v2)
 *
 * Rendert das Layout mit Section-Stepper und bindet das Template der
 * aktiven Section ein. Fehlt das Template einer Section (rettdaten.php,
 * erstbefund.php, …), greift der Platzhalter section-stub.php.
 *
 * @var string $enr
 * @var array<string,mixed> $protokoll            Komplette intra_edivi-Zeile
 * @var array<string,array{label:string,icon:string}> $sections
 * @var string $activeSection
 * @var string $sectionTemplateFile               Absoluter Pfad (Section oder Stub)
 * @var bool   $istGesperrt
 * @var bool   $istUserGeloescht
 * @var array  $crew
 */

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

// ── v1-Look: Sections, die über das getreue v1-Layout
// (_layout-protokoll.php) rendern. Sections außerhalb dieser Liste
// laufen über das ev2-Layout (_layout.php) weiter unten.
$__v1LookSections = ['erstbefund', 'massnahmen', 'diagnose', 'verlauf', 'abschluss', 'rettdaten', 'anamnese'];

if (in_array($activeSection, $__v1LookSections, true)) {
    $__title         = 'Protokoll #' . $enr;
    $__enr           = $enr;
    $__protokoll     = $protokoll;
    $__sections      = $sections;
    $__activeSection = $activeSection;
    $__istGesperrt   = $istGesperrt;
    $__crew          = $crew;
    $__sectionStatus = app(\Plugin\EnotfV2\Support\ConditionsService::class)->sectionStatus($protokoll);

    ob_start();
    // Section-Templates können setzen:
    //   $sectionBodyPage   — data-page-Attribut (z. B. Messwerte → "verlauf")
    //   $sectionChromeless — Ansicht ohne Topbar/Section-Nav (v1s chromelose
    //                        Unterseiten, siehe _layout-protokoll.php)
    require $sectionTemplateFile;
    $__content = ob_get_clean();

    $__bodyPage   = $sectionBodyPage ?? $activeSection;
    $__chromeless = $sectionChromeless ?? false;
    require dirname(__DIR__) . '/_layout-protokoll.php';
    return;
}

$__title         = 'Protokoll #' . $enr;
$__crew          = $crew;
$__enr           = $enr;
$__sections      = $sections;
$__activeSection = $activeSection;
$__istGesperrt   = $istGesperrt;
// Topbar-Patient: Name + Sync-Status (weiß/gelb/grün wie v1)
$__patname       = (string) ($protokoll['patname'] ?? '');
$__patSynced     = (int) ($protokoll['pat_synced'] ?? 0);
// Protokollart-Tag im Topbar-Kontext (links neben der ENR)
$__protArt       = (int) ($protokoll['prot_by'] ?? 0) === 1 ? 'NA' : 'RD';
// Initialer Füllstand für die Stepper-Badges — danach hält autosave.js
// sie über /api/enotf-v2/plausibility/{enr} aktuell.
$__sectionStatus = app(\Plugin\EnotfV2\Support\ConditionsService::class)->sectionStatus($protokoll);
ob_start();
?>

<?php if ($istGesperrt): ?>
    <div class="ignis-alert ignis-alert--warning mb-4">
        <i class="fa-solid fa-lock mr-2"></i>
        <?php if ($istUserGeloescht): ?>
            Dieses Protokoll wurde gelöscht und kann nicht mehr bearbeitet werden.
        <?php else: ?>
            Dieses Protokoll ist freigegeben<?= !empty($protokoll['freigeber_name']) ? ' (' . $e($protokoll['freigeber_name']) . ')' : '' ?>
            und kann nicht mehr bearbeitet werden.
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Seiten-Header: kompakt — Sectionname + Meta-Chips (dezent);
     der Patient steht in der Topbar, nicht hier -->
<div class="ev2-page-head">
    <h1 class="ev2-page-title"><?= $e($sections[$activeSection]['label']) ?></h1>
    <span class="ev2-chip font-mono">#<?= $e($enr) ?></span>
    <span class="ev2-chip" title="Protokollart">
        <?= (int) ($protokoll['prot_by'] ?? 0) === 1 ? 'Notarzt' : 'Rettungsdienst' ?>
    </span>
</div>

<?php
// Section-Template im selben Variablen-Scope einbinden — Sections
// greifen direkt auf $protokoll, $enr, $istGesperrt zu.
require $sectionTemplateFile;
?>

<?php
$__content = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
