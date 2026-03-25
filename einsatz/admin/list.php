<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

if (!Permissions::check(['admin', 'fire.incident.qm'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

require __DIR__ . '/../../assets/config/database.php';

$showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';

$incidents = [];
try {
    if ($showArchived) {
        $stmt = $pdo->query("SELECT i.*, m.fullname AS leader_name FROM intra_fire_incidents i LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id WHERE i.archived = 1 ORDER BY i.archived_at DESC");
    } else {
        $stmt = $pdo->query("SELECT i.*, m.fullname AS leader_name FROM intra_fire_incidents i LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id WHERE i.archived = 0 ORDER BY i.created_at DESC");
    }
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Resolve federation leader names where local JOIN returned null
    foreach ($incidents as &$inc) {
        if (empty($inc['leader_name']) && !empty($inc['leader_id'])) {
            $inc['leader_name'] = \App\Federation\FederatedPersonnel::resolveName($pdo, $inc['leader_id']);
        }
    }
    unset($inc);

    // Append federated fire incidents (read-only)
    if (defined('FEDERATION_ENABLED') && FEDERATION_ENABLED && !$showArchived) {
        try {
            $fedStmt = $pdo->query("
                SELECT fcf.cached_data, fl.instance_name
                FROM intra_federation_cache_fire fcf
                JOIN intra_federation_links fl ON fl.instance_id = fcf.source_instance_id AND fl.is_active = 1
                ORDER BY fcf.incident_date DESC
            ");
            foreach ($fedStmt->fetchAll(PDO::FETCH_ASSOC) as $fedRow) {
                $fi = json_decode($fedRow['cached_data'], true);
                if (!$fi) continue;
                $fi['_federation_source'] = $fedRow['instance_name'];
                $fi['_federation_readonly'] = true;
                $fi['id'] = 'fed_' . ($fi['id'] ?? 0);
                $incidents[] = $fi;
            }
        } catch (\PDOException $e) {
            // Silently skip
        }
    }
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="protokolle">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container my-4">
        <nav class="admin-breadcrumb">
            <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
            <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
            <span>Protokolle</span>
            <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
            <span class="current">Einsatz QM</span>
        </nav>
        <div class="page-header mb-4">
            <h1>Einsatzprotokolle (QM)</h1>
            <div class="header-actions">
                <div class="d-flex align-items-center gap-3">
                    <div class="btn-toolbar-group">
                        <a href="<?= BASE_PATH ?>einsatz/admin/list.php" class="btn <?= !$showArchived ? 'active' : '' ?>">Aktiv</a>
                        <a href="<?= BASE_PATH ?>einsatz/admin/list.php?show_archived=1" class="btn <?= $showArchived ? 'active' : '' ?>">Archiv</a>
                    </div>
                    <a href="<?= BASE_PATH ?>einsatz/create.php" class="btn btn-success"><i class="fa-solid fa-plus"></i> Neu</a>
                </div>
            </div>
        </div>
        <?php App\Helpers\Flash::render(); ?>

        <?php if ($showArchived): ?>
            <div class="alert alert-info mb-3">
                <i class="fa-solid fa-archive me-2"></i>
                Sie sehen archivierte Einsätze. Diese sind aus den normalen Listen ausgeblendet.
            </div>
        <?php endif; ?>

        <div class="intra__tile p-3">
            <table class="table table-striped" id="table-incidents">
                <thead>
                    <tr>
                        <th>Einsatznummer</th>
                        <th>Beginn</th>
                        <th>Ort</th>
                        <th>Stichwort</th>
                        <th>Leiter</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $i): ?>
                        <?php
                        $isFederated = !empty($i['_federation_readonly']);
                        if ($isFederated) {
                            $i['finalized'] = $i['finalized'] ?? 1;
                            $i['status'] = $i['status'] ?? 2;
                            $i['started_at'] = $i['created_at'] ?? date('Y-m-d H:i:s');
                            $i['location'] = $i['location'] ?? '';
                            $i['keyword'] = $i['keyword'] ?? '';
                        }
                        if (!$i['finalized']) {
                            $statusClass = 'status-muted';
                            $statusText = 'Unfertig';
                        } else {
                            $statusMap = [
                                0 => ['status-muted', 'Ungesehen'],
                                1 => ['status-warning', 'In Prüfung'],
                                2 => ['status-success', 'Freigegeben'],
                                3 => ['status-danger', 'Ungenügend'],
                                4 => ['status-muted', 'Ausgeblendet'],
                            ];
                            $s = (int)$i['status'];
                            [$statusClass, $statusText] = $statusMap[$s] ?? ['status-muted', 'Unbekannt'];
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($i['incident_number'] ?? '-') ?><?php if ($isFederated): ?> <span class="badge" style="background:rgba(255,255,255,0.1);font-size:0.6rem;"><?= htmlspecialchars($i['_federation_source'] ?? '') ?></span><?php endif; ?></td>
                            <td><?php
                                $startDt = new DateTime($i['started_at'], new DateTimeZone('UTC'));
                                $startDt->setTimezone(new DateTimeZone('Europe/Berlin'));
                                echo htmlspecialchars($startDt->format('d.m.Y H:i'));
                            ?></td>
                            <td><?= htmlspecialchars($i['location']) ?></td>
                            <td><?= htmlspecialchars($i['keyword']) ?></td>
                            <td><?= htmlspecialchars($i['leader_name'] ?? '-') ?></td>
                            <td><span class="badge-status <?= $statusClass ?>"><span class="status-dot"></span><?= htmlspecialchars($statusText) ?></span></td>
                            <td>
                                <?php if ($isFederated): ?>
                                    <span style="font-size:var(--fs-xs);color:var(--text-dimmed);">read-only</span>
                                <?php else: ?>
                                <a class="btn btn-sm btn-soft-primary" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= (int)$i['id'] ?>">Öffnen</a>
                                <?php if ($showArchived): ?>
                                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php" class="d-inline">
                                        <input type="hidden" name="action" value="unarchive_incident">
                                        <input type="hidden" name="incident_id" value="<?= (int)$i['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success btn-icon" title="Wiederherstellen">
                                            <i class="fa-solid fa-box-open"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php" class="d-inline">
                                        <input type="hidden" name="action" value="archive_incident">
                                        <input type="hidden" name="incident_id" value="<?= (int)$i['id'] ?>">
                                        <button type="button" class="btn btn-sm btn-soft-warning btn-icon" title="Archivieren" onclick="event.preventDefault(); showConfirm('Einsatz wirklich archivieren? Er wird aus allen Listen ausgeblendet.', {danger: true, confirmText: 'Archivieren', title: 'Einsatz archivieren'}).then(result => { if(result) this.closest('form').submit(); });">
                                            <i class="fa-solid fa-archive"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#table-incidents').DataTable({
                stateSave: true,
                order: [
                    [0, 'desc']
                ],
                pageLength: 20,
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Einträgen",
                    "lengthMenu": "_MENU_ Einträge pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Suche:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": {
                        "first": "Erste",
                        "last": "Letzte",
                        "next": "Nächste",
                        "previous": "Vorherige"
                    }
                }
            });
        });
    </script>
    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>
</body>

</html>