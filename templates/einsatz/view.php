<?php
/**
 * View: Einsatz-Detail (Tab-Container)
 *
 * @var int                             $id
 * @var string                          $activeTab
 * @var array<string,mixed>             $incident
 * @var array<int,array<string,mixed>>  $allVehicles
 * @var array<int,array<string,mixed>>  $attachedVehicles
 * @var array<int,array<string,mixed>>  $sitreps
 * @var array<int,array<string,mixed>>  $asuProtocols
 * @var \PDO                            $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;

// Helper for date/time formatting (UTC → Europe/Berlin)
function fmt_dt(?string $ts): string
{
    if (!$ts) return '-';
    try {
        $dt = new DateTime($ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $dt->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $ts;
    }
}

// Helper to format seconds as MM:SS
function fmt_elapsed(int|string $seconds): string
{
    $sec = (int)$seconds;
    if ($sec <= 0) return '00:00';
    $mins = floor($sec / 60);
    $secs = $sec % 60;
    return sprintf('%02d:%02d', $mins, $secs);
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/enotf-custom-dropdown.css">
    <style>
        .enotf-dropdown-container.form-select {
            padding: .375rem .75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--bs-body-color);
            background-color: var(--bs-body-bg);
            background-clip: padding-box;
            border: var(--bs-border-width) solid var(--bs-border-color);
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
    </style>
    <script>
        const basePath = '<?= BASE_PATH ?>';
    </script>
</head>

<body data-bs-theme="dark" data-page="protokolle">
    <div class="d-flex">
        <?php
        $einsatzActivePage = 'view';
        ob_start();
        ?>
        <span class="einsatz-sidebar-section">Einsatzprotokoll</span>
        <a href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=stammdaten" class="sidebar-link <?= $activeTab === 'stammdaten' ? 'active' : '' ?>">
            <i class="fa-solid fa-info-circle"></i><span>Stammdaten</span>
        </a>
        <a href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=bericht" class="sidebar-link <?= $activeTab === 'bericht' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-alt"></i><span>Einsatzbericht</span>
        </a>
        <a href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=fahrzeuge" class="sidebar-link <?= $activeTab === 'fahrzeuge' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck"></i><span>Einsatzmittel</span>
        </a>
        <a href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=lagemeldungen" class="sidebar-link <?= $activeTab === 'lagemeldungen' ? 'active' : '' ?>">
            <i class="fa-solid fa-broadcast-tower"></i><span>Lagemeldungen</span>
        </a>
        <a href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=lagekarte" class="sidebar-link <?= $activeTab === 'lagekarte' ? 'active' : '' ?>">
            <i class="fa-solid fa-map-marked-alt"></i><span>Lagekarte</span>
        </a>
        <a href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=abschluss" class="sidebar-link <?= $activeTab === 'abschluss' ? 'active' : '' ?>">
            <i class="fa-solid fa-check-circle"></i><span>Abschluss</span>
        </a>
        <a href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=log" class="sidebar-link <?= $activeTab === 'log' ? 'active' : '' ?>">
            <i class="fa-solid fa-history"></i><span>Protokoll</span>
        </a>
        <?php $einsatzExtraNav = ob_get_clean(); ?>
        <?php include __DIR__ . '/../../assets/components/einsatz-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1>Einsatzprotokoll</h1>
                    <div>
                        <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
                            <span class="me-2 align-middle text-muted small">QM-Status:
                                <?php
                                if (!$incident['finalized']) {
                                    $badge = 'bg-secondary';
                                    $statusText = 'Unfertig';
                                } else {
                                    $statusMap = [
                                        0 => ['bg-secondary', 'Ungesehen'],
                                        1 => ['bg-warning', 'In Prüfung'],
                                        2 => ['bg-success', 'Freigegeben'],
                                        3 => ['bg-danger', 'Ungenügend'],
                                        4 => ['bg-dark', 'Ausgeblendet'],
                                    ];
                                    $s = (int)$incident['status'];
                                    [$badge, $statusText] = $statusMap[$s] ?? ['bg-secondary', 'Unbekannt'];
                                }
                                ?>
                                <span class="badge <?= $badge ?>"><?= htmlspecialchars($statusText) ?></span>
                            </span>
                            <?php if ($incident['finalized']): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#qmStatusModal">
                                    <i class="fa-solid fa-clipboard-check"></i> QM-Status ändern
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-2 text-muted">Einsatznummer: <?= htmlspecialchars($incident['incident_number'] ?? '–') ?></div>
                <?php Flash::render(); ?>

                <!-- Tab Content -->
                <?php
                // Tabs liegen weiterhin unter einsatz/tabs/ (noch nicht nach templates/ portiert)
                include dirname(__DIR__, 2) . '/einsatz/tabs/' . $activeTab . '.php';
                ?>
            </div>
        </div>
    </div>

    <!-- Finalize Confirm Modal -->
    <div class="modal fade" id="finalizeConfirmModal" tabindex="-1" aria-labelledby="finalizeConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="finalizeConfirmModalLabel">Einsatz abschließen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Möchten Sie diesen Einsatz wirklich abschließen?</strong></p>
                    <p class="text-muted small">Das Protokoll wird zur QM-Sichtung markiert und alle Daten werden gesperrt. Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php" class="d-inline">
                        <input type="hidden" name="action" value="finalize">
                        <input type="hidden" name="incident_id" value="<?= $id ?>">
                        <input type="hidden" name="return_tab" value="abschluss">
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-check-circle me-1"></i>Jetzt abschließen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
        <!-- QM Status Change Modal -->
        <div class="modal fade" id="qmStatusModal" tabindex="-1" aria-labelledby="qmStatusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="qmStatusModalLabel">QM-Status ändern</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="incident_id" value="<?= $id ?>">
                            <input type="hidden" name="return_tab" value="<?= $activeTab ?>">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="0" <?= (int)$incident['status'] === 0 ? 'selected' : '' ?>>Ungesehen</option>
                                    <option value="1" <?= (int)$incident['status'] === 1 ? 'selected' : '' ?>>In Prüfung</option>
                                    <option value="2" <?= (int)$incident['status'] === 2 ? 'selected' : '' ?>>Freigegeben</option>
                                    <option value="3" <?= (int)$incident['status'] === 3 ? 'selected' : '' ?>>Ungenügend</option>
                                    <option value="4" <?= (int)$incident['status'] === 4 ? 'selected' : '' ?>>Ausgeblendet</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="<?= BASE_PATH ?>assets/js/enotf-custom-dropdown.js"></script>
    <script>
        // Initialize custom dropdowns when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                eNOTFCustomDropdown.init();
            });
        } else {
            eNOTFCustomDropdown.init();
        }
    </script>
</body>

</html>
