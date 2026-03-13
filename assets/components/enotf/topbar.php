<?php
/**
 * Konfigurierbare Topbar-Komponente
 *
 * Variablen (vor dem Include setzen):
 *   $topbar_left_html   - HTML für die linke Seite (optional, default: Protokoll-Icons)
 *   $topbar_sync        - Array mit Sync-Icons: 'leitstelle', 'session', 'pat_sync' (default: alle)
 *   $topbar_show_abmelden - Abmelden-Button anzeigen (default: false)
 *   $topbar_show_notices  - Freigabe-/Lösch-Hinweise anzeigen (default: true)
 */

use App\Auth\Permissions;

$topbar_sync           = $topbar_sync ?? ['leitstelle', 'session', 'pat_sync'];
$topbar_show_abmelden  = $topbar_show_abmelden ?? false;
$topbar_show_notices   = $topbar_show_notices ?? true;
?>

<div class="container-fluid" id="edivi__topbar">
    <div class="row">
        <div class="col d-flex align-items-center">
            <?php if (isset($topbar_left_html)): ?>
                <?= $topbar_left_html ?>
            <?php else: ?>
                <a href="<?= BASE_PATH ?>enotf/overview.php" id="home" class="edivi__iconlink">
                    <svg width="38px" height="38px" viewBox="0 -0.5 21 21" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#ffffff">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier">
                            <title>grid [#ffffffff]</title>
                            <desc>Created with Sketch.</desc>
                            <defs> </defs>
                            <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g id="Dribbble-Light-Preview" transform="translate(-219.000000, -200.000000)" fill="#fff">
                                    <g id="icons" transform="translate(56.000000, 160.000000)">
                                        <path d="M181.9,54 L179.8,54 C178.63975,54 177.7,54.895 177.7,56 L177.7,58 C177.7,59.105 178.63975,60 179.8,60 L181.9,60 C183.06025,60 184,59.105 184,58 L184,56 C184,54.895 183.06025,54 181.9,54 M174.55,54 L172.45,54 C171.28975,54 170.35,54.895 170.35,56 L170.35,58 C170.35,59.105 171.28975,60 172.45,60 L174.55,60 C175.71025,60 176.65,59.105 176.65,58 L176.65,56 C176.65,54.895 175.71025,54 174.55,54 M167.2,54 L165.1,54 C163.93975,54 163,54.895 163,56 L163,58 C163,59.105 163.93975,60 165.1,60 L167.2,60 C168.36025,60 169.3,59.105 169.3,58 L169.3,56 C169.3,54.895 168.36025,54 167.2,54 M181.9,47 L179.8,47 C178.63975,47 177.7,47.895 177.7,49 L177.7,51 C177.7,52.105 178.63975,53 179.8,53 L181.9,53 C183.06025,53 184,52.105 184,51 L184,49 C184,47.895 183.06025,47 181.9,47 M174.55,47 L172.45,47 C171.28975,47 170.35,47.895 170.35,49 L170.35,51 C170.35,52.105 171.28975,53 172.45,53 L174.55,53 C175.71025,53 176.65,52.105 176.65,51 L176.65,49 C176.65,47.895 175.71025,47 174.55,47 M167.2,47 L165.1,47 C163.93975,47 163,47.895 163,49 L163,51 C163,52.105 163.93975,53 165.1,53 L167.2,53 C168.36025,53 169.3,52.105 169.3,51 L169.3,49 C169.3,47.895 168.36025,47 167.2,47 M181.9,40 L179.8,40 C178.63975,40 177.7,40.895 177.7,42 L177.7,44 C177.7,45.105 178.63975,46 179.8,46 L181.9,46 C183.06025,46 184,45.105 184,44 L184,42 C184,40.895 183.06025,40 181.9,40 M174.55,40 L172.45,40 C171.28975,40 170.35,40.895 170.35,42 L170.35,44 C170.35,45.105 171.28975,46 172.45,46 L174.55,46 C175.71025,46 176.65,45.105 176.65,44 L176.65,42 C176.65,40.895 175.71025,40 174.55,40 M169.3,42 L169.3,44 C169.3,45.105 168.36025,46 167.2,46 L165.1,46 C163.93975,46 163,45.105 163,44 L163,42 C163,40.895 163.93975,40 165.1,40 L167.2,40 C168.36025,40 169.3,40.895 169.3,42" id="grid-[#ffffffff]"> </path>
                                    </g>
                                </g>
                            </g>
                        </g>
                    </svg>
                </a>

                <?php
                if ($daten['freigegeben'] != 1) :
                    if (ENOTF_PREREG) : ?>
                        <a href="<?= BASE_PATH ?>enotf/schnittstelle/voranmeldung.php?enr=<?= $enr ?>" id="prereg" class="edivi__iconlink">
                            <i class="fa-solid fa-house-medical"></i><br>
                            <small>Anmeldung</small>
                        </a>
                    <?php endif; ?>

                    <a href="<?= BASE_PATH ?>enotf/protokoll/protokollart.php?enr=<?= $enr ?>" id="modify" class="edivi__iconlink">
                        <i class="fa-solid fa-sync"></i><br>
                        <small>Art ändern</small>
                    </a>

                    <button onclick="openShareProtocol(<?= $daten['id'] ?>, '<?= $enr ?>')" id="share" class="edivi__iconlink">
                        <i class="fa-solid fa-share-nodes"></i><br>
                        <small>Teilen</small>
                    </button>
                <?php endif; ?>

                <a href="<?= BASE_PATH ?>enotf/print/index.php?enr=<?= $enr ?>" id="print" class="edivi__iconlink">
                    <i class="fa-solid fa-file-waveform"></i><br>
                    <small>Protokoll</small>
                </a>

                <?php if (ENOTF_PREREG) : ?>
                    <a href="<?= BASE_PATH ?>enotf/hospital-availability.php" id="hospital-availability" class="edivi__iconlink">
                        <i class="fa-solid fa-hospital"></i><br>
                        <small>Verfügbarkeit</small>
                    </a>
                <?php endif; ?>

                <?php if (Permissions::check(['admin', 'edivi.edit'])) : ?>
                    <button onclick="openQMActions(<?= $daten['id'] ?>, '<?= $enr ?>', '<?= htmlspecialchars($daten['patname'] ?? 'Unbekannt') ?>')" id="qma" class="edivi__iconlink">
                        <i class="fa-solid fa-exclamation"></i><br>
                        <small>QM-Aktion</small>
                    </button>
                    <button onclick="openQMLog(<?= $daten['id'] ?>, '<?= $enr ?>', '<?= htmlspecialchars($daten['patname'] ?? 'Unbekannt') ?>')" id="qml" class="edivi__iconlink">
                        <i class="fa-solid fa-clock-rotate-left"></i><br>
                        <small>QM-Log</small>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="col text-end d-flex justify-content-end align-items-center">
            <a href="<?= BASE_PATH ?>enotf/login.php?prefill=1" class="d-flex flex-column align-items-center text-decoration-none text-reset align-self-stretch justify-content-between" id="topbar-crew-display" style="font-size: 0.85rem; line-height: 1.2; padding: 5px 15px;">
                <div class="d-flex align-items-start">
                    <div class="d-flex flex-column align-items-end justify-content-start">
                        <span data-crew-name="fahrername"><?= htmlspecialchars($_SESSION['fahrername'] ?? '') ?></span>
                    </div>
                    <div class="d-flex flex-column align-items-start ms-3">
                        <span data-crew-name="beifahrername" class="<?= empty($_SESSION['beifahrername']) ? 'd-none' : '' ?>"><?= htmlspecialchars($_SESSION['beifahrername'] ?? '') ?></span>
                        <span data-crew-name="praktikantname" class="<?= empty($_SESSION['praktikantname']) ? 'd-none' : '' ?>"><?= htmlspecialchars($_SESSION['praktikantname'] ?? '') ?></span>
                    </div>
                </div>
                <small style="font-size: 0.65rem;">Anmelden</small>
            </a>
            <?php if (!empty($topbar_sync)): ?>
                <?php
                $patSyncColor = '#ffffff';
                if (isset($daten['pat_synced'])) {
                    if ($daten['pat_synced'] == 2) $patSyncColor = '#f0ad4e';
                    elseif ($daten['pat_synced'] == 1) $patSyncColor = '#28a745';
                }
                ?>
                <div class="d-flex flex-column align-items-start me-3" style="font-size: 0.95rem; gap: 4px; padding-left: 15px; border-left: 2px solid #424242;">
                    <?php if (in_array('pat_sync', $topbar_sync)): ?>
                        <div class="d-flex align-items-center" style="gap: 8px;">
                            <?php if (in_array('leitstelle', $topbar_sync)): ?>
                                <span id="leitstelle-conn-icon" title="Verbindung zur Leitstelle">
                                    <i class="fa-solid fa-tower-broadcast" style="color: #ffffff;"></i>
                                </span>
                            <?php endif; ?>
                            <?php if (in_array('session', $topbar_sync)): ?>
                                <span id="session-conn-icon" title="Session-Verbindung">
                                    <i class="fa-solid fa-network-wired" style="color: #ffffff;"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center">
                            <span id="pat-sync-icon" title="Patientendaten-Sync">
                                <i class="fa-solid fa-up-down" style="color: <?= $patSyncColor ?>;"></i>
                            </span>
                        </div>
                    <?php else: ?>
                        <?php if (in_array('leitstelle', $topbar_sync)): ?>
                            <span id="leitstelle-conn-icon" title="Verbindung zur Leitstelle">
                                <i class="fa-solid fa-tower-broadcast" style="color: #ffffff;"></i>
                            </span>
                        <?php endif; ?>
                        <?php if (in_array('session', $topbar_sync)): ?>
                            <span id="session-conn-icon" title="Session-Verbindung">
                                <i class="fa-solid fa-network-wired" style="color: #ffffff;"></i>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="d-flex flex-column align-items-end me-3" style="padding-left: 15px; border-left: 2px solid #424242;">
                <span id="current-time"><?= $currentTime ?></span>
                <span id="current-date"><?= $currentDate ?></span>
            </div>
            <a href="https://github.com/intraRP/intraRP" target="_blank">
                <img src="https://web-assets.emergencyforge.de/images/defaultLogo.webp" alt="EmergencyForge Logo" height="64px" width="auto">
            </a>
            <?php if ($topbar_show_abmelden): ?>
                <button class="edivi__nidabutton-primary align-self-stretch d-flex align-items-center" type="button" data-bs-toggle="modal" data-bs-target="#logoutModal" style="padding: 0 15px; margin-left: 15px; border-left: 2px solid #424242;">abmelden</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if (!isset($topbar_left_html)): ?>
    <?php
    // Include QM Modals if they haven't been included yet
    if (!defined('QM_MODALS_INCLUDED')) {
        define('QM_MODALS_INCLUDED', true);
        include __DIR__ . '/qm-modals.php';
    }

    // Include Share Modals if they haven't been included yet
    if (!defined('SHARE_MODALS_INCLUDED')) {
        define('SHARE_MODALS_INCLUDED', true);
        include __DIR__ . '/share-modals.php';
    }
    ?>
<?php endif; ?>
<?php if (isset($enr) && in_array('leitstelle', $topbar_sync)): ?>
<script>
    (function() {
        const SYNC_TIMEOUT = 120; // Sekunden ohne Sync = rot
        const POLL_INTERVAL = 10000; // alle 10 Sekunden prüfen
        const enr = '<?= $enr ?>';

        function updateSyncIcons() {
            fetch('<?= BASE_PATH ?>api/enotf/sync-status.php?enr=' + encodeURIComponent(enr))
                .then(r => r.json())
                .then(data => {
                    // Cloud-Icon: pat_synced Status
                    const cloudIcon = document.querySelector('#pat-sync-icon i');
                    if (cloudIcon && data.pat_synced !== null) {
                        if (data.pat_synced === 2) cloudIcon.style.color = '#f0ad4e';
                        else if (data.pat_synced === 1) cloudIcon.style.color = '#28a745';
                        else cloudIcon.style.color = '#ffffff';
                    }

                    // Tower-Icon: Leitstellen-Verbindung
                    const towerIcon = document.querySelector('#leitstelle-conn-icon i');
                    if (towerIcon) {
                        if (data.last_emd_sync) {
                            const lastSync = new Date(data.last_emd_sync.replace(' ', 'T'));
                            const now = new Date();
                            const diffSeconds = (now - lastSync) / 1000;

                            if (diffSeconds <= SYNC_TIMEOUT) {
                                towerIcon.style.color = '#28a745';
                                towerIcon.parentElement.title = 'Verbindung zur Leitstelle aktiv';
                            } else {
                                towerIcon.style.color = '#dc3545';
                                towerIcon.parentElement.title = 'Keine Verbindung zur Leitstelle';
                            }
                        } else {
                            towerIcon.style.color = '#ffffff';
                            towerIcon.parentElement.title = 'Verbindung zur Leitstelle unbekannt';
                        }
                    }
                })
                .catch(() => {});
        }

        updateSyncIcons();
        setInterval(updateSyncIcons, POLL_INTERVAL);
    })();
</script>
<?php endif; ?>
<?php if ($topbar_show_notices && isset($daten)): ?>
    <?php if ($daten['freigegeben'] == 1 && $daten['hidden_user'] != 1) : ?>
        <div class="container-full edivi__notice edivi__notice-freigeber">
            <div class="row">
                <div class="col-1 text-end"><i class="fa-solid fa-info"></i></div>
                <div class="col">
                    Das Protokoll wurde durch <strong><?= $daten['freigeber_name'] ?></strong> am <strong><?= $daten['last_edit'] ?></strong> Uhr freigegeben. Es kann nicht mehr bearbeitet werden.
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($daten['hidden_user'] == 1) : ?>
        <div class="container-full edivi__notice edivi__notice-freigeber">
            <div class="row">
                <div class="col-1 text-end"><i class="fa-solid fa-info"></i></div>
                <div class="col">
                    Das Protokoll wurde durch <strong><?= $daten['freigeber_name'] ?></strong> am <strong><?= $daten['last_edit'] ?></strong> Uhr gelöscht. Es kann nicht mehr bearbeitet werden.
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>