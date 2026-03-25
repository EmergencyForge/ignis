<?php
/**
 * Admin Setup Checklist
 * Shows for admins when essential configuration is incomplete.
 * Dismissable via localStorage — won't appear again after dismissed.
 */
use App\Auth\Permissions;
if (!Permissions::check(['admin'])) return;

// Safe count helper — returns 0 if table doesn't exist
/** @return int<0, max> */
function _setupCount(PDO $pdo, string $table): int {
    try { return (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(); }
    catch (Exception $e) { return 0; }
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

// Required steps (must all be done to hide checklist)
$requiredSteps = 5;
$completedRequired = 0;
if ($checkConfigDone) $completedRequired++;
if ($checkDienstgrade > 0) $completedRequired++;
if ($checkQuali > 0) $completedRequired++;
if ($checkRollen > 0) $completedRequired++;
if ($checkMitarbeiter > 0) $completedRequired++;

// Optional count
$completedOptional = 0;
if ($checkPois > 0) $completedOptional++;
if ($checkFahrzeuge > 0) $completedOptional++;

$totalDisplay = $completedRequired + $completedOptional;
$totalAll = $requiredSteps + 2;

// Don't show if all required steps are done
if ($completedRequired >= $requiredSteps) return;

$stepNum = 1;
?>
<div class="intra__setup-checklist" id="setupChecklist">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0" style="color:var(--text-title);font-weight:600;">
            <i class="fa-solid fa-rocket" style="color:var(--main-color);margin-right:0.4rem"></i>
            System einrichten
        </h6>
        <button class="btn-ghost btn-sm" onclick="document.getElementById('setupChecklist').style.display='none';try{localStorage.setItem('intra_setup_dismissed','1')}catch(e){}" aria-label="Schließen" style="font-size:0.8rem;padding:0.2rem 0.5rem;">
            Ausblenden
        </button>
    </div>
    <p style="color:var(--text-dimmed);font-size:var(--fs-sm);margin-bottom:0.75rem;">
        Richte die wichtigsten Bereiche ein, um loszulegen.
    </p>
    <div class="setup-steps">
        <?php $done = $checkConfigDone; ?>
        <a href="<?= BASE_PATH ?>settings/system/config.php" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Systemdaten anpassen</strong>
                <small>Name, URL und Stadt eurer Fraktion eintragen</small>
            </span>
        </a>

        <?php $stepNum++; $done = $checkDienstgrade > 0; ?>
        <a href="<?= BASE_PATH ?>settings/personal/dienstgrade/index.php" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Dienstgrade anlegen</strong>
                <small>Ränge und Badges für Mitarbeiter definieren</small>
            </span>
        </a>

        <?php $stepNum++; $done = $checkQuali > 0; ?>
        <a href="<?= BASE_PATH ?>settings/personal/qualird/index.php" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Qualifikationen konfigurieren</strong>
                <small>RD-Qualifikationen für eNOTF-Protokolle</small>
            </span>
        </a>

        <?php $stepNum++; $done = $checkRollen > 0; ?>
        <a href="<?= BASE_PATH ?>benutzer/rollen/index.php" class="setup-step <?= $done ? 'done' : '' ?>">
            <span class="setup-step-icon"><?= $done ? '<i class="fa-solid fa-check"></i>' : $stepNum ?></span>
            <span class="setup-step-text">
                <strong>Rollen & Berechtigungen einrichten</strong>
                <small>Wer darf was sehen und bearbeiten</small>
            </span>
        </a>

        <?php $stepNum++; $done = $checkMitarbeiter > 0; ?>
        <a href="<?= BASE_PATH ?>mitarbeiter/create.php" class="setup-step <?= $done ? 'done' : '' ?>">
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
            <?php $done = $checkPois > 0; ?>
            <a href="<?= BASE_PATH ?>settings/pois/index.php" class="setup-step <?= $done ? 'done' : '' ?>">
                <span class="setup-step-icon" style="background:rgba(255,255,255,0.06);color:var(--text-dimmed);"><?= $done ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-map-marker-alt" style="font-size:0.65rem"></i>' ?></span>
                <span class="setup-step-text">
                    <strong>POIs einrichten</strong>
                    <small>Einsatzorte und Krankenhäuser auf der Karte</small>
                </span>
            </a>

            <?php $done = $checkFahrzeuge > 0; ?>
            <a href="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/index.php" class="setup-step <?= $done ? 'done' : '' ?>">
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
