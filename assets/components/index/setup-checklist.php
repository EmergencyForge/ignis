<?php
/**
 * Admin Setup Checklist
 * Shows for admins when essential configuration is incomplete.
 * Dismissable via localStorage — won't appear again after dismissed.
 */
use App\Auth\Permissions;
if (!Permissions::check(['admin'])) return;

// Safe count helper — returns 0 if table doesn't exist or not whitelisted
function _setupCount(PDO $pdo, string $table): int {
    static $whitelist = [
        'intra_mitarbeiter_dienstgrade',
        'intra_mitarbeiter_rdquali',
        'intra_users_roles',
        'intra_mitarbeiter',
        'intra_edivi_pois',
        'intra_fahrzeuge',
    ];
    if (!in_array($table, $whitelist, true)) return 0;
    try { return (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(); }
    catch (Exception) { return 0; }
}

// Check what's configured
$checkConfigDone = false;
try {
    $cfgVal = $pdo->query("SELECT config_value FROM intra_system_config WHERE config_key = 'SYSTEM_URL' LIMIT 1")->fetchColumn();
    $checkConfigDone = ($cfgVal && $cfgVal !== 'CHANGE_ME');
} catch (Exception $e) {}

$checkDienstgrade = _setupCount($pdo, 'intra_mitarbeiter_dienstgrade');
$checkQuali       = _setupCount($pdo, 'intra_mitarbeiter_rdquali');
$checkRollen      = _setupCount($pdo, 'intra_users_roles');
$checkMitarbeiter = _setupCount($pdo, 'intra_mitarbeiter');
$checkPois        = _setupCount($pdo, 'intra_edivi_pois');
$checkFahrzeuge   = _setupCount($pdo, 'intra_fahrzeuge');

// Step completion flags (computed once, reused in HTML)
$doneConfig       = $checkConfigDone;
$doneDienstgrade  = $checkDienstgrade > 0;
$doneQuali        = $checkQuali > 0;
$doneRollen       = $checkRollen > 0;
$doneMitarbeiter  = $checkMitarbeiter > 0;
$donePois         = $checkPois > 0;
$doneFahrzeuge    = $checkFahrzeuge > 0;

// Required steps (must all be done to hide checklist)
$requiredSteps = 5;
$completedRequired = (int)$doneConfig + (int)$doneDienstgrade + (int)$doneQuali + (int)$doneRollen + (int)$doneMitarbeiter;
$completedOptional = (int)$donePois + (int)$doneFahrzeuge;

$totalDisplay = $completedRequired + $completedOptional;
$totalAll = $requiredSteps + 2;

// Don't show if all required steps are done
if ($completedRequired >= $requiredSteps) return;

$stepNum = 1;
?>
<div class="intra__setup-checklist" id="setupChecklist">
    <div class="flex items-center justify-between mb-2">
        <h6 class="mb-0" style="color:var(--text-title);font-weight:600;">
            <i class="fa-solid fa-rocket" style="color:var(--main-color);margin-right:0.4rem"></i>
            System einrichten
        </h6>
        <button class="ignis-btn ignis-btn--ghost ignis-btn--sm" onclick="document.getElementById('setupChecklist').style.display='none';try{localStorage.setItem('intra_setup_dismissed','1')}catch(e){}" aria-label="Schließen" style="font-size:0.8rem;padding:0.2rem 0.5rem;">
            Ausblenden
        </button>
    </div>
    <p style="color:var(--text-dimmed);font-size:var(--fs-sm);margin-bottom:0.75rem;">
        Richte die wichtigsten Bereiche ein, um loszulegen.
    </p>
    <div class="setup-steps">
        <?php $done = $doneConfig; ?>
        <a href="<?= BASE_PATH ?>settings/system/config" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Systemdaten anpassen</strong>
                <small>Name, URL und Stadt eurer Fraktion eintragen</small>
            </span>
        </a>

        <?php $stepNum++; $done = $doneDienstgrade; ?>
        <a href="<?= BASE_PATH ?>settings/personal/dienstgrade/index" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Dienstgrade anlegen</strong>
                <small>Ränge und Badges für Mitarbeiter definieren</small>
            </span>
        </a>

        <?php $stepNum++; $done = $doneQuali; ?>
        <a href="<?= BASE_PATH ?>settings/personal/qualird/index" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Qualifikationen konfigurieren</strong>
                <small>RD-Qualifikationen für eNOTF-Protokolle</small>
            </span>
        </a>

        <?php $stepNum++; $done = $doneRollen; ?>
        <a href="<?= BASE_PATH ?>benutzer/rollen/index" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Rollen & Berechtigungen einrichten</strong>
                <small>Wer darf was sehen und bearbeiten</small>
            </span>
        </a>

        <?php $stepNum++; $done = $doneMitarbeiter; ?>
        <a href="<?= BASE_PATH ?>mitarbeiter/list" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Ersten Mitarbeiter erstellen</strong>
                <small>Personalakte anlegen und Rang zuweisen</small>
            </span>
        </a>
    </div>

    <!-- Optional steps -->
    <div style="margin-top:0.75rem;padding-top:0.6rem;border-top:1px solid var(--darkgray);">
        <div style="font-size:var(--fs-xs);color:var(--text-dimmed);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.4rem;">Optional</div>
        <div class="setup-steps">
            <?php $done = $donePois; ?>
            <a href="<?= BASE_PATH ?>settings/pois/index" class="setup-step <?= $done ? 'done' : '' ?>">
                <span class="setup-step-icon" style="background:rgba(255,255,255,0.06);color:var(--text-dimmed);"><?= $done ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-map-marker-alt" style="font-size:0.65rem"></i>' ?></span>
                <span class="setup-step-text">
                    <strong>POIs einrichten</strong>
                    <small>Einsatzorte und Krankenhäuser auf der Karte</small>
                </span>
            </a>

            <?php $done = $doneFahrzeuge; ?>
            <a href="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/index" class="setup-step <?= $done ? 'done' : '' ?>">
                <span class="setup-step-icon" style="background:rgba(255,255,255,0.06);color:var(--text-dimmed);"><?= $done ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-truck" style="font-size:0.65rem"></i>' ?></span>
                <span class="setup-step-text">
                    <strong>Fahrzeug anlegen</strong>
                    <small>Fahrzeugflotte für Einsatzprotokolle definieren</small>
                </span>
            </a>
        </div>
    </div>

    <div style="margin-top:0.5rem;font-size:var(--fs-xs);color:var(--text-dimmed);">
        <?= $totalDisplay ?>/<?= $totalAll ?> Schritte abgeschlossen
    </div>
</div>
<script>
// Hide if previously dismissed
if (localStorage.getItem('intra_setup_dismissed') === '1') {
    var cl = document.getElementById('setupChecklist');
    if (cl) cl.style.display = 'none';
}
</script>
