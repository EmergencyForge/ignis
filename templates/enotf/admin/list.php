<?php
/**
 * View: eNOTF Admin/QM Protokollübersicht
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Flash;
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../../../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="edivi">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container my-4">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Protokolle</span> <span class="ignis-breadcrumb__item is-active">eNOTF QM</span></nav>
            <div class="page-header mb-4">
                <h1>Protokollübersicht</h1>
                <div class="header-actions">
                    <div class="flex align-items-center gap-3">
                        <div class="btn-toolbar-group">
                            <a href="?view=0" class="ignis-btn <?= (!isset($_GET['view']) || $_GET['view'] != 1) ? 'active' : '' ?>">Alle</a>
                            <a href="?view=1" class="ignis-btn <?= (isset($_GET['view']) && $_GET['view'] == 1) ? 'active' : '' ?>">Unbearbeitet</a>
                        </div>
                        <?php if (Permissions::check(['admin', 'edivi.edit'])) { ?>
                            <button onclick="showBulkDeleteModal()" class="ignis-btn ignis-btn--outline-danger ignis-btn--sm">
                                <i class="fa-solid fa-trash-can"></i> Leere Protokolle löschen
                            </button>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php Flash::render(); ?>
            <div class="row">
                <div class="col mb-5">
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-protokoll">
                            <thead>
                                <th scope="col">Einsatznummer</th>
                                <th scope="col">Patient</th>
                                <th scope="col">Angelegt am</th>
                                <th scope="col">Protokollant</th>
                                <th scope="col">Status</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->prepare("SELECT * FROM intra_edivi WHERE hidden <> 1");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                // Append federated eNOTF protocols (read-only)
                                if (\App\Federation\FederationMiddleware::isEnabled()) {
                                    try {
                                        $fedStmt = $pdo->query("
                                            SELECT fce.cached_data, fl.instance_name
                                            FROM intra_federation_cache_enotf fce
                                            JOIN intra_federation_links fl ON fl.instance_id = fce.source_instance_id AND fl.is_active = 1
                                            ORDER BY fce.protocol_date DESC
                                        ");
                                        foreach ($fedStmt->fetchAll(PDO::FETCH_ASSOC) as $fedRow) {
                                            $p = json_decode($fedRow['cached_data'], true);
                                            if (!$p) continue;
                                            $p['_federation_source'] = $fedRow['instance_name'];
                                            $p['_federation_readonly'] = true;
                                            // Ensure expected keys exist
                                            $p['protokoll_status'] = $p['protokoll_status'] ?? 2;
                                            $p['freigegeben'] = $p['freigegeben'] ?? 1;
                                            $p['hidden_user'] = $p['hidden_user'] ?? 0;
                                            $p['bearbeiter'] = $p['bearbeiter'] ?? '';
                                            $p['freigeber_name'] = $p['freigeber_name'] ?? '';
                                            $p['id'] = 'fed_' . ($p['id'] ?? 0);
                                            $result[] = $p;
                                        }
                                    } catch (\PDOException $e) {
                                        // Silently skip
                                    }
                                }

                                foreach ($result as $row) {
                                    $datetime = new DateTime($row['sendezeit']);
                                    $date = $datetime->format('d.m.Y | H:i');
                                    switch ($row['protokoll_status']) {
                                        case 0:
                                            $status = "<span class='ignis-chip ignis-chip--secondary'>Ungesehen</span>";
                                            break;
                                        case 1:
                                            $status = "<span title='Prüfer: " . $row['bearbeiter'] . "' class='ignis-chip ignis-chip--warning'>in Prüfung</span>";
                                            break;
                                        case 2:
                                            $status = "<span title='Prüfer: " . $row['bearbeiter'] . "' class='ignis-chip ignis-chip--success'>Geprüft</span>";
                                            break;
                                        case 4:
                                            $status = "<span title='Prüfer: " . $row['bearbeiter'] . "' class='ignis-chip ignis-chip--dark'>Ausgeblendet</span>";
                                            break;
                                        default:
                                            $status = "<span title='Prüfer: " . $row['bearbeiter'] . "' class='ignis-chip ignis-chip--danger'>Ungenügend</span>";
                                            break;
                                    }

                                    switch ($row['freigegeben']) {
                                        default:
                                            $freigabe_status = "";
                                            break;
                                        case 1:
                                            if ($row['hidden_user'] != 1) {
                                                $freigabe_status = "<span title='Freigeber: " . htmlspecialchars($row['freigeber_name']) . "' class='ignis-chip ignis-chip--success'>F</span>";
                                            } else {
                                                $freigabe_status = "";
                                            }
                                            break;
                                    }

                                    switch ($row['hidden_user']) {
                                        default:
                                            $hu_status = "";
                                            break;
                                        case 1:
                                            $hu_status = "<span title='Gelöscht: " . $row['freigeber_name'] . "' class='ignis-chip ignis-chip--danger'>G</span>";
                                            break;
                                    }

                                    if (isset($_GET['view']) && $_GET['view'] == 1) {
                                        if ($row['protokoll_status'] != 0 && $row['protokoll_status'] != 1) {
                                            continue;
                                        }
                                    }

                                    $patname = $row['patname'] ?? "Unbekannt";

                                    $isFederated = !empty($row['_federation_readonly']);
                                    $fedBadge = $isFederated ? " <span class='ignis-chip' style='background:rgba(255,255,255,0.1);font-size:0.6rem;'>" . htmlspecialchars($row['_federation_source'] ?? '') . "</span>" : "";

                                    $actions = '';
                                    if ($isFederated) {
                                        $actions = "<span style='font-size:var(--fs-xs);color:var(--text-dimmed);'>read-only</span>";
                                    } elseif (Permissions::check(['admin', 'edivi.edit'])) {
                                        $actions = "<button title='QM-Aktionen öffnen' onclick='openQMActions({$row['id']}, \"{$row['enr']}\", \"" . htmlspecialchars($row['patname'] ?? 'Unbekannt') . "\")' class='ignis-btn ignis-btn--sm btn-soft-primary'><i class='fa-solid fa-exclamation'></i></button> <button title='QM-Log öffnen' onclick='openQMLog({$row['id']}, \"{$row['enr']}\", \"" . htmlspecialchars($row['patname'] ?? 'Unbekannt') . "\")' class='ignis-btn ignis-btn--sm btn-outline-secondary'><i class='fa-solid fa-clock-rotate-left'></i></button> <a title='Protokoll löschen' href='" . EnotfUrl::admin('delete', ['id' => $row['id']]) . "' class='ignis-btn ignis-btn--sm btn-outline-danger ignis-btn--icon'><i class='fa-solid fa-trash'></i></a>";
                                    }
                                    echo "<tr" . ($isFederated ? " style='opacity:0.85;'" : "") . ">";
                                    echo "<td>" . htmlspecialchars($row['enr'] ?? '') . $fedBadge . "</td>";
                                    echo "<td>" . $patname . "</td>";
                                    echo "<td><span style='display:none'>" . ($row['sendezeit'] ?? '') . "</span>" . $date . "</td>";
                                    echo "<td>" . htmlspecialchars($row['pfname'] ?? '') . " " . $freigabe_status . $hu_status . "</td>";
                                    echo "<td>" . $status . "</td>";
                                    if ($isFederated) {
                                        echo "<td>{$actions}</td>";
                                    } else {
                                        echo "<td><a title='Protokoll ansehen' href='" . EnotfUrl::protokoll($row['enr']) . "' class='ignis-btn ignis-btn--sm btn-soft-primary' target='_blank'><i class='fa-solid fa-eye'></i></a> {$actions}</td>";
                                    }
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QM Actions Modal -->
    <div class="modal fade" id="qmActionsModal" tabindex="-1" aria-labelledby="qmActionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qmActionsModalLabel">QM-Funktionen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="qmActionsContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QM Log Modal -->
    <div class="modal fade" id="qmLogModal" tabindex="-1" aria-labelledby="qmLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qmLogModalLabel">QM-Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="qmLogContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Empty Protocols Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkDeleteModalLabel">Leere Protokolle löschen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bulkDeleteContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="bulkDeleteFooter" style="display: none;">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="ignis-btn ignis-btn--ghost-danger" onclick="executeBulkDelete()">
                        <i class="fa-solid fa-trash"></i> Jetzt löschen
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script src="<?= BASE_PATH ?>assets/js/modules/enotf-admin-list.js"></script>
    <script>
        initEnotfAdminListPage({
            basePath:      '<?= BASE_PATH ?>',
            qmActionsApi:  '<?= BASE_PATH ?>enotf/admin/qm-actions-modal',
            qmLogApi:      '<?= BASE_PATH ?>enotf/admin/qm-log-modal',
            bulkDeleteApi: '<?= BASE_PATH ?>api/enotf/bulk-delete-empty',
        });
    </script>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>