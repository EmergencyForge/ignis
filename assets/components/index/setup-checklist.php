<?php

/**
 * Admin Setup Checklist
 * Shows for admins when essential configuration is incomplete.
 * Dismissable via localStorage; won't appear again after dismissed.
 */

use App\Auth\Permissions;
use Illuminate\Database\Capsule\Manager as Capsule;

if (!Permissions::check(['admin'])) return;

// Safe count helper: returns 0 if table doesn't exist or not whitelisted.
// Guarded, weil die Seite in einem Prozess mehrmals rendern kann (Feature-Tests).
if (!function_exists('_setupCount')) {
function _setupCount(string $table): int
{
    static $whitelist = [
        'intra_mitarbeiter_dienstgrade',
        'intra_mitarbeiter_rdquali',
        'intra_users_roles',
        'intra_mitarbeiter',
        'intra_edivi_pois',
        'intra_fahrzeuge',
    ];
    if (!in_array($table, $whitelist, true)) return 0;
    try {
        return Capsule::table($table)->count();
    } catch (Exception) {
        return 0;
    }
}
}

// Systemdaten: was in intra_config noch Pflicht ist, weiß App\Setup\SetupCheck.
$setupConfigOpen = \App\Setup\SetupCheck::fromConfig()->required();

// POIs gehören zum eNOTF-Plugin, ohne aktives Plugin entfällt der Schritt.
$setupEnotfActive = function_exists('app') && app(\App\Plugins\PluginLoader::class)->isActive('enotf');

$checkDienstgrade = _setupCount('intra_mitarbeiter_dienstgrade');
$checkQuali       = _setupCount('intra_mitarbeiter_rdquali');
$checkRollen      = _setupCount('intra_users_roles');
$checkMitarbeiter = _setupCount('intra_mitarbeiter');
$checkPois        = _setupCount('intra_edivi_pois');
$checkFahrzeuge   = _setupCount('intra_fahrzeuge');

// Step completion flags (computed once, reused in HTML)
$doneConfig       = $setupConfigOpen === [];
$doneDienstgrade  = $checkDienstgrade > 0;
$doneQuali        = $checkQuali > 0;
$doneRollen       = $checkRollen > 0;
$doneMitarbeiter  = $checkMitarbeiter > 0;
$donePois         = $checkPois > 0;
$doneFahrzeuge    = $checkFahrzeuge > 0;

// Required steps (must all be done to hide checklist)
$requiredSteps = 5;
$completedRequired = (int)$doneConfig + (int)$doneDienstgrade + (int)$doneQuali + (int)$doneRollen + (int)$doneMitarbeiter;

// Don't show if all required steps are done
if ($completedRequired >= $requiredSteps) return;

// Jede Prüfung ist ein Schritt; der erste offene bekommt seinen Link als Aktion.
$setupSteps = [
    [$doneConfig, 'Systemdaten anpassen', 'settings/system/config?setup=1'],
    [$doneDienstgrade, 'Dienstgrade anlegen', 'settings/personnel/ranks/index'],
    [$doneQuali, 'Qualifikationen konfigurieren', 'settings/personnel/ambskills/index'],
    [$doneRollen, 'Rollen und Berechtigungen einrichten', 'users/roles/index'],
    [$doneMitarbeiter, 'Ersten Mitarbeiter erstellen', 'personnel/list'],
];
if ($setupEnotfActive) {
    $setupSteps[] = [$donePois, 'POIs einrichten (optional)', 'settings/pois/index'];
}
$setupSteps[] = [$doneFahrzeuge, 'Fahrzeug anlegen (optional)', 'settings/vehicles/vehicles/index'];

$setupCurrentFound = false;
$empty = [
    'tone'    => 'info',
    'icon'    => 'fa-flag-checkered',
    'heading' => 2,
    'title'   => 'ignis ist fast startklar',
    'text'    => 'Mit Systemdaten, Dienstgraden, Qualifikationen, Rollen und dem ersten Mitarbeiter ist die Grundlage gelegt. Danach füllt sich das Dashboard von selbst.',
    'steps'   => [],
];
foreach ($setupSteps as [$setupDone, $setupLabel, $setupPath]) {
    $setupStep = ['label' => $setupLabel, 'state' => $setupDone ? 'done' : 'todo'];
    // Der Systemdaten-Schritt nennt die Felder, die noch fehlen.
    if (!$setupDone && $setupConfigOpen !== [] && str_starts_with($setupPath, 'settings/system/config')) {
        $setupStep['note'] = 'Noch offen: ' . implode(', ', array_column($setupConfigOpen, 'label'));
    }
    if (!$setupDone && !$setupCurrentFound) {
        $setupCurrentFound = true;
        $setupStep['state'] = 'current';
        $setupStep['action'] = ['label' => 'Jetzt einrichten', 'href' => BASE_PATH . $setupPath, 'style' => 'primary'];
    }
    $empty['steps'][] = $setupStep;
}
?>
<div class="mb-4" id="setupChecklist">
    <div class="flex justify-end mb-1">
        <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" onclick="document.getElementById('setupChecklist').style.display='none';try{localStorage.setItem('intra_setup_dismissed','1')}catch(e){}">
            Ausblenden
        </button>
    </div>
    <?php require dirname(__DIR__, 3) . '/templates/partials/empty.php'; ?>
</div>
<script>
    // Hide if previously dismissed
    if (localStorage.getItem('intra_setup_dismissed') === '1') {
        var cl = document.getElementById('setupChecklist');
        if (cl) cl.style.display = 'none';
    }
</script>
