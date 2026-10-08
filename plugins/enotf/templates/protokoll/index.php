<?php

/**
 * View: Protokoll-Editor-Shell
 *
 * Rendert das Layout mit Section-Navigation und bindet das Template der
 * aktiven Section ein.
 *
 * @var string $enr
 * @var array<string,mixed> $protokoll            Komplette intra_edivi-Zeile
 * @var array<string,array{label:string,icon:string}> $sections
 * @var string $activeSection
 * @var string $sectionTemplateFile               Absoluter Pfad
 * @var bool   $istGesperrt
 * @var bool   $nurLesen
 * @var array<string, mixed> $crew
 */

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$__title         = 'Protokoll #' . $enr;
$__enr           = $enr;
$__protokoll     = $protokoll;
$__sections      = $sections;
$__activeSection = $activeSection;
$__istGesperrt   = $istGesperrt;
$__nurLesen      = $nurLesen;
$__crew          = $crew;
$__sectionStatus = app(\Plugin\Enotf\Crew\Support\ConditionsService::class)->sectionStatus($protokoll);

ob_start();
// Section-Templates können setzen:
//   $sectionBodyPage:   data-page-Attribut (z. B. Messwerte → "verlauf")
//   $sectionChromeless: Ansicht ohne Topbar/Section-Nav (Fokusansichten,
//                        siehe _layout-protokoll.php)
require $sectionTemplateFile;
$__content = ob_get_clean();

$__bodyPage   = $sectionBodyPage ?? $activeSection;
$__chromeless = $sectionChromeless ?? false;
require dirname(__DIR__) . '/_layout-protokoll.php';
